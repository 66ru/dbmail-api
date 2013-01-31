<?php

class SiteController extends Controller
{
    /** @var DBMailClient */
    public $dbMailClient;

    public function init()
    {
        parent::init();

        $this->dbMailClient = Yii::app()->dbmail;
    }

    public function actionCreateRule()
    {
        $rules = @json_decode($_POST['rules'], true);
        $actions = @json_decode($_POST['actions'], true);

        if (empty($_POST['userName']) || empty($_POST['ruleName']) || empty($rules) || empty($actions))
            $this->sendAnswer(array('status' => 'error', 'error' => 'wrong input'));

        $userName = $_POST['userName'];

        $oldScript = $this->dbMailClient->getScript($userName);
        $newScript = SieveCreator::generateSieveScript($_POST['ruleName'], $rules, $actions);
        $newScript = SieveCreator::mergeScripts($oldScript, $newScript);
        $this->dbMailClient->writeScript($userName, $newScript);

        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionDeleteRule()
    {
        if (empty($_POST['userName']) || empty($_POST['ruleName']))
            $this->sendAnswer(array('status' => 'error', 'error' => 'wrong input'));

        $userName = $_POST['userName'];

        $script = $this->dbMailClient->getScript($userName);
        $script = SieveCreator::removeRule($_POST['ruleName'], $script);
        $this->dbMailClient->writeScript($userName, $script);

        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionGetRules()
    {
        if (empty($_POST['userName']))
            $this->sendAnswer(array('status' => 'error', 'error' => 'wrong input'));

        $script = $this->dbMailClient->getScript($_POST['userName']);
        preg_match_all('/^#rule=(.*?)$/m', $script, $matches);

        $this->sendAnswer(array('status' => 'ok', 'rules' => $matches[1]));
    }

    public function actionError()
    {
        if ($error = Yii::app()->errorHandler->error) {
            $this->sendAnswer(array('status' => 'error', 'message' => $error['message']));
        } else {
            throw new CHttpException(404);
        }
    }
}