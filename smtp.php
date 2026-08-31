<?php
/**
 * Minimal authenticated SMTP sender.
 * Enough to deliver one multipart message over an implicit-TLS connection
 * (port 465) or STARTTLS (587). No dependencies.
 */
declare(strict_types=1);

function smtp_send(array $cfg, string $to, string $cc, string $subject,
                   string $headersExtra, string $body, ?string &$err = null): bool
{
    $host = $cfg['smtp_host'];
    $port = (int)$cfg['smtp_port'];
    $user = $cfg['smtp_user'];
    $pass = $cfg['smtp_pass'];
    $from = $cfg['smtp_from'] ?: $user;

    $transport = ($port === 465) ? "ssl://{$host}" : "tcp://{$host}";
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);

    $fp = @stream_socket_client("{$transport}:{$port}", $errno, $errstr, 20,
                                STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $err = "connect: {$errstr}"; return false; }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp, &$err): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break;   // last line of reply
        }
        return $out;
    };
    $cmd = function (string $line, string $expect) use ($fp, $read, &$err): bool {
        if ($line !== '') fwrite($fp, $line . "\r\n");
        $resp = $read();
        if (strncmp($resp, $expect, strlen($expect)) !== 0) {
            $err = trim(($line !== '' ? explode(' ', $line)[0] : 'greeting') . ' -> ' . $resp);
            return false;
        }
        return true;
    };

    $ok = $cmd('', '220')
       && $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '250');

    if ($ok && $port !== 465) {
        $ok = $cmd('STARTTLS', '220')
           && @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
           && $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '250');
        if (!$ok && $err === null) $err = 'starttls failed';
    }

    $ok = $ok
       && $cmd('AUTH LOGIN', '334')
       && $cmd(base64_encode($user), '334')
       && $cmd(base64_encode($pass), '235')
       && $cmd('MAIL FROM:<' . $from . '>', '250');

    if ($ok) {
        foreach (array_filter(array_map('trim', explode(',', $to . ',' . $cc))) as $rcpt) {
            if (!$cmd('RCPT TO:<' . $rcpt . '>', '250')) { $ok = false; break; }
        }
    }

    if ($ok && $cmd('DATA', '354')) {
        $head = "From: {$cfg['site']} Website <{$from}>\r\n"
              . "To: {$to}\r\n"
              . ($cc !== '' ? "Cc: {$cc}\r\n" : '')
              . 'Subject: ' . $subject . "\r\n"
              . "Date: " . date('r') . "\r\n"
              . $headersExtra . "\r\n";
        // A leading dot on its own line would end the message early.
        $safe = preg_replace('/^\./m', '..', $body);
        fwrite($fp, $head . "\r\n" . $safe . "\r\n.\r\n");
        $ok = $cmd('', '250');
    } else {
        $ok = false;
    }

    @fwrite($fp, "QUIT\r\n");
    @fclose($fp);
    return $ok;
}
