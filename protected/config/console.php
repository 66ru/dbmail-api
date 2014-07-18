<?php

Yii::setPathOfAlias('lib', realpath(__DIR__ . '/../../lib'));
Yii::setPathOfAlias('vendor', realpath(__DIR__ . '/../../vendor'));

$params = require(__DIR__ . '/params.php');

$components = array();
$logRoutes = array(
    array(
        'class' => 'CFileLogRoute',
        'logFile' => 'console.log',
        'levels' => 'error,warning',
    ),
);
if ($params['useSentry']) {
    $logRoutes[] = array(
        'class'=>'vendor.m8rge.yii-sentry-log.RSentryLog',
        'levels'=>'error, warning',
        'except' => 'exception.*, system.db.CDbCommand',
        'dsn' => $params['sentryDSN'],
    );
    $components['RSentryException'] = array(
        'dsn' => $params['sentryDSN'],
        'class' => 'vendor.m8rge.yii-sentry-log.RSentryComponent',
    );
}

return array(
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'DBMail client',

    'preload' => array('log', 'RSentryException'),

    'import' => array(
        'application.helpers.*',
        'application.models.*',
        'application.components.*',
    ),

    'components' => array_merge(
        array(
            'db' => array(
                'class' => 'CDbConnection',
                'connectionString' => "mysql:host={$params['dbMailHost']};dbname=dbmail",
                'emulatePrepare' => true,
                'username' => $params['dbMailUser'],
                'password' => $params['dbMailPassword'],
                'charset' => 'utf8',
                'tablePrefix' => 'dbmail_',
            ),
            'getmaildb' => array(
                'class' => 'CDbConnection',
                'connectionString' => "mysql:host={$params['getMailHost']};dbname=getmail",
                'emulatePrepare' => true,
                'username' => $params['getMailUser'],
                'password' => $params['getMailPassword'],
                'charset' => 'utf8',
            ),
            'dbmail' => array(
                'class' => 'application.components.DBMailClient'
            ),
            'log' => array(
                'class' => 'CLogRouter',
                'routes' => $logRoutes,
            ),
        ),
        $components
    ),
    'commandMap' => array(
        'migrate' => array(
            'class' => 'lib.yii.framework.cli.commands.MigrateCommand',
            'migrationTable' => 'migration',
            'connectionID' => 'getmaildb',
        ),
    ),
    'params' => require(__DIR__ . '/params.php'),
);