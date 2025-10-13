<?php
// cron/reminders.php
require __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$db = new PDO('sqlite:' . $config['db_file']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require_once __DIR__ . '/../lib/sendmail.php';

$now = time();
$stmt = $db->prepare('SELECT * FROM attestations WHERE expiry_at <= ? AND reminder_sent = 0 AND deleted_at IS NULL');
$stmt->execute([$now]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) { echo "No reminders\n"; exit; }

foreach ($rows as $r) {
  $to = $r['parent_email'];
  $subject = "Votre attestation est expirée – APEL Saint-Joseph";
  $body = "Bonjour,\n\nL'attestation que vous avez déposée pour {$r['prenom']} {$r['nom']} est arrivée à échéance (6 mois) et a été supprimée du dépôt.\nMerci de déposer une nouvelle attestation ici : {$config['site_base_url']}/\n\nCordialement,\nAPEL Saint-Joseph\n";

  $sent = sendMail($to, $subject, $body, $config);

  // suppression physique + marquage DB (même si mail échoue, on supprime pour rester conforme)
  $path = __DIR__ . '/../' . trim($config['storage_dir'],'/') . '/' . $r['filename'];
  if (file_exists($path)) @unlink($path);

  $upd = $db->prepare('UPDATE attestations SET reminder_sent=1, deleted_at=? WHERE id=?');
  $upd->execute([time(), $r['id']]);

  echo "Reminder processed for {$r['parent_email']} (mail_sent=" . ($sent?1:0) . ")\n";
}