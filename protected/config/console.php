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

    // preloading 'log' component
    'preload' => array('log'),

    // application components
    'components' => array(
        'db' => array(
            'connectionString' => 'sqlite:' . dirname(__FILE__) . '/../data/testdrive.db',
        ),
//        'db' => array(
//            'class' => 'CDbConnection',
//            'connectionString' => 'mysql:host=127.0.0.1;dbname=ekabu',
//            'emulatePrepare' => true,
//            'username' => 'root',
//            'password' => '123',
//            'charset' => 'utf8',
//        ),
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