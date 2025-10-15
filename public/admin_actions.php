<?php
// public/admin_actions.php
// Gère les actions manuelles depuis le tableau de bord admin.

session_start();
// 1. Sécurité : Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_ok']) || $_SESSION['admin_ok'] !== true) {
    http_response_code(403);
    exit('Accès interdit.');
}

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/sendmail.php';

$action = $_GET['action'] ?? '';
$now = time();

// Action de réinitialisation globale (ne nécessite pas d'ID)
if ($action === 'global_reset') {
    try {
        $db = new PDO('sqlite:' . $config['db_file']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Vider la table des attestations
        $db->exec('DELETE FROM attestations');

        // 2. Supprimer tous les fichiers dans le répertoire de stockage
        $storageDir = rtrim($config['storage_dir'], '/');
        $files = glob($storageDir . '/*');
        foreach ($files as $file) {
            // S'assurer de ne pas supprimer des sous-répertoires ou des fichiers cachés importants
            if (is_file($file) && basename($file) !== '.gitkeep') {
                @unlink($file);
            }
        }
        
        header('Location: admin.php?action_success=1');
        exit;

    } catch (Exception $e) {
        error_log("Global reset failed: " . $e->getMessage());
        header('Location: admin.php?error=Exception_GlobalReset');
        exit;
    }
}

// Actions nécessitant un ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0 || !in_array($action, ['delete', 'remind'])) {
    header('Location: admin.php?error=InvalidAction');
    exit;
}

try {
    $db = new PDO('sqlite:' . $config['db_file']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare('SELECT * FROM attestations WHERE id = ?');
    $stmt->execute([$id]);
    $attestation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attestation) {
        header('Location: admin.php?error=NotFound');
        exit;
    }

    // 2. Exécuter l'action demandée
    switch ($action) {
        case 'delete':
            // Suppression physique du fichier
            $filepath = rtrim($config['storage_dir'], '/') . '/' . $attestation['filename'];
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
            // Marquage en DB
            $upd = $db->prepare('UPDATE attestations SET deleted_at = ? WHERE id = ?');
            $upd->execute([$now, $id]);
            break;

        case 'remind':
            $expiry_date_formatted = date('d/m/Y', $attestation['expiry_at']);
            $site_link = rtrim($config['site_base_url'] ?? '', '/') . '/';
            $subject = "Rappel : Votre attestation d'honorabilité";
            $body = <<<EOT
Bonjour {$attestation['prenom']} {$attestation['nom']},

Ceci est un rappel concernant votre attestation d'honorabilité.
Elle est valide jusqu'au {$expiry_date_formatted}.

Si vous ne l'avez pas déjà fait, nous vous invitons à préparer et déposer votre nouvelle attestation via le lien ci-dessous pour assurer la continuité de votre engagement.
{$site_link}

Cordialement,
L'équipe de l'APEL
EOT;

            if (sendMail($attestation['parent_email'], $subject, $body, $config)) {
                $upd = $db->prepare('UPDATE attestations SET reminder_sent = 1 WHERE id = ?');
                $upd->execute([$id]);
            } else {
                 header('Location: admin.php?error=MailFailed');
                 exit;
            }
            break;
    }

} catch (Exception $e) {
    error_log("Admin action failed: " . $e->getMessage());
    header('Location: admin.php?error=Exception');
    exit;
}

// 3. Rediriger vers le tableau de bord
header('Location: admin.php?action_success=1');
exit;