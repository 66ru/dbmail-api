<?php

/**
 * Class CronLock
 *
 * @property string $id unique task id
 * @property string $hostname task owner node's hostname
 * @property int $lastActivity timestamp of last detected process activity
 * @property int $pid
 */
class CronLock extends CActiveRecord
{
    /**
     * @param string $className
     * @return CronLock
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function getDbConnection()
    {
        return Yii::app()->getComponent('getmaildb');
    }

    public function rules()
    {
        return array(
            array('id, hostname', 'length', 'max' => 255, 'allowEmpty' => false),
            array('lastActivity, pid', 'numerical', 'integerOnly' => true, 'min' => 0),
        );
    }
}