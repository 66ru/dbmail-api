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
        $_POST['rules'] = @json_decode($_POST['rules'], true);
        $_POST['actions'] = @json_decode($_POST['actions'], true);

        $this->checkRequiredFields(array('userName', 'ruleName', 'rules', 'actions'));

        $oldScript = $this->dbMailClient->getScript($_POST['userName']);
        $newScript = SieveCreator::generateSieveScript($_POST['ruleName'], $_POST['rules'], $_POST['actions']);
        if (!$newScript) {
            throw new DBMailClientException('Произошла ошибка при создании фильтра');
        }
        $newScript = SieveCreator::mergeScripts($oldScript, $newScript);
        $this->dbMailClient->writeScript($_POST['userName'], $newScript);

        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionDeleteRule()
    {
        $this->checkRequiredFields(array('userName', 'ruleName'));

        $script = $this->dbMailClient->getScript($_POST['userName']);
        $script = SieveCreator::removeRule($_POST['ruleName'], $script);
        $this->dbMailClient->writeScript($_POST['userName'], $script);

        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionGetRules()
    {
        $this->checkRequiredFields(array('userName'));

        $script = $this->dbMailClient->getScript($_POST['userName']);
        preg_match_all('/^#rule=(.*?)$/m', $script, $matches);

        $this->sendAnswer(array('status' => 'ok', 'rules' => $matches[1]));
    }

    public function actionAddGetMailRule()
    {
        $this->checkRequiredFields(array('userName', 'host', 'email', 'password', 'delete'));

        $config = GetmailCreator::getConfig($_POST['host'], $_POST['email'], $_POST['password'], $_POST['userName'], $_POST['delete']);
        if (!$config) {
            $this->sendAnswer(array('status' => 'error', 'error' => 'error while creating getmail config'));
        }
        $ret = file_put_contents(
            GetmailCreator::getRuleFileName($_POST['host'], $_POST['email'], $_POST['password'], $_POST['userName']),
            $config
        );
        if (!$ret) {
            $this->sendAnswer(array('status' => 'error', 'error' => 'error while writing getmail config'));
        }

        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionRemoveGetMailRule()
    {
        $this->checkRequiredFields(array('ruleName'));

        $filename = GetmailCreator::getFileNameByRule($_POST['ruleName']);
        if (file_exists($filename)) {
            if (!unlink($filename)) {
                $this->sendAnswer(array('status' => 'error', 'error' => 'error while removing rule'));
            }
        } else {
            $this->sendAnswer(array('status' => 'error', 'error' => 'rule not found'));
        }

        $this->sendAnswer(array('status' => 'ok'));
    }

    public function checkRequiredFields($requiredFields)
    {
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $this->sendAnswer(array('status' => 'error', 'error' => 'wrong input'));
            }
        }
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