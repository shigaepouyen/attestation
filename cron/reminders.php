<?php
// cron/reminders.php
// Ce script gère l'envoi des rappels et la suppression des attestations expirées.

$config = require __DIR__ . '/../config.php';
$db = new PDO('sqlite:' . $config['db_file']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once __DIR__ . '/../lib/sendmail.php';

$now = time();
$log_prefix = "[" . date('c') . "] ";

// --- 1. Envoi des pré-rappels (15 jours avant expiration) ---
$reminder_days = 15;
$reminder_threshold = strtotime("+{$reminder_days} days", $now);

$stmt_prereminder = $db->prepare(
    'SELECT * FROM attestations WHERE expiry_at > ? AND expiry_at <= ? AND reminder_sent = 0 AND deleted_at IS NULL'
);
$stmt_prereminder->execute([$now, $reminder_threshold]);
$prereminder_rows = $stmt_prereminder->fetchAll(PDO::FETCH_ASSOC);

echo $log_prefix . "Found " . count($prereminder_rows) . " attestations for pre-reminder.\n";

foreach ($prereminder_rows as $r) {
    $to = $r['parent_email'];
    $expiry_date_formatted = date('d/m/Y', $r['expiry_at']);
    $site_link = rtrim($config['site_base_url'] ?? '', '/') . '/';

    $subject = "Rappel : Votre attestation d'honorabilité expire bientôt";
    $body = <<<EOT
Bonjour {$r['prenom']} {$r['nom']},

Ceci est un rappel automatique pour vous informer que votre attestation d'honorabilité expire le {$expiry_date_formatted}.
Pour assurer la continuité, nous vous invitons à déposer une nouvelle attestation dès que possible.

Vous pouvez déposer votre nouvelle attestation via ce lien :
{$site_link}

Cordialement,
L'équipe de l'APEL
EOT;

    if (sendMail($to, $subject, $body, $config)) {
        $upd = $db->prepare('UPDATE attestations SET reminder_sent = 1 WHERE id = ?');
        $upd->execute([$r['id']]);
        echo $log_prefix . "Pre-reminder sent to {$to} for attestation ID {$r['id']}.\n";
    } else {
        echo $log_prefix . "ERROR: Failed to send pre-reminder to {$to} for attestation ID {$r['id']}.\n";
    }
}


// --- 2. Traitement des attestations expirées (suppression) ---
$stmt_expired = $db->prepare('SELECT * FROM attestations WHERE expiry_at <= ? AND deleted_at IS NULL');
$stmt_expired->execute([$now]);
$expired_rows = $stmt_expired->fetchAll(PDO::FETCH_ASSOC);

echo $log_prefix . "Found " . count($expired_rows) . " expired attestations for deletion.\n";

foreach ($expired_rows as $r) {
    $to = $r['parent_email'];
    $site_link = rtrim($config['site_base_url'] ?? '', '/') . '/';

    $subject = "Votre attestation d'honorabilité a expiré";
    $body = <<<EOT
Bonjour {$r['prenom']} {$r['nom']},

L'attestation d'honorabilité que vous aviez déposée est arrivée à échéance.
Conformément à nos procédures, le fichier a été supprimé de nos serveurs.

Pour renouveler votre attestation, veuillez utiliser le lien suivant :
{$site_link}

Cordialement,
L'équipe de l'APEL
EOT;

    sendMail($to, $subject, $body, $config);

    // Suppression physique du fichier
    $filepath = rtrim($config['storage_dir'], '/') . '/' . $r['filename'];
    if (file_exists($filepath)) {
        @unlink($filepath);
    }

    // Marquage en base de données comme supprimé
    $upd = $db->prepare('UPDATE attestations SET deleted_at = ? WHERE id = ?');
    $upd->execute([$now, $r['id']]);

    echo $log_prefix . "Expired attestation ID {$r['id']} for {$to} has been processed and deleted.\n";
}

echo $log_prefix . "Cron job finished.\n";