<?php

class GetmailHelper
{
    const POP3_DEFAULT = 'default';
    const POP3_SSL = 'ssl';

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

        $oldErrorReporting = error_reporting();
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_ERROR);
        $imapStream = imap_open('{' . $host . ':110/pop3/novalidate-cert}INBOX', $email, $password, OP_SILENT);
        imap_errors(); // clear imap errors stack
        if ($imapStream) {
            imap_close($imapStream);
            error_reporting($oldErrorReporting);
            return self::POP3_DEFAULT;
        }
        $imapStream = imap_open('{' . $host . ':995/pop3/ssl/novalidate-cert}INBOX', $email, $password, OP_SILENT);
        imap_errors(); // clear imap errors stack
        if ($imapStream) {
            imap_close($imapStream);
            error_reporting($oldErrorReporting);
            return self::POP3_SSL;
        }

        error_reporting($oldErrorReporting);
        return false;
    }
}
