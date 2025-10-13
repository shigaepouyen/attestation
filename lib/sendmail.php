<?php
// lib/sendmail.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

function sendMail($to, $subject, $body, $cfg) {
  // Si SMTP désactivé, fallback vers mail()
  if (empty($cfg['smtp']['enabled'])) {
    $headers = "From: {$cfg['smtp']['from_name']} <{$cfg['smtp']['from_email']}>\r\n";
    return @mail($to, $subject, $body, $headers);
  }

  $m = new PHPMailer(true);
  try {
    $m->isSMTP();
    $m->Host = $cfg['smtp']['host'];
    $m->SMTPAuth = true;
    $m->Username = $cfg['smtp']['user'];
    $m->Password = $cfg['smtp']['pass'];
    $m->SMTPSecure = $cfg['smtp']['secure']; // 'tls'
    $m->Port = $cfg['smtp']['port'];
    $m->setFrom($cfg['smtp']['from_email'], $cfg['smtp']['from_name']);
    $m->addAddress($to);
    $m->Subject = $subject;
    $m->Body    = $body;
    $m->CharSet = 'UTF-8';
    $m->send();
    return true;
  } catch (Exception $e) {
    error_log("Mailer Error: " . $m->ErrorInfo);
    return false;
  }
}