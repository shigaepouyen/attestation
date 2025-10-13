<?php
// public/upload.php
// Traitement du formulaire d'upload d'attestation.
// - clé unique = parent_email
// - renomme en NOM_Prenom_YYYYMMDD_AttestationHonorabilite.pdf
// - stocke fichier hors webroot (config['storage_dir'])
// - upsert SQLite (config['db_file'])
// - journalisation CSV + log détaillé
// - affiche une jolie page de confirmation HTML (responsive)
//
// IMPORTANT : ce script suppose que config.php existe et que db/create_db.php a été exécuté.

session_start();
header('Content-Type: text/html; charset=utf-8');

// -------------------------------
// Helpers
// -------------------------------
function fail($msg, $httpCode = 400) {
    http_response_code($httpCode);
    // message minimal (HTML)
    echo "<!doctype html><html lang='fr'><head><meta charset='utf-8'><title>Erreur</title></head><body><h1>Erreur</h1><p>" . htmlspecialchars($msg) . "</p></body></html>";
    // log to error log as well
    error_log("[upload.php] ERROR: " . $msg);
    exit;
}

function clean_label($s) {
    $s = trim((string)$s);
    // transliterate accents to ASCII (best-effort)
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    // keep letters, numbers, spaces, dash and underscore
    $s = preg_replace('/[^A-Za-z0-9 \-_]/', '', $s);
    $s = preg_replace('/\s+/', '_', $s);
    return $s === '' ? 'Inconnu' : trim($s, '_-');
}

function write_log($path, $line) {
    try {
        @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) {
        // best-effort: if logging fails, drop silently
    }
}

// -------------------------------
// Load config
// -------------------------------
$config = require __DIR__ . '/../config.php';

// validate config entries
if (empty($config['db_file']) || empty($config['storage_dir']) || empty($config['csv_path'])) {
    fail("Configuration incomplète. Vérifie config.php", 500);
}

// prepare log paths
$logDir = dirname($config['csv_path']);
@mkdir($logDir, 0775, true);
$uploadLog = rtrim($logDir, '/') . '/upload.log';

// -------------------------------
// Basic checks: CSRF and honeypot
// -------------------------------
if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    write_log($uploadLog, "[" . date('c') . "] CSRF invalide from " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
    fail('CSRF invalide');
}
if (!empty($_POST['website'])) { // honeypot field
    write_log($uploadLog, "[" . date('c') . "] Honeypot triggered (bot) from " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
    fail('Soumission refusée (bot détecté)');
}

// -------------------------------
// Validate required fields
// -------------------------------
$nom_raw    = $_POST['nom'] ?? '';
$prenom_raw = $_POST['prenom'] ?? '';
$email_raw  = $_POST['email'] ?? '';

$nom    = clean_label($nom_raw);
$prenom = clean_label($prenom_raw);
$parent_email = filter_var($email_raw, FILTER_VALIDATE_EMAIL);

write_log($uploadLog, "[" . date('c') . "] Upload attempt by " . ($parent_email ?: 'NO_EMAIL') . " (nom={$nom_raw}, prenom={$prenom_raw}) IP=" . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));

if (!$nom || !$prenom) {
    write_log($uploadLog, "[" . date('c') . "] Missing nom/prenom");
    fail('Nom et prénom sont requis');
}
if (!$parent_email) {
    write_log($uploadLog, "[" . date('c') . "] Email invalide ou manquant: " . ($email_raw ?? 'NULL'));
    fail('E-mail parent obligatoire et doit être valide');
}

// -------------------------------
// File upload checks
// -------------------------------
if (empty($_FILES['pdf']) || !is_uploaded_file($_FILES['pdf']['tmp_name'])) {
    write_log($uploadLog, "[" . date('c') . "] Aucun fichier PDF reçu");
    fail('Fichier PDF manquant');
}

$file = $_FILES['pdf'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    write_log($uploadLog, "[" . date('c') . "] Erreur upload code=" . $file['error']);
    fail('Erreur lors de l\'upload (code ' . intval($file['error']) . ')');
}

// size check
$maxBytes = (int)($config['max_size_mb'] ?? 10) * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    write_log($uploadLog, "[" . date('c') . "] Fichier trop volumineux: " . $file['size']);
    fail('Fichier trop volumineux (max ' . intval($config['max_size_mb']) . ' MB)');
}

// extension & mime check
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $config['allowed_ext'] ?? ['pdf'])) {
    write_log($uploadLog, "[" . date('c') . "] Extension non autorisée: " . $ext);
    fail('Extension non autorisée');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, $config['allowed_mime'] ?? ['application/pdf'])) {
    write_log($uploadLog, "[" . date('c') . "] MIME non autorisé: " . $mime);
    fail('Type de fichier non autorisé');
}

// -------------------------------
// Prepare storage
// -------------------------------
$storageDir = $config['storage_dir'];
@mkdir($storageDir, 0775, true);
if (!is_dir($storageDir) || !is_writable($storageDir)) {
    write_log($uploadLog, "[" . date('c') . "] Storage dir error: " . $storageDir);
    fail('Erreur serveur : stockage impossible (vérifier permissions)');
}

// build filename
$date = date('Ymd');
$baseName = "{$nom}_{$prenom}_{$date}";
$finalName = $baseName . ($config['filename_suffix'] ?? '_AttestationHonorabilite.pdf');
$destPath = $storageDir . '/' . $finalName;

// avoid collisions
$counter = 1;
while (file_exists($destPath)) {
    $destPath = $storageDir . '/' . $baseName . "_{$counter}" . ($config['filename_suffix'] ?? '_AttestationHonorabilite.pdf');
    $counter++;
}

// move uploaded file to temp then final
$tmp = sys_get_temp_dir() . '/' . uniqid('attest_', true) . '.pdf';
if (!move_uploaded_file($file['tmp_name'], $tmp)) {
    write_log($uploadLog, "[" . date('c') . "] move_uploaded_file failed");
    fail('Erreur interne : impossible de déplacer le fichier');
}
if (!rename($tmp, $destPath)) {
    @unlink($tmp);
    write_log($uploadLog, "[" . date('c') . "] rename to final failed: " . $destPath);
    fail('Erreur interne : stockage final impossible');
}

// set safe file permissions
@chmod($destPath, 0640);

write_log($uploadLog, "[" . date('c') . "] Fichier stocké: " . $destPath . " (size=" . filesize($destPath) . ")");

// -------------------------------
// DB upsert (SQLite)
// -------------------------------
$dbFile = $config['db_file'];

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    write_log($uploadLog, "[" . date('c') . "] DB connection failed: " . $e->getMessage());
    // rollback: remove stored file
    @unlink($destPath);
    fail('Erreur serveur (DB).');
}

$now = time();
$expiry = strtotime('+6 months', $now);
$token = bin2hex(random_bytes(18)); // secure token for download links

$db->beginTransaction();
try {
    // find existing by email (not deleted)
    $stmt = $db->prepare('SELECT * FROM attestations WHERE parent_email = ? AND deleted_at IS NULL');
    $stmt->execute([$parent_email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // delete old file if exists
        if (!empty($existing['filename'])) {
            $oldPath = $storageDir . '/' . $existing['filename'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
                write_log($uploadLog, "[" . date('c') . "] Ancien fichier supprimé: " . $oldPath);
            }
        }
        // update record
        $upd = $db->prepare('UPDATE attestations SET nom=?, prenom=?, filename=?, token=?, uploaded_at=?, expiry_at=?, reminder_sent=0 WHERE parent_email=?');
        $upd->execute([$nom, $prenom, basename($destPath), $token, $now, $expiry, $parent_email]);
        $recordId = $existing['id'];
        write_log($uploadLog, "[" . date('c') . "] DB update id={$recordId} email={$parent_email}");
    } else {
        // insert
        $ins = $db->prepare('INSERT INTO attestations (nom,prenom,parent_email,filename,token,uploaded_at,expiry_at) VALUES (?,?,?,?,?,?,?)');
        $ins->execute([$nom, $prenom, $parent_email, basename($destPath), $token, $now, $expiry]);
        $recordId = $db->lastInsertId();
        write_log($uploadLog, "[" . date('c') . "] DB insert id={$recordId} email={$parent_email}");
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    // cleanup file
    @unlink($destPath);
    write_log($uploadLog, "[" . date('c') . "] DB error: " . $e->getMessage());
    fail('Erreur serveur (DB insert/update).');
}

// -------------------------------
// CSV log for audit
// -------------------------------
try {
    $csvDir = dirname($config['csv_path']);
    @mkdir($csvDir, 0775, true);
    $fp = fopen($config['csv_path'], 'ab');
    if ($fp) {
        fputcsv($fp, [date('c'), $recordId, $nom, $prenom, $parent_email, basename($destPath), $now], ';');
        fclose($fp);
    } else {
        write_log($uploadLog, "[" . date('c') . "] Cannot open CSV log: " . $config['csv_path']);
    }
} catch (Throwable $e) {
    write_log($uploadLog, "[" . date('c') . "] CSV log failed: " . $e->getMessage());
}

// -------------------------------
// Final: show a friendly confirmation page (HTML)
// -------------------------------
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Attestation enregistrée</title>
<style>
  :root{--green:#16a34a;--gray:#374151;--bg:#f8fafc}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);margin:0;display:grid;place-content:center;min-height:100vh;color:var(--gray)}
  .card{background:#fff;padding:2rem;border-radius:12px;box-shadow:0 6px 18px rgba(15,23,42,0.06);max-width:720px;width:94%;border:1px solid #e6eef3;text-align:left}
  h1{color:var(--green);margin:0 0 .5rem;font-size:1.5rem}
  p{margin:.4rem 0}
  code{display:inline-block;background:#f1f5f9;padding:.25rem .5rem;border-radius:6px;font-family:monospace}
  .meta{margin-top:1rem;color:#6b7280;font-size:.95rem}
  .actions{margin-top:1.25rem}
  .btn{display:inline-block;background:var(--green);color:#fff;padding:.6rem 1rem;border-radius:8px;text-decoration:none;font-weight:600}
  .btn-secondary{background:#eef2ff;color:#1e293b;padding:.6rem 1rem;border-radius:8px;text-decoration:none;margin-left:.6rem}
  .muted{color:#6b7280;font-size:.95rem}
</style>
</head>
<body>
  <div class="card">
    <h1>✅ Merci — attestation enregistrée</h1>
    <p>Votre fichier a bien été reçu et stocké de manière sécurisée.</p>

    <p><strong>Référence :</strong><br>
      <code><?= htmlspecialchars(basename($destPath), ENT_QUOTES, 'UTF-8') ?></code>
    </p>

    <div class="meta">
      <p class="muted">Déposé le : <?= date('Y-m-d H:i:s', $now) ?> (heure serveur)</p>
      <p class="muted">Valide jusqu’au : <?= date('Y-m-d', $expiry) ?> (6 mois)</p>
    </div>

    <div class="actions">
      <a class="btn" href="index.php">Retour au formulaire</a>
      <a class="btn-secondary" href="javascript:window.print()">Imprimer / Enregistrer</a>
    </div>
  </div>
</body>
</html>