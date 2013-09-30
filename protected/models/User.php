<?php

/**
 * Class User
 *
 * @property int user_idnr
 * @property string userid
 *
 * @property Mailbox[] mailboxes
 */
class User extends CActiveRecord
{
    public function init()
    {
        parent::init();

        $this->onBeforeSave = function ($event) {
            /** @var CModelEvent $event */
            $event->isValid = !$this->isNewRecord;
        };
    }

    /**
     * @param string $className
     * @return User
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'dbmail_users';
    }

    public function relations()
    {
        return array(
            'mailboxes' => array(self::HAS_MANY, 'Mailbox', 'owner_idnr'),
        );
    }

    /**
     * @param $userName
     * @return User
     */
    public function findByName($userName)
    {
        return $this->findByAttributes(array('userid' => $userName));
    }
}
