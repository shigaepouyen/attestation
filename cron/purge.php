<?php
// cron/purge.php
// Tâche CRON pour purger les anciennes attestations soft-deleted

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config.php';

// Configuration et création du répertoire de logs
$log_dir = $config['log_dir'] ?? __DIR__ . '/../storage/logs';
if (!is_dir($log_dir)) {
    // Tente de créer le répertoire, supprime le umask pour un contrôle total des permissions
    $old_umask = umask(0);
    mkdir($log_dir, 0755, true);
    umask($old_umask);
}

$log_file = $log_dir . '/purge.log';
// Fonction de logging
function log_message($message)
{
    global $log_file;
    // Ajoute un timestamp et le message au fichier de log
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
    // Affiche aussi le message dans la console pour la compatibilité
    echo $message . "\n";
}

log_message("--- Début du script de purge ---");

$dbfile = $config['db_file'];
if (!file_exists($dbfile)) {
    log_message("Erreur: Fichier de base de données introuvable.");
    exit(1);
}
$db = new PDO('sqlite:' . $dbfile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$retention_days = $config['purge_deleted_after_days'] ?? 365;
if ($retention_days <= 0) {
    log_message("La purge est désactivée (purge_deleted_after_days <= 0).");
    exit(0);
}

$purge_before_timestamp = time() - ($retention_days * 86400);

// Sélectionner les attestations à purger
$stmt = $db->prepare('SELECT id, filename FROM attestations WHERE deleted_at IS NOT NULL AND deleted_at < ?');
$stmt->execute([$purge_before_timestamp]);
$attestations_to_purge = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($attestations_to_purge)) {
    log_message("Aucune attestation à purger.");
    exit(0);
}

log_message("Début de la purge de " . count($attestations_to_purge) . " attestation(s)...");

$purged_count = 0;
$error_count = 0;

foreach ($attestations_to_purge as $attestation) {
    $db->beginTransaction();
    try {
        // 1. Supprimer le fichier physique
        $filepath = $config['storage_dir'] . '/' . $attestation['filename'];
        if (file_exists($filepath)) {
            if (unlink($filepath)) {
                log_message("Fichier " . $attestation['filename'] . " supprimé.");
            } else {
                throw new Exception("Impossible de supprimer le fichier " . $attestation['filename']);
            }
        } else {
            log_message("Fichier " . $attestation['filename'] . " non trouvé, suppression de l'entrée DB uniquement.");
        }

        // 2. Supprimer l'enregistrement de la base de données
        $delete_stmt = $db->prepare('DELETE FROM attestations WHERE id = ?');
        $delete_stmt->execute([$attestation['id']]);

        $db->commit();
        $purged_count++;
        log_message("Enregistrement ID " . $attestation['id'] . " purgé de la base de données.");

    } catch (Exception $e) {
        $db->rollBack();
        log_message("Erreur lors de la purge de l'attestation ID " . $attestation['id'] . ": " . $e->getMessage());
        $error_count++;
    }
}

log_message("Purge terminée.");
log_message("Total purgé: " . $purged_count);
log_message("Erreurs: " . $error_count);
log_message("--- Fin du script de purge ---");

exit(0);