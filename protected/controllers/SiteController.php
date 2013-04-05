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

    public function actionCreateUser()
    {
        $this->checkRequiredFields(array('userName', 'password'));

        $this->dbMailClient->createUser($_POST['userName'], $_POST['password']);
        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionChangePassword()
    {
        $this->checkRequiredFields(array('userName', 'password'));

        $this->dbMailClient->changePassword($_POST['userName'], $_POST['password']);
        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionDeleteUser()
    {
        $this->checkRequiredFields(array('userName'));

        $this->dbMailClient->deleteUser($_POST['userName']);
        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionGetRules()
    {
        $this->checkRequiredFields(array('userName'));

        $script = $this->dbMailClient->getScript($_POST['userName']);
        preg_match_all('/^#rule=(.*?)$/m', $script, $matches);

        $this->sendAnswer(array('status' => 'ok', 'rules' => $matches[1]));
    }

    public function actionGetUnreadCount()
    {
        $this->checkRequiredFields(array('userName'));

        /** @var CDbConnection $db */
        $db = Yii::app()->db;
        $unreadCount = $db->createCommand(
            "SELECT count(*)
            FROM dbmail_messages M
            JOIN dbmail_mailboxes MB ON M.`mailbox_idnr` = MB.`mailbox_idnr`
            JOIN dbmail_users U ON MB.`owner_idnr` = U.`user_idnr` AND U.`userid` = :username
            WHERE M.`seen_flag` = 0"
        )->queryScalar(
                array(
                    ':username' => $_POST['userName'],
                )
            );

        $this->sendAnswer(array('status' => 'ok', 'unreadCount' => $unreadCount));
    }

    public function actionAddGetMailRule()
    {
        $this->checkRequiredFields(array('userName', 'host', 'email', 'password'));

        if (!isset($_POST['delete']))
            $_POST['delete'] = false;

        $ruleName = GetmailHelper::getRuleName(
            $_POST['host'],
            $_POST['email'],
            $_POST['password'],
            $_POST['userName']
        );
        $config = GetmailHelper::getConfig(
            $_POST['host'],
            $_POST['email'],
            $_POST['password'],
            $_POST['userName'],
            $_POST['delete']
        );
        if (!$config) {
            $this->sendAnswer(array('status' => 'error', 'error' => 'error while creating getmail config'));
        }
        $ruleFileName = GetmailHelper::getFileName($ruleName);
        $ruleDir = pathinfo($ruleFileName, PATHINFO_DIRNAME);
        if (!file_exists($ruleDir))
            mkdir($ruleDir, 0777, true);
        $ret = file_put_contents($ruleFileName, $config);
        if (!$ret) {
            $this->sendAnswer(array('status' => 'error', 'error' => 'error while writing getmail config'));
        }

        $this->sendAnswer(
            array(
                'status' => 'ok',
                'ruleName' => $ruleName
            )
        );
    }

    public function actionRemoveGetMailRule()
    {
        $this->checkRequiredFields(array('ruleName'));

        $filename = GetmailHelper::getFileName($_POST['ruleName']);
        $logFilename = GetmailHelper::getLogFileName($_POST['ruleName']);
        if (file_exists($filename)) {
            if (!unlink($filename)) {
                $this->sendAnswer(array('status' => 'error', 'error' => 'error while removing rule'));
            }
            if (file_exists($logFilename)) {
                unlink($logFilename);
            }
        } else {
            $this->sendAnswer(array('status' => 'error', 'error' => 'rule not found'));
        }

        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionListGetMailRules()
    {
        $this->checkRequiredFields(array('userName'));

        $startDir = GetmailHelper::getConfigsDir() . GetmailHelper::getIntermediatePath($_POST['userName'].'-null');

        $rules = array();
        $files = glob($startDir.$_POST['userName'].'-*');
        array_filter($files, function($value) use (&$rules, $startDir) {
            if (strpos($value, '.log') === false) {
                $ruleName = substr($value, strlen($startDir));
                $rules[$ruleName] = GetmailHelper::getRuleStatus($ruleName);
            }
        });

        $this->sendAnswer(array('status' => 'ok', 'rules' => $rules));
    }

    public function checkRequiredFields($requiredFields)
    {
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new CHttpException(400, 'wrong input');
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