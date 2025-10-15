<?php
// public/rapport.php
// Affiche la liste sécurisée de toutes les attestations valides.
// L'accès est protégé par un "master token" généré par le cron.

$config = require __DIR__ . '/../config.php';

// --- Validation du Master Token ---
$tokenFile = dirname($config['storage_dir']) . '/master_token.txt';
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

// --- Récupération des données ---
$db = new PDO('sqlite:' . $config['db_file']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$now = time();
$stmt = $db->prepare(
    'SELECT * FROM attestations 
     WHERE deleted_at IS NULL AND expiry_at > ? 
     ORDER BY nom ASC, prenom ASC'
);
$stmt->execute([$now]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapport des Attestations d'Honorabilité</title>
    <style>
        :root {
            --primary: #007bff; --light: #f8f9fa; --dark: #343a40;
            --muted: #6c757d; --border-color: #dee2e6; --success: #28a745;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background-color: var(--light); color: var(--dark); }
        .container { max-width: 1200px; margin: 2rem auto; padding: 2rem; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h1 { color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 0.5rem; margin-bottom: 1rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .header p { margin: 0; font-size: 1.1rem; }
        .btn-archive {
            display: inline-block; padding: 0.75rem 1.5rem; background-color: var(--success); color: #fff;
            text-decoration: none; border-radius: 6px; font-weight: 600; transition: background-color 0.2s, opacity 0.2s;
            cursor: pointer;
        }
        .btn-archive:hover { background-color: #218838; }
        .btn-archive.is-loading {
            background-color: #6c757d;
            opacity: 0.65;
            cursor: not-allowed;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); }
        thead th { background-color: var(--light); }
        tbody tr:nth-of-type(even) { background-color: var(--light); }
        tbody tr:hover { background-color: #e9ecef; }
        .action-link { color: var(--primary); text-decoration: none; font-weight: 500; }
        .action-link:hover { text-decoration: underline; }
        @media (max-width: 768px) {
            th, td { padding: 8px; }
            .header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Rapport des Attestations</h1>
                <p>Liste de toutes les attestations valides au <?= h(date('d/m/Y')) ?>.</p>
            </div>
            <?php if (!empty($rows)): ?>
                <a href="archive.php?token=<?= h($requestToken) ?>" class="btn-archive" id="download-zip-btn">Télécharger tout (ZIP)</a>
            <?php endif; ?>
        </div>

        <?php if (empty($rows)): ?>
            <p>Aucune attestation active pour le moment.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Expire le</th>
                        <th>Télécharger</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= h($r['nom']) ?></td>
                            <td><?= h($r['prenom']) ?></td>
                            <td><?= h(date('d/m/Y', (int)$r['expiry_at'])) ?></td>
                            <td>
                                <a href="download.php?token=<?= h($r['token']) ?>" class="action-link" target="_blank">Ouvrir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script>
    const downloadBtn = document.getElementById('download-zip-btn');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function(e) {
            if (this.classList.contains('is-loading')) {
                e.preventDefault();
                return;
            }
            this.classList.add('is-loading');
            this.textContent = 'Préparation du ZIP...';

            // Réactive le bouton après 10 secondes pour éviter qu'il ne reste bloqué
            // si l'utilisateur annule le téléchargement ou si le serveur répond rapidement.
            setTimeout(() => {
                this.classList.remove('is-loading');
                this.textContent = 'Télécharger tout (ZIP)';
            }, 10000);
        });
    }
    </script>
</body>
</html>
