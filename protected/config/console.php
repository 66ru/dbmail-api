<?php

Yii::setPathOfAlias('lib', realpath(__DIR__ . '/../../lib'));

$params = require(__DIR__ . '/params.php');

$logRoutes = array();
$logRoutes[] = array(
    'class' => 'CFileLogRoute',
    'levels' => 'error,warning',
);
$logRoutes[] = array(
    'class'=>'lib.sentry-log.RSentryLog',
    'levels'=>'error, warning',
    'except' => 'exception.*, php',
    'dsn' => $params['sentryDSN'],
);

return array(
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'DBMail client',

    'preload' => array('log', 'RSentryException'),

    'import' => array(
        'application.helpers.*',
        'application.models.*',
        'application.components.*',
    ),

    'components' => array(
        'RSentryException' => array(
            'dsn' => $params['sentryDSN'],
            'class' => 'ESentryComponent',
        ),
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
            'connectionString' => "mysql:host={$params['dbMailHost']};dbname=getmail",
            'emulatePrepare' => true,
            'username' => $params['dbMailUser'],
            'password' => $params['dbMailPassword'],
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

    'params' => require(__DIR__ . '/params.php'),
);