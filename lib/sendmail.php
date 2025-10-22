<?php
// lib/sendmail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

function _write_mail_log($logDir, $line) {
    try {
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = rtrim($logDir, '/') . '/mail.log';
        $timestamp = date('c');
        @file_put_contents($logFile, "[{$timestamp}] {$line}" . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Ignorer silencieusement si le logging échoue
    }
}

function sendMail($to, $subject, $body, $cfg, $cc = []) {
  $logDir = $cfg['log_dir'] ?? __DIR__ . '/../storage/logs';

  // Si SMTP désactivé, fallback vers mail()
  if (empty($cfg['smtp']['enabled'])) {
    $headers = "From: {$cfg['smtp']['from_name']} <{$cfg['smtp']['from_email']}>\r\n";
    if (!empty($cc)) {
      $headers .= "Cc: " . implode(',', $cc) . "\r\n";
    }
    $sent = @mail($to, $subject, $body, $headers);
    if (!$sent) {
        _write_mail_log($logDir, "mail() failed for recipient {$to}. Error: " . error_get_last()['message']);
    }
    return $sent;
  }

  $m = new PHPMailer(true);
  try {
    // $m->SMTPDebug = 2; // Décommenter pour un debug très verbeux
    $m->isSMTP();
    $m->Host = $cfg['smtp']['host'];
    $m->SMTPAuth = true;
    $m->Username = $cfg['smtp']['user'];
    $m->Password = $cfg['smtp']['pass'];
    $m->SMTPSecure = $cfg['smtp']['secure']; // 'tls'
    $m->Port = (int)$cfg['smtp']['port'];
    $m->setFrom($cfg['smtp']['from_email'], $cfg['smtp']['from_name']);
    $m->addAddress($to);

    if (!empty($cc)) {
      foreach ($cc as $cc_email) {
        $m->addCC($cc_email);
      }
    }

    $m->Subject = $subject;
    $m->Body    = $body;
    $m->CharSet = 'UTF-8';
    $m->send();
    return true;
  } catch (Exception $e) {
    $error_message = "PHPMailer error for recipient {$to}: " . $m->ErrorInfo;
    _write_mail_log($logDir, $error_message);
    error_log($error_message); // Also log to standard PHP error log
    return false;
  }
}