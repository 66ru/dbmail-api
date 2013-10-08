<?php

class SiteController extends Controller
{
    /** @var DBMailClient */
    public $dbMailClient;

    public function init()
    {
        parent::init();

        if (empty($_POST['secKey']) || $_POST['secKey']!= '&k3]E1v39"okbg2') {
            throw new CHttpException(403);
        }

        $this->dbMailClient = Yii::app()->dbmail;
    }

    public function actionCreateRule()
    {
        $_POST['rules'] = @json_decode($_POST['rules'], true);
        $_POST['actions'] = @json_decode($_POST['actions'], true);

        $this->checkRequiredFields(array('userName', 'ruleName', 'rules', 'actions'));

        $rulesJoinOperator = !empty($_POST['rulesJoinOperator']) ? $_POST['rulesJoinOperator'] : 'and';
        $disabled = !empty($_POST['disabled']) ? (bool)$_POST['disabled'] : false;
        $oldScript = $this->dbMailClient->getScript($_POST['userName']);
        $newScript = SieveCreator::generateSieveScript($_POST['ruleName'], $rulesJoinOperator, $_POST['rules'], $_POST['actions'], $disabled);
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

    public function actionIsUserPresent()
    {
        $this->checkRequiredFields(array('userName'));

        $found = User::model()->countByAttributes(array('userid' => $_POST['userName']));
        $this->sendAnswer(array('status' => 'ok', 'found' => (bool)$found));
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

    public function actionTruncateUser()
    {
        $this->checkRequiredFields(array('userName'));

        $this->dbMailClient->truncateUser($_POST['userName']);
        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionGetRules()
    {
        $this->checkRequiredFields(array('userName'));

        $rulesArray = array();
        $script = $this->dbMailClient->getScript($_POST['userName']);
        preg_match_all('/^#rule=.+?\n\n/ms', $script, $matches);
        foreach ($matches[0] as $fullRule) {
            preg_match('/#rule=(.+)/', $fullRule, $ruleName);
            $ruleName = $ruleName[1];
            preg_match('/#rules=(.+)/', $fullRule, $rules);
            $rules = json_decode($rules[1], true);
            if (preg_match('/#rulesJoinOperator=(.+)/', $fullRule, $rulesJoinOperator)) {
                $rulesJoinOperator = json_decode($rulesJoinOperator[1], true);
            } else {
                $rulesJoinOperator = 'and';
            }
            if (preg_match('/#disabled=(.+)/', $fullRule, $disabled)) {
                $disabled = json_decode($disabled[1], true);
            } else {
                $disabled = false;
            }
            preg_match('/#actions=(.+)/', $fullRule, $actions);
            $actions = json_decode($actions[1], true);
            $rulesArray[ $ruleName ] = array(
                'rules' => $rules,
                'actions' => $actions,
                'disabled' => $disabled,
                'rulesJoinOperator' => $rulesJoinOperator,
            );
        }

        $this->sendAnswer(array('status' => 'ok', 'rules' => $rulesArray));
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
            WHERE M.`seen_flag` = 0 && M.`deleted_flag` = 0 && MB.name LIKE 'INBOX%'"
        )->queryScalar(
                array(
                    ':username' => $_POST['userName'],
                )
            );
        if ($unreadCount === false) {
            throw new CException('user not found');
        }

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
                'ruleId' => $rule->id
            )
        );
    }

    public function actionEditGetMailRule()
    {
        $this->checkRequiredFields(array('userName', 'host', 'email', 'id'));

        /** @var GetMailRule $rule */
        $rule = GetMailRule::model()->findByPk($_POST['id']);
        if ($rule->dbMailUserName !== $_POST['userName']) {
            throw new CException('you are not the owner this rule');
        }

        if (!empty($rule)) {
            if (!isset($_POST['delete']))
                $_POST['delete'] = false;

            $mailBoxType = GetmailHelper::determineMailboxType(
                $_POST['host'],
                $_POST['email'],
                $_POST['password'] ? $_POST['password'] : $rule->password
            );

            $rule->host = $_POST['host'];
            $rule->email = $_POST['email'];
            if (!empty($_POST['password'])) {
                $rule->password = $_POST['password'];
            }
            $rule->dbMailUserName = $_POST['userName'];
            $rule->delete = $_POST['delete'];
            $rule->ssl = $mailBoxType == GetmailHelper::POP3_SSL;
            if (!$rule->save()) {
                throw new CException('error while saving rule');
            }
        } else {
            throw new CException('rule not found');
        }

        $this->sendAnswer(
            array(
                'status' => 'ok',
            )
        );
    }

    public function actionRemoveGetMailRule()
    {
        $this->checkRequiredFields(array('ruleId'));

        $rule = GetMailRule::model()->findByPk($_POST['ruleId']);
        /** @var GetMailRule $rule */
        if (!empty($rule)) {
            if (!$rule->delete()) {
                throw new CException('error while removing rule');
            }
        } else {
            throw new CException('rule not found');
        }

        $this->sendAnswer(array('status' => 'ok'));
    }

    public function actionListGetMailRules()
    {
        $this->checkRequiredFields(array('userName'));

        $answer = array();
        $rules = GetMailRule::model()->findAllByAttributes(array('dbMailUserName' => $_POST['userName']));
        /** @var GetMailRule[] $rules */
        foreach ($rules as $rule) {
            $answer[] = $rule->attributes;
        }

        $this->sendAnswer(array('status' => 'ok', 'rules' => $answer));
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