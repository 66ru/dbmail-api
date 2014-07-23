<?php

/**
 * Class GlobalConsoleCommand
 *
 * version 3.0
 *
 * @property CDbConnection dbConnection
 * @property CDbCommandBuilder commandBuilder
 * @property string taskName
 */
abstract class GlobalConsoleCommand extends CConsoleCommand
{
    /**
     * @var int ids count to pass to startProcess method
     */
    public $aggregateTasks = 1;

    /**
     * If process executes more than $longRunningTimeout seconds,
     * it will be considered as long-running
     * and warning message will be logged
     * @var int
     */
    public $longRunningTimeout = 3600; // 1h

    /**
     * If any running task $lastActivity value older than $changeOwnerTimeout,
     * this lock will be released, so
     * this task will be acquired by any of active nodes.
     * @var int seconds
     */
    public $changeOwnerTimeout = 600; // 10m

    /**
     * @return string
     */
    public function getTaskName()
    {
        return str_replace('Command', '', get_class($this));
    }

    /**
     * @param int[] $busyIds
     * @throws GlobalCommandException
     * @return int[] returns task ids available to processing
     */
    public function getAvailableIds($busyIds)
    {
        throw new GlobalCommandException(__METHOD__ . ' must be implemented');
    }

    /**
     * begin processing bunch of task ids
     * @param int[] $ids
     * @throws GlobalCommandException
     * @return int pid of started process
     */
    protected function startProcess($ids)
    {
        throw new GlobalCommandException(__METHOD__ . ' must be implemented');
    }

    /**
     *
     * @param int $id
     */
    public function postProcessing($id)
    {
    }

    public function actionIndex()
    {
        $this->refreshAndCleanupTasks();
        $this->releaseExpiredTasks();
        $this->lockAndStartTasks();
    }

    /**
     * @throws GlobalCommandException
     */
    public function refreshAndCleanupTasks()
    {
        /** @var ESentryComponent $sentry */
        $sentry = Yii::app()->getComponent('RSentryException');

        try {
            $criteria = new CDbCriteria();
            $criteria->addColumnCondition(array('hostname' => gethostname()));
            CronLock::model()->updateAll(
                array(
                    'lastActivity' => new CDbExpression('NOW()'),
                ),
                $criteria
            );
        } catch (CDbException $e) {
            throw new GlobalCommandException('failed update lastActivity', 0, $e);
        }

        /** @var CronLock[] $myTasks */
        $myTasks = CronLock::model()->findAllByAttributes(array('hostname' => gethostname()));
        foreach ($myTasks as $task) {
            if (!empty($task->pid)) {
                $processStartedTime = @filectime('/proc/' . $task->pid);
                if (!$processStartedTime) { // process missing –> postprocess
                    $this->postProcessing($task->taskId); //todo: if this failed -> we do not delete task (
                    try {
                        if (!$task->delete()) {
                            $sentry->setContext($task->errors);
                            throw new GlobalCommandException('failed to remove lock');
                        }
                    } catch (CDbException $e) {
                        $sentry->setContext($task->attributes);
                        throw new GlobalCommandException('failed to remove lock', 0, $e);
                    }
                    continue;
                } elseif ($processStartedTime + $this->longRunningTimeout < time()) {
                    $e = new GlobalCommandException('task freezed');
                    $e->severety = E_WARNING;
                    $sentry->captureException($e, $task->attributes);
                }
            }
        }
    }

    /**
     * @throws GlobalCommandException
     */
    public function releaseExpiredTasks()
    {
        /** @var ESentryComponent $sentry */
        $sentry = Yii::app()->getComponent('RSentryException');

        try {
            /** @var CronLock[] $expiredTasks */
            $expiredTasks = CronLock::model()->findAll(
                'lastActivity <= NOW() - INTERVAL :changeOwnerTimeout SECOND',
                array(
                    ':changeOwnerTimeout' => $this->changeOwnerTimeout,
                )
            );
        } catch (CDbException $e) {
            throw new GlobalCommandException('failed fetch expired tasks', 0, $e);
        }

        foreach ($expiredTasks as $task) {
            try {
                if (!$task->delete()) {
                    $sentry->setContext($task->attributes);
                    throw new GlobalCommandException('failed to release lock');
                }

                $e = new GlobalCommandException('task lock was released');
                $e->severety = E_WARNING;
                $additionalData = $task->attributes;
                $additionalData['releasedBy'] = gethostname();
                $sentry->captureException($e, $additionalData);
            } catch (CDbException $e) {
                throw new GlobalCommandException('failed to release lock', 0, $e);
            }
        }
    }

    /**
     * @throws GlobalCommandException
     */
    protected function lockAndStartTasks()
    {
        $queuedTaskIds = array();
        $busyIds = array();

        try {
            /** @var CronLock[] $lockedTasks */
            $lockedTasks = CronLock::model()->findAllByAttributes(array('taskName' => $this->taskName));
            foreach ($lockedTasks as $lockedTask) {
                $busyIds[] = $lockedTask->taskId;
            }
        } catch (CDbException $e) {
            throw new GlobalCommandException('failed fetch locked tasks', 0, $e);
        }

        foreach ($this->getAvailableIds($busyIds) as $taskId) {
            try {
                $lock = new CronLock();
                $lock->setAttributes(
                    array(
                        'hostname' => gethostname(),
                        'taskName' => $this->taskName,
                        'taskId' => $taskId,
                    )
                );
                $lock->save();
                $queuedTaskIds[] = $taskId;
            } catch (CDbException $e) {
                continue;
            }
            if (count($queuedTaskIds) == $this->aggregateTasks) {
                $this->startTasks($queuedTaskIds);
                $queuedTaskIds = array();
            }
        }

        if (!empty($queuedTaskIds)) {
            $this->startTasks($queuedTaskIds);
        }
    }

    /**
     * @param int[] $taskIds
     * @throws GlobalCommandException
     */
    protected function startTasks($taskIds)
    {
        /** @var ESentryComponent $sentry */
        $sentry = Yii::app()->getComponent('RSentryException');

        $pid = $this->startProcess($taskIds);

        try {
            $criteria = new CDbCriteria();
            $criteria->addColumnCondition(array('taskName' => $this->taskName));
            $criteria->addInCondition('taskId', $taskIds);
            $updated = CronLock::model()->updateAll(
                array(
                    'pid' => $pid,
                    'lastActivity' => new CDbExpression('NOW()'),
                ),
                $criteria
            );
            if (!$updated) {
                $sentry->setContext(array('taskName' => $this->taskName, 'taskIds' => $taskIds));
                throw new GlobalCommandException("can't find rows for update pid and lastActivity");
            }
        } catch (CDbException $e) {
            throw new GlobalCommandException("failed update pid", 0, $e);
        }
    }
}

class GlobalCommandException extends Exception
{
    public $severety = E_ERROR;

    public function getSeverity()
    {
        return $this->severety;
    }
}