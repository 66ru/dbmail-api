<?php

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

    'import' => array(
        'application.helpers.*',
        'application.models.*',
        'application.components.*',
    ),

    // preloading 'log' component
    'preload' => array('log'),

    // application components
    'components' => array(
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