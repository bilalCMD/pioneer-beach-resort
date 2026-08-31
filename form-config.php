<?php
/**
 * Mail settings for form-handler.php
 *
 * PHP mail() on Hostinger is accepted and then silently dropped when the
 * From address is not a real mailbox on the account, which is why the first
 * test never arrived. Filling in the SMTP block below switches delivery to
 * an authenticated send, which is what actually reaches Gmail and Outlook.
 *
 * To fill it in:
 *   hPanel -> Emails -> Email Accounts -> Create a mailbox on the domain
 *   (for example noreply@pioneerv2.dartwebsite.com), then paste the address
 *   and password here. SMTP_FROM must be that same mailbox.
 */

return [
    // Where enquiries go.
    'to'   => 'Info@pioneerbeachresort.com',
    'cc'   => 'altafbilal649@gmail.com',

    // Leave SMTP_USER empty to fall back to PHP mail().
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,
    'smtp_user' => '',
    'smtp_pass' => '',
    'smtp_from' => '',   // must match smtp_user

    'site'  => 'Pioneer Beach RV Resort',
    'phone' => '1-888-480-3246',
];
