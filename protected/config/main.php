<?php

// uncomment the following to define a path alias
// Yii::setPathOfAlias('local','path/to/local-folder');

$params = require(__DIR__ . '/params.php');

$logRoutes = array();
$logRoutes[] = array(
    'class' => 'CFileLogRoute',
    'levels' => 'error,warning',
);
if (!$params['debug'])
    $logRoutes[] = array(
        'class' => 'CEmailLogRoute',
        'levels' => 'error, warning',
        'emails' => $params['errorEmails'],
        'utf8' => true,
    );

return array(
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'DBMail client',

    'preload' => array('log'),

    'import' => array(
        'application.helpers.*',
        'application.models.*',
        'application.components.*',
    ),

    'components' => array(
        'urlManager' => array(
            'urlFormat' => 'path',
            'rules' => array(
                '<action:\w+>' => 'site/<action>',
            ),
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
        'errorHandler' => array(
            'errorAction' => 'site/error',
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