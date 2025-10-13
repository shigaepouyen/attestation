<?php
// public/create_db_web.php
// Création de la base SQLite depuis le navigateur.
// À exécuter UNE SEULE FOIS puis à supprimer immédiatement.

header('Content-Type: text/html; charset=utf-8');

$dbFile = realpath(__DIR__ . '/..') . '/db/attestations.sqlite';

try {
    if (!is_dir(dirname($dbFile))) {
        throw new RuntimeException('Dossier DB manquant : ' . dirname($dbFile));
    }

    if (file_exists($dbFile)) {
        $msg = "⚠️ La base existe déjà : <code>$dbFile</code>";
    } else {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Création de la table principale
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS attestations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nom TEXT NOT NULL,
                prenom TEXT NOT NULL,
                parent_email TEXT NOT NULL UNIQUE,
                filename TEXT NOT NULL,
                token TEXT NOT NULL UNIQUE,
                uploaded_at INTEGER NOT NULL,
                expiry_at INTEGER NOT NULL,
                reminder_sent INTEGER DEFAULT 0,
                deleted_at INTEGER DEFAULT NULL
            );
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expiry ON attestations(expiry_at);");

        $msg = "✅ Base créée avec succès : <code>$dbFile</code>";
    }

    // Auto-suppression après exécution
    $self = __FILE__;
    $deleted = @unlink($self);

} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>Erreur</h1><pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation de la base – Attestations</title>
<style>
  body { font-family: system-ui, sans-serif; display: grid; place-content: center; min-height: 100vh; background: #f9fafb; margin: 0; }
  .card { background: white; padding: 24px; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 2px 6px rgba(0,0,0,.05); max-width: 640px; line-height: 1.5; }
  code { background: #f3f4f6; padding: 0.1rem 0.4rem; border-radius: 4px; }
  .ok { color: #065f46; }
  .warn { color: #92400e; }
</style>
</head>
<body>
  <div class="card">
    <h1>Création de la base</h1>
    <p class="ok"><?= $msg ?></p>
    <?php if ($deleted): ?>
      <p class="ok">✅ Ce script s'est auto-supprimé après exécution.</p>
    <?php else: ?>
      <p class="warn">⚠️ Supprime manuellement ce fichier <code><?= basename($self) ?></code> du dossier <code>public/</code>.</p>
    <?php endif; ?>
    <p style="margin-top:1rem;color:#555">
      Le dossier <code>/db</code> doit être protégé via un fichier <code>.htaccess</code> pour empêcher tout accès HTTP.
    </p>
  </div>
</body>
</html>