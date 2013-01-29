<?php

class SieveClient
{
    /**
     * @param $userName string
     * @throws SieveClientException
     * @return string
     */
    public function getScript($userName)
    {
        $userName = escapeshellarg($userName);
        $output = $this->exec("dbmail-sievecmd -u $userName -e");
        if (end($output) == 'No active script found!')
            return '';
        if (end($output) == 'File not modified, canceling.')
            throw new SieveClientException("dbmail-sievecmd returned wrong output");

        array_pop($output);

        return implode("\n", $output);
    }

    /**
     * @param $userName string
     * @param $script string
     */
    public function writeScript($userName, $script)
    {
        $userName = escapeshellarg($userName);
        $this->exec("dbmail-sievecmd -u $userName -r script.sieve", 'Script [script.sieve] deleted.');

        $tempFile = tempnam(sys_get_temp_dir(), 'sieve-');
        file_put_contents($tempFile, $script);
        $output = $this->exec("dbmail-sievecmd -u $userName -i script.sieve $tempFile -y");
        unlink($tempFile);

        if (strpos(end($output), 'marked inactive') !== false)
            $this->exec("dbmail-sievecmd -u $userName -a script.sieve", 'Script [test.sieve] is now active. All others are inactive.');
    }

    /**
     * @param $cmd string
     * @param null|string $expectedLastString
     * @return string[]
     * @throws SieveClientException
     */
    protected function exec($cmd, $expectedLastString = null)
    {
        exec('EDITOR=cat '.$cmd, $output, $returnVal);
        if ($returnVal)
            throw new SieveClientException("dbmail-sievecmd returned code $returnVal");
        if (!empty($expectedLastString) && end($output) != $expectedLastString)
            throw new SieveClientException("dbmail-sievecmd returned wrong output");

        return $output;
    }
}

class SieveClientException extends Exception {};