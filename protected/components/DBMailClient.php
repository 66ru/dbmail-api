<?php

class DBMailClient extends CComponent
{
    public function init()
    {

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
            $output = $this->exec("dbmail-sievecmd -u $userName -c script.sieve");
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
        $output = $this->exec("dbmail-sievecmd -u $userName -i script.sieve $tempFile -y");
        unlink($tempFile);

        $lines = explode("\n", trim($output, " \r\n"));
        if (strpos(end($lines), 'marked inactive') !== false)
            $this->exec("dbmail-sievecmd -u $userName -a script.sieve", 'Script [script.sieve] is now active. All others are inactive.');
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
        passthru($cmd, $returnVal);
        $output = ob_get_clean();
        $lines = explode("\n", trim($output, " \r\n"));
        if ($returnVal)
            throw new DBMailClientException("'$cmd' returned code $returnVal with message: $output");
        if (!empty($expectedLastString) && end($lines) != $expectedLastString)
            throw new DBMailClientException("'$cmd' returned wrong output: $output");

        return $output;
    }
}

class DBMailClientException extends CException {};