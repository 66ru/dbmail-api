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
     * @var string table name with tasks lock information
     */
    public $tableName = 'CronLock';

    /**
     * @return CDbConnection Database connection.
     */
    public function getDbConnection()
    {
        return Yii::app()->getComponent('db');
    }

    /**
     * @return CDbCommandBuilder
     */
    public function getCommandBuilder()
    {
        return $this->getDbConnection()->getCommandBuilder();
    }

    /**
     * @return string
     */
    public function getTaskName()
    {
        return 'taskName';
    }

    /**
     * @param int[] $busyIds
     * @return int[] returns task ids available to processing
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
        $myTasks = $this->findTasks(array('hostname' => gethostname()));

        foreach ($myTasks as $task) {
            $processStartedTime = @filectime('/proc/' . $task['pid']); //todo: check pid to NULL
            if (!$processStartedTime) {
                $this->postProcessing($task['taskId']); //todo: if this failed -> we do not delete task (
                $this->removeTask($task['taskName'], $task['taskId']);
                continue;
            } elseif ($processStartedTime + $this->longRunningTimeout < time()) {
                Yii::log("task {$task['id']} freezed", CLogger::LEVEL_WARNING, 'cron');
            }

            $this->commandBuilder->createUpdateCommand(
                $this->tableName,
                array(
                    'lastActivity' => new CDbExpression('NOW()'),
                ),
                $this->createTaskCriteria($task['taskName'], $task['taskId'])
            )->execute();
        }

        $this->releaseExpiredTasks();

        $this->beginWork();
    }

    public function releaseExpiredTasks()
    {
        $expiredTasks = $this->findTasks(
            array(
                'condition' => 'lastActivity <= NOW() - FROM_UNIXTIME(:changeOwnerTimeout)',
                'params' => array(
                    ':changeOwnerTimeout' => $this->changeOwnerTimeout,
                )
            )
        );

        foreach ($expiredTasks as $task) {
            try {
                $this->removeTask($task['taskName'], $task['taskId']);
                Yii::log(
                    "task {$task['id']}({$task['hostname']}, " .
                    "last activity at {$task['lastActivity']}) lock was released by " . gethostname(),
                    CLogger::LEVEL_WARNING,
                    'cron'
                );
            } catch (CDbException $e) {
                continue;
            }
        }
    }

    protected function beginWork()
    {
        $queuedTaskIds = array();

        $busyIds = $this->findTasks(array('taskName' => $this->taskName));
        foreach ($this->getAvailableIds($busyIds) as $taskId) {
            try {
                $this->commandBuilder->createInsertCommand(
                    $this->tableName,
                    array(
                        'hostname' => gethostname(),
                        'taskName' => $this->taskName,
                        'taskId' => $taskId,
                    )
                )->execute();
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
     * @param string[] $taskIds
     * @throws CException
     */
    protected function startTasks($taskIds)
    {
        $pid = $this->startProcess($taskIds);

        $this->commandBuilder->createUpdateCommand(
            $this->tableName,
            array(
                'pid' => $pid,
                'lastActivity' => new CDbExpression('NOW()'),
            ),
            $this->createTaskCriteria($this->taskName, $taskIds)
        )->execute();
    }

    /**
     * @param string $taskName
     * @param int $taskId
     */
    public function removeTask($taskName, $taskId)
    {
        $this->commandBuilder->createDeleteCommand(
            $this->tableName,
            $this->createTaskCriteria($taskName, $taskId)
        )->execute();
    }

    /**
     * @param string $taskName
     * @param int|int[] $taskId
     * @return CDbCriteria
     */
    public function createTaskCriteria($taskName, $taskId)
    {
        return $this->commandBuilder->createColumnCriteria(
            $this->tableName,
            array(
                'taskName' => $taskName,
                'taskId' => $taskId,
            )
        );
    }

    /**
     * @param array $columns Columns parameter for CDbCommandBuilder::createColumnCriteria
     * @return array
     */
    public function findTasks($columns)
    {
        $criteria = $this->commandBuilder->createColumnCriteria($this->tableName, $columns);
        $tasks = $this->commandBuilder->createFindCommand($this->tableName, $criteria)->queryAll();

        return $tasks;
    }
}