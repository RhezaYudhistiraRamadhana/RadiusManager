<?php
/**
 * RadiusManager — Email Dispatcher & OTP Notification Service
 * Supports pure-PHP Socket SMTP (TLS/SSL) and native PHP mail() transport.
 */

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config.php';
}

/**
 * Mask an email address for privacy display (e.g. Rh***a@polman.ac.id)
 */
function maskEmail(string $email): string {
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    [$local, $domain] = explode('@', $email, 2);
    $len = strlen($local);
    if ($len <= 2) {
        $maskedLocal = substr($local, 0, 1) . '***';
    } elseif ($len <= 4) {
        $maskedLocal = substr($local, 0, 1) . '***' . substr($local, -1);
    } else {
        $maskedLocal = substr($local, 0, 2) . '***' . substr($local, -1);
    }
    return $maskedLocal . '@' . $domain;
}

/**
 * Send an email using configured transport (Socket SMTP or native mail())
 */
function sendMailMessage(string $to, string $subject, string $htmlBody, string $textBody = ''): array {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => "Invalid destination email address: '$to'."];
    }

    $fromEmail = defined('MAIL_FROM') && MAIL_FROM ? MAIL_FROM : 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $fromName  = defined('MAIL_FROM_NAME') && MAIL_FROM_NAME ? MAIL_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'RadiusManager');

    if (empty($textBody)) {
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
    }

    // Try Socket SMTP if host is defined
    if (defined('SMTP_HOST') && SMTP_HOST !== '') {
        $res = sendSocketSmtp($to, $subject, $htmlBody, $textBody, $fromEmail, $fromName);
        if ($res['success']) {
            logMailEvent('SMTP_SUCCESS', $to, $subject);
            return $res;
        }
        logMailEvent('SMTP_FAILED', $to, $subject, $res['error']);
        // If SMTP fails, fallback to mail() if configured
    }

    // Native PHP mail() transport
    $boundary = '=_rm_mime_' . md5((string)microtime(true));
    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
    $headers .= "X-Mailer: RadiusManager-Mailer/1.8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= "--$boundary--\r\n";

    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

    // Suppress warnings in case local sendmail is missing
    $mailSent = @mail($to, $encodedSubject, $body, $headers);

    if ($mailSent) {
        logMailEvent('MAIL_SUCCESS', $to, $subject);
        return ['success' => true];
    }

    $lastErr = error_get_last()['message'] ?? 'Unable to send email via local mail transport.';
    logMailEvent('MAIL_FAILED', $to, $subject, $lastErr);

    return [
        'success'    => false,
        'error'      => $lastErr,
        'dev_logged' => true
    ];
}

/**
 * Lightweight pure-PHP socket SMTP transport (Supports TLS on 587/465, AUTH LOGIN)
 */
function sendSocketSmtp(string $to, string $subject, string $htmlBody, string $textBody, string $fromEmail, string $fromName): array {
    $host   = SMTP_HOST;
    $port   = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $user   = defined('SMTP_USER') ? SMTP_USER : '';
    $pass   = defined('SMTP_PASS') ? SMTP_PASS : '';
    $secure = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'tls';
    $timeout = 10;

    $connectHost = ($secure === 'ssl') ? "ssl://$host" : "tcp://$host";
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client("$connectHost:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return ['success' => false, 'error' => "Cannot connect to SMTP server $host:$port - $errstr ($errno)"];
    }

    stream_set_timeout($socket, $timeout);

    $read = function() use ($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            $data .= $line;
            if ($line === false || (isset($line[3]) && $line[3] === ' ')) {
                break;
            }
        }
        return $data;
    };

    $cmd = function(string $c, array $expectedCodes = [250]) use ($socket, $read): array {
        fputs($socket, $c . "\r\n");
        $resp = $read();
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            return ['ok' => false, 'code' => $code, 'resp' => trim($resp)];
        }
        return ['ok' => true, 'code' => $code, 'resp' => trim($resp)];
    };

    $greeting = $read();
    if ((int)substr($greeting, 0, 3) !== 220) {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP greeting failed: " . trim($greeting)];
    }

    $cRes = $cmd("EHLO " . (gethostname() ?: 'localhost'));
    if (!$cRes['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "EHLO rejected: " . $cRes['resp']];
    }

    // Handle STARTTLS for port 587
    if ($secure === 'tls') {
        $cRes = $cmd("STARTTLS", [220]);
        if (!$cRes['ok']) {
            fclose($socket);
            return ['success' => false, 'error' => "STARTTLS rejected: " . $cRes['resp']];
        }
        $cryptoOk = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$cryptoOk) {
            fclose($socket);
            return ['success' => false, 'error' => "TLS handshake failed with $host"];
        }
        $cmd("EHLO " . (gethostname() ?: 'localhost'));
    }

    // Handle AUTH LOGIN
    if ($user !== '') {
        $cRes = $cmd("AUTH LOGIN", [334]);
        if (!$cRes['ok']) {
            fclose($socket);
            return ['success' => false, 'error' => "AUTH LOGIN rejected: " . $cRes['resp']];
        }
        $cRes = $cmd(base64_encode($user), [334]);
        if (!$cRes['ok']) {
            fclose($socket);
            return ['success' => false, 'error' => "SMTP Username rejected: " . $cRes['resp']];
        }
        $cRes = $cmd(base64_encode($pass), [235]);
        if (!$cRes['ok']) {
            fclose($socket);
            return ['success' => false, 'error' => "SMTP Authentication failed: " . $cRes['resp']];
        }
    }

    $cRes = $cmd("MAIL FROM:<$fromEmail>");
    if (!$cRes['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM rejected: " . $cRes['resp']];
    }

    $cRes = $cmd("RCPT TO:<$to>");
    if (!$cRes['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "RCPT TO rejected: " . $cRes['resp']];
    }

    $cRes = $cmd("DATA", [354]);
    if (!$cRes['ok']) {
        fclose($socket);
        return ['success' => false, 'error' => "DATA command rejected: " . $cRes['resp']];
    }

    $boundary = '=_rm_smtp_' . md5((string)microtime(true));
    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$fromEmail>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "X-Mailer: RadiusManager-SMTP/1.8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $dataPayload  = $headers . "\r\n";
    $dataPayload .= "--$boundary\r\n";
    $dataPayload .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $dataPayload .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $dataPayload .= $textBody . "\r\n\r\n";
    $dataPayload .= "--$boundary\r\n";
    $dataPayload .= "Content-Type: text/html; charset=UTF-8\r\n";
    $dataPayload .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $dataPayload .= $htmlBody . "\r\n\r\n";
    $dataPayload .= "--$boundary--\r\n.";

    $cRes = $cmd($dataPayload);
    $cmd("QUIT", [221]);
    fclose($socket);

    if (!$cRes['ok']) {
        return ['success' => false, 'error' => "Failed sending message payload: " . $cRes['resp']];
    }

    return ['success' => true];
}

/**
 * Log email events into storage/logs/mail.log
 */
function logMailEvent(string $status, string $to, string $subject, string $detail = ''): void {
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/mail.log';
    $time = date('Y-m-d H:i:s');
    $msg = "[$time] [$status] To: $to | Subject: $subject" . ($detail ? " | Detail: $detail" : "") . PHP_EOL;
    @file_put_contents($logFile, $msg, FILE_APPEND);
}

/**
 * Send Branded One-Time Password (OTP) Email for Self-Service Password Reset
 */
function sendOtpEmail(string $toEmail, string $recipientName, string $otpCode): array {
    $appName = defined('APP_NAME') ? APP_NAME : 'RadiusManager';
    $subject = "[$appName] One-Time Password (OTP) Verification Code";

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 20px; color: #1e293b; }
  .email-container { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
  .email-header { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; padding: 28px 24px; text-align: center; }
  .email-header h1 { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; }
  .email-header p { margin: 6px 0 0 0; font-size: 13px; opacity: 0.9; }
  .email-body { padding: 28px 24px; line-height: 1.6; font-size: 14px; }
  .otp-box { background: #f8fafc; border: 2px dashed #3b82f6; border-radius: 10px; padding: 18px; text-align: center; margin: 22px 0; }
  .otp-code { font-family: "SFMono-Regular", Consolas, Menlo, monospace; font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #1e40af; margin: 0; }
  .otp-expire { font-size: 12px; color: #64748b; margin-top: 6px; }
  .warning-box { background: #fef2f2; border-left: 4px solid #dc2626; padding: 12px 14px; border-radius: 6px; font-size: 12px; color: #991b1b; margin-top: 20px; }
  .email-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 24px; text-align: center; font-size: 11px; color: #94a3b8; }
</style>
</head>
<body>
<div class="email-container">
  <div class="email-header">
    <h1>{$appName} Security Service</h1>
    <p>Subscriber Self-Service Password Verification</p>
  </div>
  <div class="email-body">
    <p>Hello <strong>{$recipientName}</strong>,</p>
    <p>You have requested to change your WiFi / RADIUS account password. Please use the following One-Time Password (OTP) verification code to complete the process:</p>
    
    <div class="otp-box">
      <div class="otp-code">{$otpCode}</div>
      <div class="otp-expire">Valid for <strong>10 minutes</strong> only</div>
    </div>

    <p>Enter this 6-digit code on the <strong>Change Account Password</strong> screen in the Subscriber Portal.</p>

    <div class="warning-box">
      <strong>Important Security Notice:</strong><br>
      Never share this OTP code with anyone. The IT Department and Helpdesk will never ask for your verification code. If you did not request this password change, please contact IT Support immediately as someone may be attempting to access your account.
    </div>
  </div>
  <div class="email-footer">
    &copy; 2026 {$appName} &bull; Network Infrastructure &amp; Authentication System
  </div>
</div>
</body>
</html>
HTML;

    $text = "Hello $recipientName,\n\n"
          . "You have requested to change your WiFi / RADIUS account password.\n"
          . "Your One-Time Password (OTP) verification code is: $otpCode\n\n"
          . "This code is valid for 10 minutes.\n"
          . "Never share this code with anyone. If you did not request this change, please contact IT Support immediately.\n\n"
          . "-- $appName Security Service";

    return sendMailMessage($toEmail, $subject, $html, $text);
}
