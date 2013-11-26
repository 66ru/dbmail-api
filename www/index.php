<?php

require_once(__DIR__ . '/../vendor/autoload.php');

// change the following paths if necessary
$yii = __DIR__ . '/../lib/yii/framework/yii.php';
$config = __DIR__ . '/../protected/config/main.php';

$params = require(__DIR__ . '/../protected/config/params.php');
defined('YII_DEBUG') or define('YII_DEBUG', $params['debug']);
// specify how many levels of call stack should be shown in each log message
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL', 3);

require_once($yii);
Yii::createWebApplication($config)->run();
