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
        $userName = $_POST['userName'];

        $oldScript = $this->dbMailClient->getScript($userName);
        $newScript = SieveCreator::generateSieveScript($_POST['ruleName'], $_POST['rules'], $_POST['actions']);
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