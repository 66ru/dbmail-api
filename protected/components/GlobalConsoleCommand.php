<?php

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
    public function getTaskPrefix()
    {
        return 'prefix';
    }

    /**
     * @param int[] $busyIds
     * @return int[] returns non-processing task ids
     */
    public function getAvailableIds($busyIds)
    {
        throw new Exception(__METHOD__ . ' must be implemented');
    }

    /**
     * begin processing bunch of task ids
     * @param int[] $ids
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
        $myTasks = CronLock::model()->findAllByAttributes(array('hostname' => gethostname()));
        /** @var CronLock[] $myTasks */
        foreach ($myTasks as $task) {
            $processStartedTime = @filectime('/proc/' . $task->pid);
            if (!$processStartedTime) {
                list($null, $ruleId) = explode('-', $task->id);
                $this->postProcessing($ruleId);
                $task->delete();
                continue;
            } elseif ($processStartedTime + $this->longRunningTimeout < time()) {
                Yii::log("task $task->id freezed", CLogger::LEVEL_WARNING, 'cron');
            }
            $task->lastActivity = time();
            if (!$task->save())
                throw new Exception('can\'t save task: '.print_r($task->errors, true));
        }

        $this->releaseExpiredTasks();

        $this->beginWork();
    }

    public function releaseExpiredTasks()
    {
        $expiredTasks = CronLock::model()->findAll(
            "lastActivity <= UNIX_TIMESTAMP() - :changeOwnerTimeout",
            array(
                ':changeOwnerTimeout' => $this->changeOwnerTimeout,
            )
        );
        /** @var CronLock[] $expiredTasks */
        foreach ($expiredTasks as $task) {
            try {
                if (!$task->delete())
                    throw new Exception('can\'t delete task: '.print_r($task->attributes, true));
                Yii::log(
                    "task {$task->id}({$task->hostname}, last activity at {$task->lastActivity}) lock was released",
                    CLogger::LEVEL_WARNING,
                    'cron'
                );
            } catch (CDbException $e) {
            }
        }
    }

    protected function beginWork()
    {
        $queuedTaskIds = array();
        $busyIds = EHtml::listData(CronLock::model());
        foreach ($this->getAvailableTaskIds($busyIds) as $taskId) {
            $task = new CronLock();
            $task->hostname = gethostname();
            $task->id = $taskId;
            try {
                if (!$task->save())
                    throw new Exception('can\'t save task: '.print_r($task->errors, true));
                $queuedTaskIds[] = $taskId;
            } catch (CDbException $e) {
                Yii::log("failed lock $task->id id", CLogger::LEVEL_INFO, 'cron');
                continue;
            }
            if (count($queuedTaskIds) == $this->aggregateTasks) {
                $this->startTasks($queuedTaskIds);
                $queuedTaskIds = array();
            }
        }

        if (!empty($queuedTaskIds))
            $this->startTasks($queuedTaskIds);
    }

    /**
     * @param string[] $busyTaskIds
     * @return string[]
     */
    protected function getAvailableTaskIds($busyTaskIds)
    {
        $busyIds = $this->TaskIdsToIds($busyTaskIds);

        $availableIds = $this->getAvailableIds($busyIds);

        $availableTaskIds = $this->idsToTaskIds($availableIds);

        return $availableTaskIds;
    }

    /**
     * @param string[] $taskIds
     * @throws CException
     */
    protected function startTasks($taskIds)
    {
        $pid = $this->startProcess( $this->TaskIdsToIds($taskIds) );

        $c = new CDbCriteria();
        $c->addInCondition('id', $taskIds);
        CronLock::model()->updateAll(
            array(
                'pid' => $pid,
                'lastActivity' => time()
            ),
            $c
        );
    }

    /**
     * @param string[] $taskIds
     * @return int[]
     */
    protected function TaskIdsToIds($taskIds)
    {
        $ids = array();
        foreach ($taskIds as $taskId) {
            list($null, $ruleId) = explode('-', $taskId);
            $ids[] = $ruleId;
        }
        return $ids;
    }

    /**
     * @param $ids
     * @return array
     */
    protected function idsToTaskIds($ids)
    {
        $taskIds = array();
        foreach ($ids as $ruleId) {
            $taskIds[] = $this->getTaskPrefix() . '-' . $ruleId;
        }
        return $taskIds;
    }
}