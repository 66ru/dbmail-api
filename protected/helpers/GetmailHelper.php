<?php

class GetmailHelper
{
    const SUCCESS = 0;
    const ERROR_NOT_YET = 1;
    const ERROR_WRONG_PASSWORD = 2; //getmailOperationError error ?
    const ERROR_CONNECTION_ERROR = 3;
    const ERROR_WRONG_DOMAIN = 4;
    const ERROR_UNKNOWN = 5;

    const POP3_DEFAULT = 'default';
    const POP3_SSL = 'ssl';

    /**
     * @param string $host
     * @param string $email
     * @param string $password
     * @param string $dbMailUserName
     * @param bool $delete
     * @param bool $useSsl
     * @return bool|string false if error
     */
    public static function getConfig($host, $email, $password, $dbMailUserName, $delete, $useSsl)
    {
        if (strpos($dbMailUserName, '"') !== false ||
            strpos($dbMailUserName, '/') !== false
        ) {
            return false;
        }

        $ruleName = self::getRuleName($host, $email, $password, $dbMailUserName);
        $logPath = self::getLogFileName($ruleName);
        $dbmailDeliver = Yii::app()->params['dbmail-deliver'];
        $delete = $delete ? 1 : 0;
        $useSsl = $useSsl ? 'SSL' : '';

        $template = "[retriever]
type = SimplePOP3{$useSsl}Retriever
server = $host
username = $email
password = $password

[destination]
type = MDA_external
path = $dbmailDeliver
user = dbmail
group = dbmail
arguments = (\"-u\", \"$dbMailUserName\")

[options]
message_log = $logPath
delete = $delete";

        return $template;
    }

    /**
     * @param string $host
     * @param string $email
     * @param string $password
     * @param string $dbMailUserName
     * @return string
     */
    public static function getRuleName($host, $email, $password, $dbMailUserName)
    {
        return $dbMailUserName . '-' . md5($host . $email . $password);
    }

    /**
     * @param string $ruleName
     * @return string
     */
    public static function getFileName($ruleName)
    {
        return self::getConfigsDir() . self::getIntermediatePath($ruleName) . $ruleName;
    }

    public static function getConfigsDir()
    {
        $configsDir = Yii::app()->runtimePath . '/gmConfigs/';
        return $configsDir;
    }

    /**
     * @param string $ruleName
     * @return string
     */
    public static function getLogFileName($ruleName)
    {
        return self::getFileName($ruleName) . '.log';
    }

    /**
     * @param string $ruleName
     * @return string
     */
    public static function getIntermediatePath($ruleName)
    {
        $dbMailUserName = explode('-', $ruleName);
        $subDirMask = md5($dbMailUserName[0]);

        $path = '';
        for ($i = 0; $i < 2; $i++) {
            $path .= substr($subDirMask, $i * 2, 2) . '/';
        }

        return $path;
    }

    /**
     * @param string $ruleName
     * @return int
     */
    public static function getRuleStatus($ruleName)
    {
        $logFile = self::getLogFileName($ruleName);
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

    /**
     * determine 110 or 995 port use to connecting
     * @param $host
     * @param $email
     * @param $password
     * @return bool|string GetmailHelper::POP3_* or false if failed
     */
    public static function determineMailboxType($host, $email, $password)
    {
        imap_timeout(IMAP_OPENTIMEOUT, 5);
        imap_timeout(IMAP_READTIMEOUT, 5);
        imap_timeout(IMAP_WRITETIMEOUT, 5);
        imap_timeout(IMAP_CLOSETIMEOUT, 1);

        $imapStream = imap_open('{' . $host . ':110/pop3/novalidate-cert}INBOX', $email, $password);
        if ($imapStream) {
            imap_close($imapStream);
            return self::POP3_DEFAULT;
        }
        $imapStream = imap_open('{' . $host . ':995/pop3/ssl/novalidate-cert}INBOX', $email, $password);
        if ($imapStream) {
            imap_close($imapStream);
            return self::POP3_SSL;
        }

        return false;
    }
}
