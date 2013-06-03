<?php

class GrabMailCommand extends GlobalConsoleCommand
{
    public $aggregateTasks = 30;
    public $longRunningTimeout = 7200; // 2h
    public $changeOwnerTimeout = 300; // 5m

    /**
     * @return string
     */
    public function getTaskPrefix()
    {
        return 'getmail';
    }

    /**
     * @param int[] $busyIds
     * @return int[]
     */
    public function getAvailableIds($busyIds)
    {
        $model = new GetMailRule;
        $model->dbCriteria->addNotInCondition('id', $busyIds);
        return EHtml::listData($model);
    }

    /**
     * @param int[] $ids
     * @return int pid of started process
     */
    protected function startProcess($ids)
    {
        $rules = GetMailRule::model();
        $rules->dbCriteria->addInCondition('id', $ids);
        $rules->findAll();

        /** @var GetMailRule[] $rules */
        $cmd = Yii::app()->params['getmail'];
        foreach ($rules as $rule) {
            $ruleFileName = $rule->getRuleFileName();
            file_put_contents($ruleFileName, $rule->getConfig());
            $cmd .= ' --rcfile ' . $ruleFileName;
        }

        $pid = exec($cmd . ' >/dev/null 2>&1 & echo $!');
        if (!$pid) {
            throw new CException('can\'t get pid of executed getmail process');
        }

        return $pid;
    }

    /**
     * @param int $id
     */
    public function postProcessing($id)
    {
        /** @var GetMailRule $rule */
        $rule = GetMailRule::model()->findByPk($id);

        $rule->status = $rule->getRuleStatus();
        $rule->save();
    }

}