<?php

/**
 * Class GetMailRule
 *
 * @property int $id
 * @property string $host
 * @property string $email
 * @property string $password
 * @property string $dbMailUserName
 * @property bool $delete
 * @property bool $ssl
 * @property int $status
 */
class GetMailRule extends CActiveRecord
{
    const SUCCESS = 0;
    const ERROR_NOT_YET = 1;
    const ERROR_WRONG_PASSWORD = 2; //getmailOperationError error ?
    const ERROR_CONNECTION_ERROR = 3;
    const ERROR_WRONG_DOMAIN = 4;
    const ERROR_UNKNOWN = 5;

    public function init()
    {
        parent::init();

        $this->status = self::ERROR_NOT_YET;
    }

    /**
     * @param string $className
     * @return GetMailRule
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function getDbConnection()
    {
        return Yii::app()->getComponent('getmaildb');
    }

    public function rules()
    {
        return array(
            array('host, email, password, dbMailUserName', 'length', 'max' => 255, 'allowEmpty' => false),
            array('delete, ssl', 'numerical', 'integerOnly' => true, 'min' => 0),
            array('status', 'numerical', 'integerOnly' => true),
        );
    }

    /**
     * @return bool|string false if error
     */
    public function getConfig()
    {
        if ($this->isNewRecord ||
            strpos($this->dbMailUserName, '"') !== false ||
            strpos($this->dbMailUserName, '/') !== false
        ) {
            return false;
        }

        $logPath = $this->getLogFileName();
        $dbmailDeliver = Yii::app()->params['dbmail-deliver'];
        $useSsl = $this->ssl ? 'SSL' : '';

        $template = "[retriever]
type = SimplePOP3{$useSsl}Retriever
server = $this->host
username = $this->email
password = $this->password

[destination]
type = MDA_external
path = $dbmailDeliver
user = dbmail
group = dbmail
arguments = (\"-u\", \"{$this->dbMailUserName}\")

[options]
message_log = $logPath
delete = $this->delete";

        return $template;
    }

    /**
     * @return string
     */
    public function getConfigsDir()
    {
        return Yii::app()->runtimePath . '/gmConfigs/';
    }

    /**
     * @return string
     */
    public function getIntermediatePath()
    {
        $subDirMask = md5($this->dbMailUserName);

        $path = '';
        for ($i = 0; $i < 2; $i++) {
            $path .= substr($subDirMask, $i * 2, 2) . '/';
        }

        return $path;
    }

    /**
     * @return string
     */
    public function getRuleFileName()
    {
        return $this->getConfigsDir() . $this->getIntermediatePath() . $this->dbMailUserName . '-' . $this->id;
    }

    /**
     * @return string
     */
    public function getLogFileName()
    {
        return $this->getRuleFileName() . '.log';
    }

    /**
     * @return int one of GetMailRule::SUCCESS or GetMailRule::ERROR_*
     */
    public function getRuleStatus()
    {
        $logFile = $this->getLogFileName();
        if (!file_exists($logFile)) {
            return self::ERROR_NOT_YET;
        }

        $line = exec("tail -n 1 $logFile", $output, $returnVal);

        if (preg_match('/Password supplied for .*? is incorrect/', $line) ||
            preg_match('/invalid password/', $line) ||
            preg_match('/incorrect password/', $line)
        ) {
            return self::ERROR_WRONG_PASSWORD;
        }
        if (preg_match('/gaierror error/', $line)) {
            return self::ERROR_WRONG_DOMAIN;
        }
        if (preg_match('/socket error/', $line)) {
            return self::ERROR_CONNECTION_ERROR;
        }
        if (preg_match('/delivered to MDA_external command dbmail-deliver/', $line)) {
            return self::SUCCESS;
        }

        return self::ERROR_UNKNOWN;
    }
}