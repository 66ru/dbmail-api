<?php

/**
 * Class GlobalConsoleCommand
 *
 * version 2.0
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
        return 'taskName';
    }

    /**
     * @param int[] $busyIds
     * @throws Exception
     * @return int[] returns task ids available to processing
     */
    public function getAvailableIds($busyIds)
    {
        throw new Exception(__METHOD__ . ' must be implemented');
    }

    /**
     * begin processing bunch of task ids
     * @param int[] $ids
     * @throws Exception
     * @return int pid of started process
     */
    protected function startProcess($ids)
    {
        throw new Exception(__METHOD__ . ' must be implemented');
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

    public function refreshAndCleanupTasks()
    {
        /** @var CronLock[] $myTasks */
        $myTasks = CronLock::model()->findAllByAttributes(array('hostname' => gethostname()));
        foreach ($myTasks as $task) {
            if (empty($task->pid) && empty($task->lastActivity)) {
                $this->logTask($task, 'failed to start', CLogger::LEVEL_ERROR);
            } else {
                $processStartedTime = @filectime('/proc/' . $task->pid);
                if (!$processStartedTime) {
                    $this->postProcessing($task->taskId); //todo: if this failed -> we do not delete task (
                    try {
                        $task->delete();
                    } catch (CDbException $e) {
                        $this->logTask($task, 'failed to remove lock', CLogger::LEVEL_ERROR);
                        throw $e;
                    }
                    continue;
                } elseif ($processStartedTime + $this->longRunningTimeout < time()) {
                    $this->logTask($task, 'freezed', CLogger::LEVEL_WARNING);
                }
            }

            try {
                $task->lastActivity = new CDbExpression('NOW()');
                $task->save();
            } catch (CDbException $e) {
                $this->logTask($task, 'failed to update lastActivity', CLogger::LEVEL_ERROR);
                throw $e;
            }
        }
    }

    public function releaseExpiredTasks()
    {
        /** @var CronLock[] $expiredTasks */
        $expiredTasks = CronLock::model()->findAll(
            'lastActivity <= NOW() - INTERVAL :changeOwnerTimeout SECOND',
            array(
                ':changeOwnerTimeout' => $this->changeOwnerTimeout,
            )
        );

        foreach ($expiredTasks as $task) {
            try {
                $task->delete();
                $this->logTask(
                    $task,
                    "(last activity at {$task->lastActivity}) lock was released by " . gethostname(),
                    CLogger::LEVEL_WARNING
                );
            } catch (CDbException $e) {
                $this->logTask($task, 'failed to release lock', CLogger::LEVEL_ERROR);
                throw $e;
            }
        }
    }

    protected function lockAndStartTasks()
    {
        $queuedTaskIds = array();
        $busyIds = array();

        /** @var CronLock[] $lockedTasks */
        $lockedTasks = CronLock::model()->findAllByAttributes(array('taskName' => $this->taskName));
        foreach ($lockedTasks as $lockedTask) {
            $busyIds[] = $lockedTask->taskId;
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
     * @throws CDbException
     */
    protected function startTasks($taskIds)
    {
        $pid = $this->startProcess($taskIds);

        try {
            $criteria = CronLock::model()->getCommandBuilder()->createColumnCriteria(
                CronLock::model()->tableName(),
                array(
                    'taskName' => $this->taskName,
                    'taskId' => $taskIds,
                )
            );
            CronLock::model()->updateAll(
                array(
                    'pid' => $pid,
                    'lastActivity' => new CDbExpression('NOW()'),
                ),
                $criteria
            );
        } catch (CDbException $e) {
            Yii::log("failed update pid on {$this->taskName}-(" . implode(', ', $taskIds) . ")", CLogger::LEVEL_ERROR);
            throw $e;
        }
    }

    /**
     * @param CronLock $task
     * @param string $message
     * @param $level
     */
    public function logTask($task, $message, $level)
    {
        Yii::log(
            "task {$task->taskName}-{$task->taskId}@{$task->hostname} " . $message,
            $level,
            'cron'
        );
    }
}