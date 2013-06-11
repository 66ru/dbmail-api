<?php

class GccTestCommand extends GlobalConsoleCommand
{
    public $aggregateTasks = 2;
    public $longRunningTimeout = 660; // 11m
    public $changeOwnerTimeout = 120; // 2m

    public function getTaskName()
    {
        return 'gcctest';
    }

    /**
     * @param int[] $busyIds
     * @return int[]
     */
    public function getAvailableIds($busyIds)
    {
        $allIds = array(0,1,2,3,4,5,6,7,8,9);
        return array_diff($allIds, $busyIds);
    }

    /**
     * @param int[] $ids
     * @return int pid of started process
     */
    protected function startProcess($ids)
    {
        $cmd = array();
        foreach ($ids as $id) {
            $sleepSeconds = $id + 1;
            $cmd []= "echo \"+ {$id} `date`\" >> /tmp/gcc.txt && sleep {$sleepSeconds}m && echo \"- {$id} `date`\" >> /tmp/gcc.txt ";
        }
        $cmd = implode(' && ', $cmd);

        $pid = exec("($cmd) >/dev/null 2>&1 & echo $!");
        if (!$pid) {
            throw new CException('can\'t get pid of executed process');
        }

        return $pid;
    }

    /**
     * @param int $id
     */
    public function postProcessing($id)
    {
        exec("echo \"= {$id} `date`\" >> /tmp/gcc.txt");
    }
}