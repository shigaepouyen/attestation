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

function clean_label($s) {
    $s = trim((string)$s);
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = preg_replace('/[^A-Za-z0-9 \-_]/', '', $s);
    $s = preg_replace('/\s+/', '_', $s);
    return $s === '' ? 'Inconnu' : trim($s, '_-');
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
$nom_raw    = $_POST['nom'] ?? '';
$prenom_raw = $_POST['prenom'] ?? '';
$email_raw  = $_POST['email'] ?? '';

$nom    = clean_label($nom_raw);
$prenom = clean_label($prenom_raw);
$parent_email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);

write_log($uploadLog, "[" . date('c') . "] Tentative d'upload par " . ($parent_email ?: 'NO_EMAIL') . " (nom={$nom_raw}, prenom={$prenom_raw}) IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));

if (!$nom || !$prenom) fail('Le nom et le prénom sont requis.');
if (!$parent_email) fail('Une adresse e-mail valide est obligatoire.');

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

$date = date('Ymd');
$baseName = "{$nom}_{$prenom}_{$date}";
$finalName = $baseName . ($config['filename_suffix'] ?? '_AttestationHonorabilite.pdf');
$destPath = $storageDir . '/' . $finalName;
$counter = 1;
while (file_exists($destPath)) {
    $destPath = $storageDir . '/' . $baseName . "_{$counter}" . ($config['filename_suffix'] ?? '_AttestationHonorabilite.pdf');
    $counter++;
}

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
    $stmt = $db->prepare('SELECT * FROM attestations WHERE parent_email = ? AND deleted_at IS NULL');
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

    <div class="details">
      <p><strong>Nom du fichier :</strong> <?= htmlspecialchars(basename($destPath), ENT_QUOTES, 'UTF-8') ?></p>
      <p><strong>Déposé le :</strong> <?= date('d/m/Y à H:i', $now) ?></p>
      <p><strong>Valide jusqu’au :</strong> <?= date('d/m/Y', $expiry) ?> (6 mois)</p>
    </div>

    <div class="actions">
      <a class="btn btn-primary" href="index.php">Retour au formulaire</a>
      <a class="btn btn-secondary" href="javascript:window.print()">Imprimer cette page</a>
    </div>
  </div>
</body>
</html>
