<?php

$action = !empty($_POST['action']) ? $_POST['action'] : null;
$validActions = array('createRule', 'deleteRule', 'getRules');
if (!in_array($action, $validActions))
    throw new Exception('wrong action');

$userName = !empty($_POST['userName']) ? $_POST['userName'] : null;
$ruleName = !empty($_POST['ruleName']) ? $_POST['ruleName'] : null;
$rules = !empty($_POST['rules']) ? $_POST['rules'] : null;
$ruleActions = !empty($_POST['actions']) ? $_POST['actions'] : null;

$sc = new SieveClient();
if ($action == 'createRule') {
    $oldScript = $sc->getScript($userName);
    $newScript = SieveCreator::generateSieveScript($ruleName, $rules, $action);
    $newScript = SieveCreator::mergeScripts($oldScript, $newScript);
    $sc->writeScript($userName, $newScript);

    echo 'ok';
} else if ($action == 'deleteRule') {
//    $script = $sc->getScript($userName);

} else if ($action == 'getRules') {
}