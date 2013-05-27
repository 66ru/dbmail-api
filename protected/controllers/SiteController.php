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

        $mailBoxType = GetmailHelper::determineMailboxType(
            $_POST['host'],
            $_POST['email'],
            $_POST['password']
        );
        $rule = new GetMailRule();
        $rule->host = $_POST['host'];
        $rule->email = $_POST['email'];
        $rule->password = $_POST['password'];
        $rule->dbMailUserName = $_POST['userName'];
        $rule->delete = $_POST['delete'];
        $rule->ssl = $mailBoxType == GetmailHelper::POP3_SSL;
        if (!$rule->save()) {
            throw new CException('error while saving rule');
        }

        $this->sendAnswer(
            array(
                'status' => 'ok',
                'ruleName' => $rule->id
            )
        );
    }

    public function actionRemoveGetMailRule()
    {
        $this->checkRequiredFields(array('ruleName'));

        $rule = GetMailRule::model()->findByPk($_POST['ruleName']);
        /** @var GetMailRule $rule */
        if (!empty($rule)) {
            if (!$rule->delete()) {
                $this->sendAnswer(array('status' => 'error', 'error' => 'error while removing rule'));
            }
        } else {
            $this->sendAnswer(array('status' => 'error', 'error' => 'rule not found'));
        }

        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionListGetMailRules()
    {
        $this->checkRequiredFields(array('userName'));

        $rules = GetMailRule::model()->findByAttributes(array('dbMailUserName' => $_POST['userName']));
        /** @var GetMailRule[] $rules */
        foreach ($rules as $rule) {
            $rules[$rule->id] = $rule->status;
        }

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

    public function actionError()
    {
        if ($error = Yii::app()->errorHandler->error) {
            $this->sendAnswer(array('status' => 'error', 'message' => $error['message']));
        } else {
            throw new CHttpException(404);
        }
    }
}