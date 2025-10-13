<?php
// cron/weekly_digest.php
require __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$db = new PDO('sqlite:' . $config['db_file']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require_once __DIR__ . '/../lib/sendmail.php';

$since = strtotime('-7 days');
$stmt = $db->prepare('SELECT * FROM attestations WHERE uploaded_at >= ? AND deleted_at IS NULL');
$stmt->execute([$since]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) { echo "No new this week\n"; exit; }

$subject = "Livraison hebdomadaire – Attestations APEL Saint-Joseph – " . date('Y-m-d');
$body = "Bonjour Madame la Directrice,\n\nVoici les nouvelles attestations déposées cette semaine :\n\n";

foreach ($rows as $r) {
  $link = rtrim($config['site_base_url'],'/').'/download.php?token='.$r['token'];
  $body .= "- {$r['nom']} {$r['prenom']} — déposée le ".date('Y-m-d', $r['uploaded_at'])." — $link\n";
}

$body .= "\nCordialement,\nAPEL Saint-Joseph\n";

$sent = sendMail($config['director_email'], $subject, $body, $config);
echo "Digest sent to {$config['director_email']} (sent=" . ($sent?1:0) . "), count=" . count($rows) . "\n";