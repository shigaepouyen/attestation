<?php
// cron/weekly_digest.php

require __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';

date_default_timezone_set('Europe/Paris');

// 1) Logging minimaliste et sûr
$logFile = rtrim($config['storage_dir'] ?? (__DIR__ . '/../storage/logs'), '/').'/weekly_digest.log';
if (!is_dir(dirname($logFile))) {
    @mkdir(dirname($logFile), 0775, true);
}
function logLine(string $line) : void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    // FILE_APPEND + LOCK_EX pour éviter les logs qui se marchent dessus
    @file_put_contents($logFile, "[$ts] $line\n", FILE_APPEND | LOCK_EX);
}

// 2) Convertir les warnings en exceptions (genre file_put_contents qui échoue)
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // 3) DB
    $db = new PDO('sqlite:' . $config['db_file']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    require_once __DIR__ . '/../lib/sendmail.php';

    // 4) Check nouveaux dépôts
    $since = strtotime('-7 days');
    $checkNewStmt = $db->prepare('SELECT COUNT(*) FROM attestations WHERE uploaded_at >= ? AND deleted_at IS NULL');
    $checkNewStmt->execute([$since]);
    $newSubmissionsCount = (int)$checkNewStmt->fetchColumn();

    if ($newSubmissionsCount === 0) {
        logLine("Aucun nouveau dépôt cette semaine. Aucun e-mail envoyé.");
        // Optionnel: echo pour le mail cron
        echo "Aucun nouveau dépôt cette semaine. Aucun e-mail envoyé.\n";
        exit(0);
    }

    // 5) Générer et enregistrer le master token
    // Ce script est la seule source de vérité pour le token.
    // Il écrase le token précédent à chaque envoi d'email.
    $masterToken = bin2hex(random_bytes(32));
    $tokenPayload = [
        'token'  => $masterToken,
        'expiry' => time() + (14 * 24 * 60 * 60), // Le lien reste valide 14 jours
    ];

    // Le chemin vers le fichier du token maître
    $masterTokenFile = __DIR__ . '/../storage/master_token.txt';

    // Écraser l'ancien token avec le nouveau (pas de FILE_APPEND)
    if (@file_put_contents($masterTokenFile, json_encode($tokenPayload, JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        throw new Exception("Impossible d'écrire dans le fichier master_token.txt.");
    }

    // 6) Email
    $subject = "[APEL St Jo] Attestation honorabilité - " . date('Y-m-d');
    $reportLink = rtrim($config['site_base_url'], '/') . '/rapport.php?token=' . $masterToken;
    $directorTitle = $config['director_title'] ?? '';
    $to = $config['director_email'];

    $body = "Bonjour " . $directorTitle . ",\n\n";
    $body .= "Il y a eu " . $newSubmissionsCount . " nouveau(x) dépôt(s) d'attestation cette semaine.\n\n";
    $body .= "Consultez la liste complète et à jour des attestations valides via le lien sécurisé :\n\n";
    $body .= "$reportLink\n\n";
    $body .= "Ce lien est valable 14 jours.\n\n";
    $body .= "Cordialement,\nAPEL Saint-Joseph\n";

    $sent = sendMail($to, $subject, $body, $config);

    if (!$sent) {
        // échec d’envoi: on logue sévèrement et on remonte un code d’erreur
        logLine("ERREUR: échec d'envoi de l'e-mail à $to. Compte=$newSubmissionsCount");
        echo "Echec d'envoi de l'e-mail.\n";
        exit(2);
    }

    logLine("OK: e-mail envoyé à $to. Compte=$newSubmissionsCount Lien=$reportLink");
    echo "E-mail envoyé à $to\n";
    exit(0);

} catch (Throwable $e) {
    // 7) Gestion des erreurs fatales
    logLine("FATAL: ".$e->getMessage());
    // Pour debug cron: on imprime aussi
    echo "Erreur: ".$e->getMessage()."\n";
    exit(1);
} finally {
    restore_error_handler();
}