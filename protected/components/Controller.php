<?php

class Controller extends CController
{
    /**
     * @param $data mixed
     */
    public function sendAnswer($data)
    {
        echo json_encode($data);
        Yii::app()->end();
    }
}