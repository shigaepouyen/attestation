<?php
// public/archive.php
// Génère une archive ZIP contenant toutes les attestations valides et la propose au téléchargement.
// L'accès est protégé par le même "master token" que la page de rapport.

$config = require __DIR__ . '/../config.php';

// --- Sécurité : Validation du Master Token ---
$tokenFile = rtrim($config['storage_dir'], '/') . '/../storage/master_token.txt';
if (!file_exists($tokenFile)) {
    http_response_code(403);
    die('Accès interdit (token invalide).');
}

$tokenData = json_decode(file_get_contents($tokenFile), true);
$requestToken = $_GET['token'] ?? '';

if (
    !isset($tokenData['token']) ||
    !hash_equals($tokenData['token'], $requestToken) ||
    time() > $tokenData['expiry']
) {
    http_response_code(403);
    die('Accès interdit ou lien expiré.');
}

// Vérification que l'extension ZipArchive est disponible
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die("Erreur serveur : L'extension PHP ZipArchive est requise mais n'est pas activée.");
}

// --- Récupération de la liste des fichiers ---
try {
    $db = new PDO('sqlite:' . $config['db_file']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $now = time();
    $stmt = $db->prepare(
        'SELECT nom, prenom, filename FROM attestations
         WHERE deleted_at IS NULL AND expiry_at > ?'
    );
    $stmt->execute([$now]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    http_response_code(500);
    die("Erreur lors de l'accès à la base de données.");
}

if (empty($records)) {
    die("Aucune attestation valide à archiver.");
}

// --- Création de l'archive ZIP ---
$zip = new ZipArchive();
$zipFileName = 'attestations_honorabilite_' . date('Y-m-d') . '.zip';
$tempZipPath = sys_get_temp_dir() . '/' . $zipFileName;

if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    http_response_code(500);
    die("Impossible de créer l'archive ZIP.");
}

$storageDir = $config['storage_dir'];
$filesAdded = 0;

foreach ($records as $record) {
    $filePath = $storageDir . '/' . $record['filename'];
    if (file_exists($filePath)) {
        // Construction d'un nom de fichier lisible
        $friendly_filename = sprintf(
            'Attestation-%s-%s.pdf',
            preg_replace('/[^a-zA-Z0-9-]/', '', $record['prenom']),
            preg_replace('/[^a-zA-Z0-9-]/', '', $record['nom'])
        );
        $zip->addFile($filePath, $friendly_filename);
        $filesAdded++;
    }
}

$zip->close();

if ($filesAdded === 0) {
    @unlink($tempZipPath);
    die("Les fichiers des attestations sont introuvables sur le serveur.");
}

// --- Envoi de l'archive au navigateur ---
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
header('Content-Length: ' . filesize($tempZipPath));
header('Pragma: no-cache');
header('Expires: 0');

// Vider les tampons de sortie pour éviter la corruption du fichier
ob_clean();
flush();

readfile($tempZipPath);

// --- Nettoyage ---
unlink($tempZipPath);
exit;
