<?php

class DBMailClient extends CComponent
{
    public function init()
    {
        if (false === setlocale(LC_CTYPE, 'ru_RU.UTF-8')) {
            Yii::log("Can't set ru_RU.UTF-8 locale", CLogger::LEVEL_WARNING);
        }
    }

    /**
     * @param $userName string
     * @throws DBMailClientException
     * @return string
     */
    public function getScript($userName)
    {
        $userName = escapeshellarg($userName);
        try {
            $output = $this->exec(Yii::app()->params['dbmail-sievecmd'] . " -u $userName -c script.sieve");
        } catch (DBMailClientException $e){
            return '';
        }

        return $output;
    }

    /**
     * @param $userName string
     * @param $script string
     */
    public function writeScript($userName, $script)
    {
        $userName = escapeshellarg($userName);

        $tempFile = tempnam(sys_get_temp_dir(), 'sieve-');
        file_put_contents($tempFile, $script);
        $output = $this->exec(Yii::app()->params['dbmail-sievecmd'] . " -u $userName -i script.sieve $tempFile -y");
        unlink($tempFile);

        $lines = explode("\n", trim($output, " \r\n"));
        if (strpos(end($lines), 'marked inactive') !== false)
            $this->exec(Yii::app()->params['dbmail-sievecmd'] . " -u $userName -a script.sieve", 'Script [script.sieve] is now active. All others are inactive.');
    }

    /**
     * @param string $userName
     * @param string $password
     * @throws DBMailClientException
     */
    public function createUser($userName, $password)
    {
        if (strpos($userName, '@') !== false) {
            $mailAlias = escapeshellarg($userName);
        } else {
            $mailAlias = escapeshellarg($userName . '@' . Yii::app()->params['defaultMailDomain']);
        }
        $userName = escapeshellarg($userName);
        $password = escapeshellarg($password);
        $this->exec(Yii::app()->params['dbmail-users'] . " -a $userName -w $password -p sha256");
        try {
            $this->exec(Yii::app()->params['dbmail-users'] . " -c $userName -s $mailAlias");
        } catch (DBMailClientException $e) {
            $this->exec(Yii::app()->params['dbmail-users'] . " -d $userName");
            throw $e;
        }
    }

    /**
     * @param string $userName
     * @param string $password
     * @throws DBMailClientException
     */
    public function changePassword($userName, $password)
    {
        $userName = escapeshellarg($userName);
        $password = escapeshellarg($password);
        try {
            $this->exec(Yii::app()->params['dbmail-users'] . " -c $userName -w $password -p sha256");
        } catch (DBMailClientException $e) {
            if ($e->getExitCode() != 1) { // todo: dbmail 3.1.7 bug
                throw $e;
            }
        }
    }

    /**
     * @param string $userName
     */
    public function deleteUser($userName)
    {
        $userName = escapeshellarg($userName);
        $this->exec(Yii::app()->params['dbmail-users'] . " -d $userName");
    }

    /**
     * @param string $userName
     */
    public function truncateUser($userName)
    {
        $userName = escapeshellarg($userName);
        $this->exec(Yii::app()->params['dbmail-users'] . " -e $userName -y");
    }

    /**
     * @param $cmd string
     * @param null|string $expectedLastString
     * @throws DBMailClientException
     * @return string
     */
    protected function exec($cmd, $expectedLastString = null)
    {
        ob_start();
        passthru($cmd . ' 2>&1', $returnVal);
        $output = ob_get_clean();
        $lines = explode("\n", trim($output, " \r\n"));
        if ($returnVal) {
            throw new DBMailClientException("'$cmd' returned code $returnVal with message: $output", $returnVal, $output);
        }
        if (!empty($expectedLastString) && end($lines) != $expectedLastString) {
            throw new DBMailClientException("'$cmd' returned wrong output: $output", $returnVal, $output);
        }

        return $output;
    }
}

class DBMailClientException extends CException
{
    /** @var string */
    protected $output;

    /** @var int */
    protected $exitCode;

    public function __construct($message = "", $exitCode = 0, $output = '', \Exception $previous = null)
    {
        $this->exitCode = $exitCode;
        $this->output = $output;
        parent::__construct($message, 0, $previous);
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function getExitCode()
    {
        return $this->exitCode;
    }
}