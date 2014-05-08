<?php

class XtraDbCommand extends CDbCommand
{
    /**
     * Executes the SQL statement.
     * This method is meant only for executing non-query SQL statement.
     * No result set will be returned.
     * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
     * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
     * them in this way can improve the performance. Note that if you pass parameters in this way,
     * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
     * Please also note that all values are treated as strings in this case, if you need them to be handled as
     * their real data types, you have to use {@link bindParam} or {@link bindValue} instead.
     * @return integer number of rows affected by the execution.
     * @throws CException execution failed
     */
    public function execute($params = array())
    {
        return $this->executeCircular($params);
    }

    protected function executeCircular($params = array(), $i = 0)
    {
        try {
            return parent::execute($params);
        } catch (CDbException $e) {
            if ($e->errorInfo[0] == '40001'
                && strpos($e->getMessage(), 'try restarting transaction') !== false
                && $i < 2
            ) {
                usleep(1000 * $i);
                return $this->executeCircular($params, $i + 1);
            } else {
                throw $e;
            }
        }
    }
}