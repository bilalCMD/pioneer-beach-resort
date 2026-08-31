<?php
/**
 * Pioneer Beach RV Resort - form delivery.
 * Handles the contact form and the footer newsletter signup, and mails
 * a formatted notification to the resort.
 */
declare(strict_types=1);

$CFG   = require __DIR__ . '/form-config.php';
require_once __DIR__ . '/smtp.php';

$TO    = $CFG['to'];
$CC    = $CFG['cc'];
$SITE  = $CFG['site'];
$PHONE = $CFG['phone'];

$EOL = "\r\n";

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

/* Bots fill hidden fields; people do not. Look successful, send nothing. */
if (trim((string)($_POST['website'] ?? '')) !== '') {
    echo json_encode(['ok' => true]);
    exit;
}

/** Single-line field, CR/LF stripped so it can never forge a mail header. */
function clean(string $k, int $max = 500): string {
    $v = trim((string)($_POST[$k] ?? ''));
    $v = str_replace(["\r", "\n"], ' ', $v);
    return mb_substr($v, 0, $max);
}
function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$type = clean('form_type', 30) ?: 'contact';

if ($type === 'newsletter') {
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
        exit;
    }
    $kicker  = 'Newsletter signup';
    $heading = 'New newsletter signup';
    $intro   = 'Someone just subscribed from the website footer.';
    $rows    = [['Email', $email]];
    $replyTo = $email;
    $subject = 'Newsletter signup - ' . $email;
    $plain   = "New newsletter signup.\n\nEmail: {$email}\n";
    $message = '';
} else {
    $first   = clean('first_name', 80);
    $last    = clean('last_name', 80);
    $email   = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone   = clean('phone', 40);
    $inquiry = clean('inquiry', 60);
    $message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 4000);

    if ($first === '' || $email === false || $message === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Please fill in your name, a valid email and a message.']);
        exit;
    }

    $name    = trim($first . ' ' . $last);
    $kicker  = 'Website enquiry';
    $heading = 'New enquiry from the website';
    $intro   = 'Reply directly to this email and it will go straight to the guest.';
    $rows    = [
        ['Name',    $name],
        ['Email',   $email],
        ['Phone',   $phone !== '' ? $phone : '-'],
        ['Inquiry', $inquiry !== '' ? $inquiry : '-'],
    ];
    $replyTo = $email;
    $subject = 'Website enquiry' . ($inquiry !== '' ? " - {$inquiry}" : '') . " - {$name}";
    $plain   = "New enquiry from the website.\n\n"
             . "Name:    {$name}\n"
             . "Email:   {$email}\n"
             . "Phone:   " . ($phone !== '' ? $phone : '-') . "\n"
             . "Inquiry: " . ($inquiry !== '' ? $inquiry : '-') . "\n\n"
             . "Message:\n{$message}\n";
}

$received = date('l j F Y, g:ia');

$rowsHtml = '';
foreach ($rows as $row) {
    $label = $row[0];
    $value = $row[1];
    $shown = ($label === 'Email')
        ? '<a href="mailto:' . esc($value) . '" style="color:#ED1F24;text-decoration:none">' . esc($value) . '</a>'
        : esc($value);
    $rowsHtml .= '<tr>'
        . '<td style="padding:11px 0;border-bottom:1px solid #f0e6e6;font:600 12px/1.4 Arial,Helvetica,sans-serif;color:#8a8a8a;text-transform:uppercase;letter-spacing:.08em;width:110px;vertical-align:top">'
        . esc($label) . '</td>'
        . '<td style="padding:11px 0;border-bottom:1px solid #f0e6e6;font:400 15px/1.5 Arial,Helvetica,sans-serif;color:#1a1a1a">'
        . $shown . '</td></tr>';
}

$messageHtml = '';
if ($type !== 'newsletter') {
    $messageHtml =
        '<p style="margin:26px 0 8px;font:600 12px/1.4 Arial,Helvetica,sans-serif;color:#8a8a8a;text-transform:uppercase;letter-spacing:.08em">Message</p>'
      . '<div style="background:#fdf4f4;border-left:3px solid #ED1F24;border-radius:0 8px 8px 0;padding:16px 18px;font:400 15px/1.65 Arial,Helvetica,sans-serif;color:#1a1a1a">'
      . nl2br(esc($message)) . '</div>'
      . '<p style="margin:26px 0 0"><a href="mailto:' . esc($email) . '" style="display:inline-block;background:#ED1F24;color:#ffffff;text-decoration:none;border-radius:100px;padding:13px 30px;font:700 14px/1 Arial,Helvetica,sans-serif">Reply to this guest</a></p>';
}

$html = '<!doctype html><html><body style="margin:0;padding:0;background:#f4f4f5">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:28px 12px">'
  . '<tr><td align="center">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:14px;overflow:hidden">'
  . '<tr><td style="background:#ED1F24;padding:22px 30px">'
  . '<p style="margin:0;font:700 11px/1 Arial,Helvetica,sans-serif;color:#ffd9da;text-transform:uppercase;letter-spacing:.16em">'
  . esc($kicker) . '</p>'
  . '<p style="margin:8px 0 0;font:700 21px/1.25 Georgia,serif;color:#ffffff">' . esc($SITE) . '</p>'
  . '</td></tr>'
  . '<tr><td style="padding:30px">'
  . '<h1 style="margin:0 0 6px;font:400 22px/1.3 Georgia,serif;color:#1a1a1a">' . esc($heading) . '</h1>'
  . '<p style="margin:0 0 22px;font:400 14px/1.6 Arial,Helvetica,sans-serif;color:#6b6b6b">' . esc($intro) . '</p>'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rowsHtml . '</table>'
  . $messageHtml
  . '</td></tr>'
  . '<tr><td style="background:#faf7f7;padding:18px 30px;border-top:1px solid #f0e6e6">'
  . '<p style="margin:0;font:400 12px/1.6 Arial,Helvetica,sans-serif;color:#9a9a9a">'
  . 'Sent from the ' . esc($SITE) . ' website on ' . esc($received) . '.<br>'
  . '120 Gulfwind Drive, Port Aransas, TX 78373 &nbsp;&middot;&nbsp; ' . esc($PHONE)
  . '</p></td></tr>'
  . '</table></td></tr></table></body></html>';

$plain .= "\nReceived: {$received}\n";

$boundary = 'pbr' . bin2hex(random_bytes(8));
$host     = preg_replace('/[^a-z0-9.\-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'pioneerbeachresort.com'));

$mimeHeaders = 'Reply-To: ' . $replyTo . $EOL
             . 'MIME-Version: 1.0' . $EOL
             . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

/* mail() needs From/Cc in the header blob; the SMTP path writes its own. */
$headers = 'From: ' . $SITE . ' Website <no-reply@' . $host . '>' . $EOL
         . 'Cc: ' . $CC . $EOL
         . $mimeHeaders;

$body = '--' . $boundary . $EOL
      . 'Content-Type: text/plain; charset=utf-8' . $EOL . $EOL . $plain . $EOL . $EOL
      . '--' . $boundary . $EOL
      . 'Content-Type: text/html; charset=utf-8' . $EOL . $EOL . $html . $EOL . $EOL
      . '--' . $boundary . '--';

/* Keep a copy on disk regardless of what the mail server does, so an
   enquiry is never lost to a silent delivery failure. */
@file_put_contents(__DIR__ . '/form-submissions.log',
    json_encode(['at' => date('c'), 'type' => $type, 'subject' => $subject,
                 'fields' => $rows, 'message' => $message],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND | LOCK_EX);

$err  = null;
$sent = false;

if (!empty($CFG['smtp_user'])) {
    $sent = smtp_send($CFG, $TO, $CC, $subject, $mimeHeaders, $body, $err);
} else {
    $sent = @mail($TO, $subject, $body, $headers);
    if (!$sent) { $err = 'mail() returned false'; }
}

if ($sent) {
    echo json_encode(['ok' => true]);
} else {
    @file_put_contents(__DIR__ . '/form-submissions.log',
        json_encode(['at' => date('c'), 'delivery_error' => $err]) . PHP_EOL,
        FILE_APPEND | LOCK_EX);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Sorry, that could not be sent. Please call us on ' . $PHONE . '.']);
}
