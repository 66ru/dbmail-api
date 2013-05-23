<?php

abstract class GlobalConsoleCommand extends CConsoleCommand
{
    /**
     * @var int ids count to pass to startProcess method
     */
    public $aggregateTasks = 1;

    /**
     * @var int seconds
     */
    public $processFreezedAlarmTimeout = 3600;

    /**
     * @var int seconds
     */
    public $changeOwnerTimeout = 600;

    /**
     * @return string
     */
    public function getJobPrefix()
    {
        return 'prefix';
    }

    /**
     * @param int[] $busyIds
     * @return int[]
     */
    public function getAvailableIds($busyIds)
    {
        throw new Exception(__METHOD__ . ' must be implemented');
    }

    /**
     * @param int[] $ids
     * @return int pid of started process
     */
    protected function startProcess($ids)
    {
        throw new Exception(__METHOD__ . ' must be implemented');
    }

    /**
     * @param int $id
     */
    public function postProcessing($id)
    {
    }

    public function actionIndex()
    {
        $myJobs = CronLock::model()->findAllByAttributes(array('hostname' => gethostname()));
        /** @var CronLock[] $myJobs */
        foreach ($myJobs as $job) {
            $processStartedTime = filectime('/proc/' . $job->pid);
            if (!$processStartedTime) {
                list($null, $ruleId) = explode('-', $job->id);
                $this->postProcessing($ruleId);
                $job->delete();
                continue;
            } elseif ($processStartedTime + $this->processFreezedAlarmTimeout < time()) {
                Yii::log("job $job->id freezed", CLogger::LEVEL_WARNING, 'cron');
            }
            $job->lastActivity = time();
            $job->save();
        }

        $this->releaseExpiredJobs();

        $this->beginWork();
    }

    public function releaseExpiredJobs()
    {
        $expiredJobs = CronLock::model()->findAll(
            "lastActivity < UNIX_TIMESTAMP() - :changeOwnerTimeout",
            array(
                ':changeOwnerTimeout' => $this->changeOwnerTimeout,
            )
        );
        /** @var CronLock[] $expiredJobs */
        foreach ($expiredJobs as $job) {
            try {
                $job->delete();
                Yii::log(
                    "lock on job {$job->id}({$job->hostname}, last activity at {$job->lastActivity}) was released",
                    CLogger::LEVEL_WARNING,
                    'cron'
                );
            } catch (CDbException $e) {
            }
        }
    }

    protected function beginWork()
    {
        $queuedJobIds = array();
        $busyIds = EHtml::listData(CronLock::model());
        foreach ($this->getAvailableJobIds($busyIds) as $jobId) {
            $lock = new CronLock();
            $lock->hostname = gethostname();
            $lock->id = $jobId;
            try {
                $lock->save();
                $queuedJobIds[] = $jobId;
            } catch (CDbException $e) {
                Yii::log("failed lock $lock->id id", CLogger::LEVEL_INFO, 'cron');
                continue;
            }
            if (count($queuedJobIds) == $this->aggregateTasks) {
                $this->startJobs($queuedJobIds);
                $queuedJobIds = array();
            }
        }

        if (!empty($queuedJobIds))
            $this->startJobs($queuedJobIds);
    }

    /**
     * @param string[] $busyJobIds
     * @return string[]
     */
    protected function getAvailableJobIds($busyJobIds)
    {
        $busyIds = $this->jobIdsToIds($busyJobIds);

        $availableIds = $this->getAvailableIds($busyIds);

        $availableJobIds = $this->idsToJobIds($availableIds);

        return $availableJobIds;
    }

    /**
     * @param string[] $jobIds
     * @throws CException
     */
    protected function startJobs($jobIds)
    {
        $pid = $this->startProcess( $this->jobIdsToIds($jobIds) );

        $c = new CDbCriteria();
        $c->addInCondition('id', $jobIds);
        CronLock::model()->updateAll(
            array(
                'pid' => $pid,
                'lastActivity' => time()
            ),
            $c
        );
    }

    /**
     * @param string[] $jobIds
     * @return int[]
     */
    protected function jobIdsToIds($jobIds)
    {
        $ids = array();
        foreach ($jobIds as $jobId) {
            list($null, $ruleId) = explode('-', $jobId);
            $ids[] = $ruleId;
        }
        return $ids;
    }

    /**
     * @param $ids
     * @return array
     */
    protected function idsToJobIds($ids)
    {
        $jobIds = array();
        foreach ($ids as $ruleId) {
            $jobIds[] = $this->getJobPrefix() . '-' . $ruleId;
        }
        return $jobIds;
    }
}