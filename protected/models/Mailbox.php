<?php

/**
 * Class Mailbox
 *
 * @property int mailbox_idnr
 * @property int owner_idnr
 * @property string name
 */
class Mailbox extends CActiveRecord
{
    /**
     * @param string $className
     * @return Mailbox
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'mailboxes';
    }

    public function rules()
    {
        return array(
            array('name', 'length', 'min' => 0, 'max' => 255, 'allowEmpty' => false),
            array('owner_idnr', 'numerical', 'integerOnly' => true, 'min' => 0),
        );
    }
}
