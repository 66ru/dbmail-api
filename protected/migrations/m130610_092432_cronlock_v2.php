<?php

class m130610_092432_cronlock_v2 extends CDbMigration
{
    public function getDbConnection()
    {
        return Yii::app()->getComponent('getmaildb');
    }

	public function up()
	{
        $this->dropTable('CronLock');
        $this->execute("CREATE TABLE `CronLock` (
  `taskName` varchar(255) NOT NULL DEFAULT '',
  `taskId` int(11) NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `lastActivity` datetime DEFAULT NULL,
  `pid` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`taskName`,`taskId`),
  KEY `hostname` (`hostname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	}

	public function down()
	{
        $this->dropTable('CronLock');
        $this->execute("CREATE TABLE `CronLock` (
  `id` varchar(255) NOT NULL DEFAULT '',
  `hostname` varchar(255) NOT NULL,
  `lastActivity` int(11) DEFAULT NULL,
  `pid` int(11) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	}
}