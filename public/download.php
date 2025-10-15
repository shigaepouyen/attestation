<?php
// public/download.php
// Téléchargement sécurisé d'une attestation via un token unique.

session_start();
header('Content-Type: text/html; charset=utf-8');

// Fonction d'erreur générique
function fail_download($message, $code = 404) {
    http_response_code($code);
    // On pourrait afficher une page HTML plus propre ici
    die(htmlspecialchars($message));
}

// Chargement de la configuration
$config = require __DIR__ . '/../config.php';
if (empty($config['db_file']) || empty($config['storage_dir'])) {
    fail_download("Configuration du serveur incomplète.", 500);
}

// Connexion à la base de données
try {
    $db = new PDO('sqlite:' . $config['db_file']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Erreur de connexion DB [download.php]: " . $e->getMessage());
    fail_download("Erreur de service.", 500);
}

// Validation du token
$token = $_GET['token'] ?? '';
if (empty($token) || !ctype_alnum($token)) {
    fail_download("Le lien de téléchargement est invalide ou a expiré.", 400);
}

// Récupération de l'enregistrement
$stmt = $db->prepare('SELECT nom, prenom, filename FROM attestations WHERE token = ? AND deleted_at IS NULL');
$stmt->execute([$token]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    fail_download("Le fichier demandé n'existe pas ou n'est plus accessible.", 404);
}

// Vérification du fichier physique
$filePath = rtrim($config['storage_dir'], '/') . '/' . $record['filename'];
if (!file_exists($filePath) || !is_readable($filePath)) {
    error_log("Fichier non trouvé sur le disque: {$filePath}");
    fail_download("Le fichier est introuvable sur le serveur.", 404);
}

// Construction d'un nom de fichier lisible pour l'utilisateur
$friendly_filename = sprintf(
    'Attestation-Honorabilite-%s-%s.pdf',
    preg_replace('/[^a-zA-Z0-9-]/', '', $record['prenom']),
    preg_replace('/[^a-zA-Z0-9-]/', '', $record['nom'])
);

// Envoi du fichier avec des en-têtes de sécurité
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $friendly_filename . '"');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff'); // Empêche le "sniffing" de type MIME
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Nettoyer le buffer de sortie avant de lire le fichier
if (ob_get_level()) {
    ob_end_clean();
}

readfile($filePath);
exit;
