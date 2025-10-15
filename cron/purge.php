<?php
// cron/purge.php
// Tâche CRON pour purger les anciennes attestations soft-deleted

require_once __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config.php';
$dbfile = $config['db_file'];
if (!file_exists($dbfile)) {
    echo "Erreur: Fichier de base de données introuvable.\n";
    exit(1);
}
$db = new PDO('sqlite:' . $dbfile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$retention_days = $config['purge_deleted_after_days'] ?? 365;
if ($retention_days <= 0) {
    echo "La purge est désactivée (purge_deleted_after_days <= 0).\n";
    exit(0);
}

$purge_before_timestamp = time() - ($retention_days * 86400);

// Sélectionner les attestations à purger
$stmt = $db->prepare('SELECT id, filename FROM attestations WHERE deleted_at IS NOT NULL AND deleted_at < ?');
$stmt->execute([$purge_before_timestamp]);
$attestations_to_purge = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($attestations_to_purge)) {
    echo "Aucune attestation à purger.\n";
    exit(0);
}

echo "Début de la purge de " . count($attestations_to_purge) . " attestation(s)...\n";

$purged_count = 0;
$error_count = 0;

foreach ($attestations_to_purge as $attestation) {
    $db->beginTransaction();
    try {
        // 1. Supprimer le fichier physique
        $filepath = $config['storage_dir'] . '/' . $attestation['filename'];
        if (file_exists($filepath)) {
            if (unlink($filepath)) {
                echo "Fichier " . $attestation['filename'] . " supprimé.\n";
            } else {
                throw new Exception("Impossible de supprimer le fichier " . $attestation['filename']);
            }
        } else {
            echo "Fichier " . $attestation['filename'] . " non trouvé, suppression de l'entrée DB uniquement.\n";
        }

        // 2. Supprimer l'enregistrement de la base de données
        $delete_stmt = $db->prepare('DELETE FROM attestations WHERE id = ?');
        $delete_stmt->execute([$attestation['id']]);

        $db->commit();
        $purged_count++;
        echo "Enregistrement ID " . $attestation['id'] . " purgé de la base de données.\n";

    } catch (Exception $e) {
        $db->rollBack();
        echo "Erreur lors de la purge de l'attestation ID " . $attestation['id'] . ": " . $e->getMessage() . "\n";
        $error_count++;
    }
}

echo "Purge terminée.\n";
echo "Total purgé: " . $purged_count . "\n";
echo "Erreurs: " . $error_count . "\n";

exit(0);