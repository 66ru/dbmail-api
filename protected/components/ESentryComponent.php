<?php

Yii::import('lib.sentry.*');

class ESentryComponent extends RSentryComponent
{
    public $skip = array(404, 403);

    /**
     * @param CExceptionEvent $event
     */
    public function handleException($event)
    {
        if (!($event->exception instanceof CHttpException) ||
            $event->exception instanceof CHttpException && !in_array($event->exception->statusCode, $this->skip))
            parent::handleException($event);
    }
}