<?php
/**
 * Pioneer Beach RV Resort - form delivery.
 * Handles both the contact form and the footer newsletter signup.
 * Replies are sent to the address in $TO below.
 */
declare(strict_types=1);

$TO      = 'Info@pioneerbeachresort.com';
$SITE    = 'Pioneer Beach RV Resort';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

/** Bots fill hidden fields; people do not. */
if (trim((string)($_POST['website'] ?? '')) !== '') {
    echo json_encode(['ok' => true]);   // look successful, send nothing
    exit;
}

function clean(string $k, int $max = 500): string {
    $v = trim((string)($_POST[$k] ?? ''));
    $v = str_replace(["\r", "\n"], ' ', $v);      // keep headers un-injectable
    return mb_substr($v, 0, $max);
}

$type = clean('form_type', 30) ?: 'contact';

if ($type === 'newsletter') {
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
        exit;
    }
    $subject = 'Newsletter signup - ' . $email;
    $body    = "New newsletter signup.\n\nEmail: {$email}\n";
    $replyTo = $email;
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
    $subject = 'Website enquiry' . ($inquiry !== '' ? " - {$inquiry}" : '') . " - {$name}";
    $body    = "New enquiry from the website.\n\n"
             . "Name:    {$name}\n"
             . "Email:   {$email}\n"
             . "Phone:   " . ($phone !== '' ? $phone : '-') . "\n"
             . "Inquiry: " . ($inquiry !== '' ? $inquiry : '-') . "\n\n"
             . "Message:\n{$message}\n";
    $replyTo = $email;
}

$headers = implode("\r\n", [
    'From: ' . $SITE . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'pioneerbeachresort.com') . '>',
    'Reply-To: ' . $replyTo,
    'Content-Type: text/plain; charset=utf-8',
    'MIME-Version: 1.0',
]);

if (@mail($TO, $subject, $body, $headers)) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Sorry, that could not be sent. Please call us on 1-888-480-3246.']);
}
