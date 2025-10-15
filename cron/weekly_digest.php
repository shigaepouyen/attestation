<?php
// cron/weekly_digest.php
// Envoie un e-mail hebdomadaire à la direction avec un lien sécurisé
// vers une page web listant toutes les attestations valides.

require __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$db = new PDO('sqlite:' . $config['db_file']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require_once __DIR__ . '/../lib/sendmail.php';

// Étape 1: Vérifier s'il y a eu de nouveaux dépôts cette semaine.
$since = strtotime('-7 days');
$checkNewStmt = $db->prepare('SELECT COUNT(*) FROM attestations WHERE uploaded_at >= ?');
$checkNewStmt->execute([$since]);
$newSubmissionsCount = (int)$checkNewStmt->fetchColumn();

if ($newSubmissionsCount === 0) {
    echo "Aucun nouveau depot cette semaine. Aucun e-mail envoye.\n";
    exit;
}

// Étape 2: Générer un "master token" unique pour l'accès à la page de rapport.
// On le stocke dans un fichier temporaire pour que la page de rapport puisse le vérifier.
$masterToken = bin2hex(random_bytes(32));
$tokenFile = dirname($config['storage_dir']) . '/master_token.txt';
// Le token est valable 2 semaines pour laisser le temps de consulter le rapport.
$tokenData = json_encode(['token' => $masterToken, 'expiry' => time() + (14 * 24 * 60 * 60)]);
file_put_contents($tokenFile, $tokenData);

// Étape 3: Préparer et envoyer l'e-mail.
$subject = "[APEL St Jo] Attestation honorabilité - " . date('Y-m-d');
$reportLink = rtrim($config['site_base_url'], '/') . '/rapport.php?token=' . $masterToken;

$body = "Bonjour Madame la Directrice,\n\n";
$body .= "Il y a eu " . $newSubmissionsCount . " nouveau(x) dépôt(s) d'attestation cette semaine.\n\n";
$body .= "Vous pouvez consulter la liste complète et à jour de toutes les attestations valides en cliquant sur le lien sécurisé ci-dessous :\n\n";
$body .= "$reportLink\n\n";
$body .= "Ce lien est valable 14 jours.\n";
$body .= "\nCordialement,\nAPEL Saint-Joseph\n";

$sent = sendMail($config['director_email'], $subject, $body, $config);

echo "E-mail de rapport envoye a {$config['director_email']} (sent=" . ($sent ? 1 : 0) . ").\n";

