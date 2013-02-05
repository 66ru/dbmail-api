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
        if (!$newScript) {
            throw new DBMailClientException('Произошла ошибка при создании фильтра');
        }
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

//    public function actionGetFolders()
//    {
//        if (empty($_POST['userName']))
//            $this->sendAnswer(array('status' => 'error', 'error' => 'wrong input'));
//
//        $user = $this->getUserModel();
//        $this->sendAnswer(array('status' => 'ok', 'rules' => array_keys(CHtml::listData($user->mailboxes, 'name', 'name'))));
//    }
//
//    public function actionCreateFolder()
//    {
//        if (empty($_POST['userName']) || empty($_POST['folderName']))
//            $this->sendAnswer(array('status' => 'error', 'error' => 'wrong input'));
//
//        $user = $this->getUserModel();
//        $mailbox = new Mailbox();
//        $mailbox->name = $_POST['folderName'];
//        $mailbox->owner_idnr = $user->user_idnr;
//        if (!$mailbox->save())
//            $this->sendAnswer(array('status' => 'error', 'error' => print_r($mailbox->getErrors(), true)));
//
//        $this->sendAnswer(array('status' => 'ok'));
//    }
//
//    public function actionDeleteFolder()
//    {
//        if (empty($_POST['userName']) || empty($_POST['folderName']))
//            $this->sendAnswer(array('status' => 'error', 'error' => 'wrong input'));
//
//        $user = $this->getUserModel();
//        if (!Mailbox::model()->deleteAllByAttributes(array('name' => $_POST['folderName'], 'owner_idnr' => $user->user_idnr)))
//            $this->sendAnswer(array('status' => 'error', 'error' => 'mailbox not found'));
//
//        $this->sendAnswer(array('status' => 'ok'));
//    }
//
//    /**
//     * @return User
//     */
//    protected function getUserModel()
//    {
//        $user = User::model()->findByName($_POST['userName']);
//        if (empty($user))
//            $this->sendAnswer(array('status' => 'error', 'error' => 'user not found'));
//
//        return $user;
//    }

    public function actionError()
    {
        if ($error = Yii::app()->errorHandler->error) {
            $this->sendAnswer(array('status' => 'error', 'message' => $error['message']));
        } else {
            throw new CHttpException(404);
        }
    }
}