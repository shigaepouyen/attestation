<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// public/upload.php
// Traitement du formulaire d'upload d'attestation.

session_start();
header('Content-Type: text/html; charset=utf-8');

// -------------------------------
// Fonctions utilitaires
// -------------------------------

/**
 * Affiche une page d'erreur formatée et arrête le script.
 * @param string $msg Le message d'erreur à afficher.
 * @param int $httpCode Le code de statut HTTP.
 */
function fail($msg, $httpCode = 400) {
    http_response_code($httpCode);
    error_log("[upload.php] ERREUR: " . $msg);
    ?>
    <!doctype html>
    <html lang="fr">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Erreur lors de l'envoi</title>
    <style>
      :root {
        --danger-color: #dc3545;
        --background-color: #f8f9fa;
        --text-color: #212529;
        --card-background: #ffffff;
        --border-color: #dee2e6;
        --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        --border-radius: 0.5rem;
        --box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      }
      body {
        font-family: var(--font-sans);
        background: var(--background-color);
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        color: var(--text-color);
      }
      .card {
        background: var(--card-background);
        padding: 2.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        border: 1px solid var(--border-color);
        max-width: 600px;
        width: 90%;
        text-align: center;
      }
      h1 {
        color: var(--danger-color);
        margin-top: 0;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
      }
      .icon { width: 32px; height: 32px; }
      p { margin-bottom: 1.5rem; line-height: 1.6; }
      .btn {
        display: inline-block;
        background: var(--danger-color);
        color: #fff;
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        text-decoration: none;
        font-weight: 600;
        transition: background-color 0.2s;
      }
      .btn:hover { background-color: #c82333; }
    </style>
    </head>
    <body>
      <div class="card">
        <h1>
          <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
          Une erreur est survenue
        </h1>
        <p><?= htmlspecialchars($msg) ?></p>
        <a class="btn" href="index.php">Retourner au formulaire</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

function generate_uuidv4() {
    // Génère 16 octets (128 bits) de données aléatoires
    $data = random_bytes(16);
    // Définit le bit de version (4)
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Définit le bit de variante (RFC 4122)
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    // Retourne l'UUID formaté
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function write_log($path, $line) {
    try {
        @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // Ignorer silencieusement si le logging échoue
    }
}

// -------------------------------
// Chargement de la configuration
// -------------------------------
$config = require __DIR__ . '/../config.php';
if (empty($config['db_file']) || empty($config['storage_dir']) || empty($config['csv_path'])) {
    fail("Configuration du serveur incomplète. Veuillez vérifier le fichier config.php.", 500);
}
$logDir = dirname($config['csv_path']);
@mkdir($logDir, 0775, true);
$uploadLog = rtrim($logDir, '/') . '/upload.log';

// -------------------------------
// Vérifications de sécurité : CSRF et Honeypot
// -------------------------------
if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    write_log($uploadLog, "[" . date('c') . "] CSRF invalide depuis " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
    fail('La session a expiré ou le formulaire est invalide. Veuillez réessayer.');
}
if (!empty($_POST['website'])) {
    write_log($uploadLog, "[" . date('c') . "] Honeypot déclenché (bot) depuis " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
    fail('Soumission refusée (détection de bot).');
}

// -------------------------------
// Validation des champs
// -------------------------------
$nom_raw    = trim($_POST['nom'] ?? '');
$prenom_raw = trim($_POST['prenom'] ?? '');
$email_raw  = trim($_POST['email'] ?? '');

$parent_email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);

write_log($uploadLog, "[" . date('c') . "] Tentative d'upload par " . ($parent_email ?: 'NO_EMAIL') . " (nom={$nom_raw}, prenom={$prenom_raw}) IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));

if (empty($nom_raw) || empty($prenom_raw)) {
    fail('Le nom et le prénom sont requis.');
}
if (!$parent_email) {
    fail('Une adresse e-mail valide est obligatoire.');
}

// Nettoyage simple pour la base de données
$nom = htmlspecialchars($nom_raw, ENT_QUOTES, 'UTF-8');
$prenom = htmlspecialchars($prenom_raw, ENT_QUOTES, 'UTF-8');

// -------------------------------
// Validation du fichier
// -------------------------------
if (empty($_FILES['pdf']) || !is_uploaded_file($_FILES['pdf']['tmp_name'])) {
    fail('Aucun fichier PDF n\'a été envoyé.');
}
$file = $_FILES['pdf'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    fail('Erreur lors de l\'envoi du fichier (code ' . intval($file['error']) . ').');
}
$maxBytes = (int)($config['max_size_mb'] ?? 10) * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    fail('Le fichier est trop volumineux (max ' . intval($config['max_size_mb']) . ' Mo).');
}
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $config['allowed_ext'] ?? ['pdf'])) {
    fail('Seuls les fichiers avec l\'extension .pdf sont autorisés.');
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $config['allowed_mime'] ?? ['application/pdf'])) {
    fail('Le type de fichier n\'est pas autorisé. Seuls les PDF sont acceptés.');
}

// -------------------------------
// Préparation du stockage
// -------------------------------
$storageDir = $config['storage_dir'];
@mkdir($storageDir, 0775, true);
if (!is_dir($storageDir) || !is_writable($storageDir)) {
    fail('Erreur serveur : le stockage est impossible (vérifier les permissions).', 500);
}

// Génération d'un nom de fichier unique et non devinable
$uuid = generate_uuidv4();
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$finalName = "{$uuid}.{$fileExtension}";
$destPath = rtrim($storageDir, '/') . '/' . $finalName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    fail('Erreur interne : impossible de déplacer le fichier.', 500);
}
@chmod($destPath, 0640);
write_log($uploadLog, "[" . date('c') . "] Fichier stocké: " . $destPath);

// -------------------------------
// Mise à jour de la base de données (SQLite)
// -------------------------------
try {
    $db = new PDO('sqlite:' . $config['db_file']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $now = time();
    $expiry = strtotime('+6 months', $now);
    $token = bin2hex(random_bytes(24));

    $db->beginTransaction();
    // On cherche une attestation existante pour cet email, même si elle a été supprimée
    $stmt = $db->prepare('SELECT * FROM attestations WHERE parent_email = ?');
    $stmt->execute([$parent_email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if (!empty($existing['filename'])) {
            $oldPath = $storageDir . '/' . $existing['filename'];
            if (file_exists($oldPath)) @unlink($oldPath);
        }
        $upd = $db->prepare('UPDATE attestations SET nom=?, prenom=?, filename=?, token=?, uploaded_at=?, expiry_at=?, reminder_sent=0, deleted_at=NULL WHERE id=?');
        $upd->execute([$nom, $prenom, basename($destPath), $token, $now, $expiry, $existing['id']]);
        $recordId = $existing['id'];
    } else {
        $ins = $db->prepare('INSERT INTO attestations (nom,prenom,parent_email,filename,token,uploaded_at,expiry_at) VALUES (?,?,?,?,?,?,?)');
        $ins->execute([$nom, $prenom, $parent_email, basename($destPath), $token, $now, $expiry]);
        $recordId = $db->lastInsertId();
    }
    $db->commit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    @unlink($destPath);
    write_log($uploadLog, "[" . date('c') . "] Erreur DB: " . $e->getMessage());
    fail('Erreur serveur lors de l\'enregistrement des données.', 500);
}

// -------------------------------
// Journalisation CSV pour audit
// -------------------------------
try {
    if (($fp = fopen($config['csv_path'], 'ab'))) {
        fputcsv($fp, [date('c'), $recordId, $nom, $prenom, $parent_email, basename($destPath)], ';');
        fclose($fp);
    }
} catch (Throwable $e) {
    write_log($uploadLog, "[" . date('c') . "] Erreur écriture CSV: " . $e->getMessage());
}

// -------------------------------
// Envoi de l'e-mail de confirmation
// -------------------------------
require_once __DIR__ . '/../lib/sendmail.php';

$retention_days = (int)($config['purge_deleted_after_days'] ?? 365);
$retention_text = "{$retention_days} jours";
if ($retention_days === 365) {
    $retention_text = "1 an";
}

$expiry_date_formatted = date('d/m/Y', $expiry);
$site_link = rtrim($config['site_base_url'] ?? '', '/') . '/';
$subject = "Confirmation de dépôt de votre attestation d'honorabilité";
$body = <<<EOT
Bonjour {$prenom} {$nom},

Nous vous confirmons la bonne réception de votre attestation d'honorabilité.
Elle a été enregistrée avec succès et est valide jusqu'au {$expiry_date_formatted}.

Pour toute nouvelle démarche, vous pouvez utiliser le lien suivant :
{$site_link}

Notez que conformément à notre politique de confidentialité, ce document sera conservé pendant {$retention_text} après sa date d'expiration, puis sera définitivement supprimé de nos systèmes.

Nous vous remercions pour votre coopération.

Cordialement,
L'équipe de l'APEL
EOT;

if (!sendMail($parent_email, $subject, $body, $config)) {
    write_log($uploadLog, "[" . date('c') . "] Echec de l'envoi de l'email de confirmation à " . $parent_email);
}


// -------------------------------
// Page de confirmation
// -------------------------------
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Attestation enregistrée !</title>
<style>
  :root {
    --success-color: #198754;
    --primary-color: #007bff;
    --background-color: #f8f9fa;
    --text-color: #212529;
    --card-background: #ffffff;
    --border-color: #dee2e6;
    --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --border-radius: 0.5rem;
    --box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  }
  body {
    font-family: var(--font-sans);
    background: var(--background-color);
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    color: var(--text-color);
  }
  .card {
    background: var(--card-background);
    padding: 3rem;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    border-top: 5px solid var(--success-color);
    max-width: 650px;
    width: 90%;
    text-align: center;
  }
  .icon {
    width: 60px;
    height: 60px;
    color: var(--success-color);
    margin-bottom: 1.5rem;
  }
  h1 {
    color: var(--success-color);
    margin-top: 0;
    margin-bottom: 1rem;
    font-size: 2rem;
  }
  .details {
    text-align: left;
    margin: 2rem 0;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
  }
  .details p { margin: 0.5rem 0; }
  .details strong { color: #343a40; }
  .actions { margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; }
  .btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    border-radius: var(--border-radius);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
  }
  .btn-primary { background: var(--primary-color); color: #fff; }
  .btn-primary:hover { background: #0056b3; }
  .btn-secondary { background: #e9ecef; color: #495057; border: 1px solid #ced4da; }
  .btn-secondary:hover { background: #d3d9df; }
</style>
</head>
<body>
  <div class="card">
    <svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
    <h1>Merci, c'est enregistré !</h1>
    <p>Votre attestation a bien été reçue et stockée de manière sécurisée.</p>
    <p style="font-size: 0.9rem; color: #6c757d;">Un e-mail de confirmation vient de vous être envoyé. S'il n'apparaît pas dans votre boîte de réception, pensez à vérifier votre dossier de courriers indésirables (spam).</p>

    <div class="details">
      <p><strong>Nom du fichier :</strong> <?= htmlspecialchars(basename($destPath), ENT_QUOTES, 'UTF-8') ?></p>
      <p><strong>Déposé le :</strong> <?= date('d/m/Y à H:i', $now) ?></p>
      <p><strong>Valide jusqu’au :</strong> <?= date('d/m/Y', $expiry) ?> (6 mois)</p>
      <p style="margin-top: 1rem; font-size: 0.9rem; color: #6c757d;">Conformément à notre politique, ce document sera conservé pendant <?= htmlspecialchars($retention_text, ENT_QUOTES, 'UTF-8') ?> après sa date d'expiration avant d'être définitivement supprimé de nos systèmes.</p>
    </div>

    <div class="actions">
      <a class="btn btn-primary" href="index.php">Retour au formulaire</a>
    </div>
  </div>
</body>
</html>
