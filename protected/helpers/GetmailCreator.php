<?php

class GetmailCreator
{
    /**
     * @param string $host
     * @param string $email
     * @param string $password
     * @param string $dbMailUserName
     * @param bool $delete
     * @return bool|string false if error
     */
    public static function getConfig($host, $email, $password, $dbMailUserName, $delete)
    {
        if (strpos($dbMailUserName, '"') !== false ||
            strpos($dbMailUserName, '/') !== false
        )
            return false;

        $ruleName = self::getRuleName($host, $email, $password, $dbMailUserName);
        $logPath = self::getLogFileName($ruleName);

        $template = "[retriever]
type = SimplePOP3Retriever
server = $host
username = $email
password = $password

[destination]
type = MDA_external
path = /usr/sbin/dbmail-deliver
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
    protected static function getRuleName($host, $email, $password, $dbMailUserName)
    {
        return $dbMailUserName . '-' . md5($host . $email . $password);
    }

    /**
     * @param string $host
     * @param string $email
     * @param string $password
     * @param string $dbMailUserName
     * @return string
     */
    public static function getRuleFileName($host, $email, $password, $dbMailUserName)
    {
        return self::getFileNameByRule(self::getRuleName($host, $email, $password, $dbMailUserName));
    }

    /**
     * @param string $ruleName
     * @return string
     */
    public static function getFileNameByRule($ruleName)
    {
        return self::getConfigsDir() . self::getIntermediatePath($ruleName) . $ruleName;
    }

    public static function getConfigsDir()
    {
        $configsDir = Yii::app()->runtimePath . '/gmConfig/';
        return $configsDir;
    }

    /**
     * @param string $ruleName
     * @return string
     */
    public static function getLogFileName($ruleName)
    {
        return self::getFileNameByRule($ruleName).'.log';
    }

    protected static function getIntermediatePath($ruleName)
    {
        $path = '';
        for ($i = 0; $i < 2; $i++) {
            $path .= substr($ruleName, $i * 2, 2) . '/';
        }

        return $path;
    }
}
