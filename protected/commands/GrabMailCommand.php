<?php

class GrabMailCommand extends CConsoleCommand
{
    public $threadsCount = 2;

    public function actionIndex()
    {
        $isGetMailRunning = $this->isGetMailRunning();
        if (!$isGetMailRunning) {
            $configs = $this->getConfigs();
            $lineLimit = $this->getCmdLineLimit();

            $configsPerProcess = ceil(count($configs) / $this->threadsCount);
            $i = 0;
            $cmd = Yii::app()->params['getmail'];
            foreach ($configs as &$config) {
                $cmd .= ' --rcfile ' . $config;
                $i++;

                if ($i == $configsPerProcess || strlen($cmd) >= $lineLimit) {
                    $this->startGetMail($cmd);
                    $i = 0;
                    $cmd = Yii::app()->params['getmail'];
                }
            }
        } else {
            if (time() - $isGetMailRunning > 3600) {
                throw new CException('getmail process executing for 1 day');
            }
        }
    }

    /**
     * @param string $cmd
     * @throws CException
     */
    public function startGetMail($cmd)
    {
        $pid = exec($cmd . ' >/dev/null 2>&1 & echo $!;');
        if (!$pid) {
            throw new CException('can\'t get pid of executed getmail process');
        }

        if (!file_put_contents("/tmp/gm-$pid.pid", $pid)) {
            throw new CException("can't write pid of executed getmail in file /tmp/gm-$pid.pid");
        }
    }

    /**
     * returns false or unix timestamp of latest exec time
     * @return bool|int
     * @throws CException
     */
    public function isGetMailRunning()
    {
        $res = glob("/tmp/gm-*.pid");
        if ($res === false || !is_array($res)) {
            throw new CException('can\'t get pid of executed getmail process');
        }

        if (is_array($res) && !empty($res)) {
            $latestStartDate = time();
            foreach ($res as $file) {
                $time = filemtime($file);
                if ($time < $latestStartDate) {
                    $latestStartDate = $time;
                }
            }

            return $latestStartDate;
        } else {
            return false;
        }
    }

    /**
     * @param null|string $startDir defaults to configs directory
     * @return string[] array with config file names
     * @throws CException
     */
    protected function getConfigs($startDir = null)
    {
        if (!$startDir) {
            $startDir = GetmailHelper::getConfigsDir();
        }

        if (false === $d = opendir($startDir)) {
            throw new CException('can\'t open directory for reading: ' . $startDir);
        }

        $result = array();
        while ($entry = readdir($d)) {
            $entry = $startDir . '/' . $entry;
            if (is_dir($entry)) {
                $result += $this->getConfigs($entry);
            } else {
                $result[] = $entry;
            }
        }
        closedir($d);

        return $result;
    }

    /**
     * returns maximum command line length in bytes
     * @return int
     * @throws CException
     */
    protected function getCmdLineLimit()
    {
        ob_start();
        passthru('xargs --show-limits --no-run-if-empty </dev/null', $returnVal);
        $output = ob_get_clean();

        if ($returnVal) {
            throw new CException("xargs returned code $returnVal with message: $output");
        }

        if (!preg_match('/Maximum length of command we could actually use: (\d+)/', $output, $matches)) {
            throw new CException("xargs returned code $returnVal with message: $output");
        }

        return $matches[1];
    }
}
