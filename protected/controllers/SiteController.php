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

        if (empty($_POST['userName']) || empty($rules) || empty($actions))
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

    }

    public function actionGetRules()
    {

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