<?php

class m141003_102908_migrate_users extends CDbMigration
{
	public function getDbConnection()
    {
        return Yii::app()->getComponent('db');
    }

	public function up()
	{
		$this->addColumn('dbmail_users', 'migrated', 'TINYINT(1)', 'NULL');
        $this->createIndex('migrated', 'dbmail_users', 'migrated');
	}

	public function down()
	{
		$this->dropColumn('dbmail_users', 'migrated');
	}
}