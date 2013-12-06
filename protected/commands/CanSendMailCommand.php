<?php

class CanSendMailCommand extends CConsoleCommand
{
    public function sendAnswer($allow = true)
    {
        $action = $allow ? 'dunno' : 'reject Service is unavailable';
        echo "action=$action\n\n";
    }

    public function actionIndex()
    {
        $f = fopen('php://stdin', 'r');
        $stringData = fread($f, 1024);
        fclose($f);
        if (substr($stringData, -2) === "\n\n") {
            preg_match_all('/^([^=\s]+)=(.*?)$/m', $stringData, $matches);
            $data = array_combine($matches[1], $matches[2]);
            if (!empty($data['request']) && $data['request'] !== 'smtpd_access_policy') {
                throw new CException("unrecognized request type: {$data['request']}");
            }

            if (!empty($data['sasl_username'])) {
                if (empty($data['instance'])) {
                    throw new CException("empty instance: " . $stringData);
                }
                if (empty($data['recipient'])) {
                    throw new CException("empty recipient: " . $stringData);
                }
                if (empty($data['size'])) {
                    /** @var ESentryComponent $sentry */
                    $sentry = Yii::app()->getComponent('RSentryException');
                    $exception = new CException('size is 0: ' . $stringData);
                    $sentry->handleException(new CExceptionEvent(null, $exception));
                }
                $answer = \m8rge\CurlHelper::postUrl(
                    Yii::app()->params['webmailEndPoint'],
                    array(
                        'secKey' => Yii::app()->params['webmailSecKey'],
                        'controller' => 'userOptions',
                        'action' => 'canSendEmail',
                        'login' => $data['sasl_username'],
                        'instance' => $data['instance'],
                        'recipient' => $data['recipient'],
                        'size' => $data['size'],
                        'client_address' => $data['client_address'],
                    ),
                    array(
                        CURLOPT_TIMEOUT => 10,
                    )
                );
                $canSendEmail = @json_decode($answer, true);
                if (is_null($canSendEmail)) {
                    throw new CException("unrecognized answer from webmail: $answer");
                }
            } else {
                $canSendEmail = true;
            }
            $this->sendAnswer($canSendEmail);
        } else {
            $logData = str_replace(array("\r","\n"), array("\\r", "\\n"), $stringData);
            throw new CException("request doesn't ends with double eol: $logData");
        }
    }
}