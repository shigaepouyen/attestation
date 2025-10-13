<?php
// public/download.php
require __DIR__ . '/../config.php';
$config = require __DIR__ . '/../config.php';
$db = new PDO('sqlite:' . $config['db_file']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$token = $_GET['token'] ?? '';
if (!$token) { http_response_code(400); echo "Bad token\n"; exit; }

$stmt = $db->prepare('SELECT * FROM attestations WHERE token = ? AND deleted_at IS NULL');
$stmt->execute([$token]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) { http_response_code(404); echo "Fichier introuvable\n"; exit; }

$path = __DIR__ . '/../' . trim($config['storage_dir'], '/'). '/' . $r['filename'];
if (!file_exists($path)) { http_response_code(404); echo "Fichier supprimé\n"; exit; }

// Forcer le téléchargement
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.basename($r['filename']).'"');
header('Content-Length: '.filesize($path));
readfile($path);
exit;