<?php
// public/admin.php
session_start();
$config = require __DIR__ . '/../config.php';
$dbfile = $config['db_file'];
if (!file_exists($dbfile)) { http_response_code(500); echo "Erreur: Fichier de base de données introuvable."; exit; }
$db = new PDO('sqlite:' . $dbfile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Déconnexion
if (isset($_GET['logout'])){ 
    session_destroy(); 
    header('Location: '.$_SERVER['PHP_SELF']); 
    exit; 
}

// Authentification
if (!isset($_SESSION['admin_ok']) || $_SESSION['admin_ok'] !== true) {
  $err = null;
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['u'] ?? '';
    $p = $_POST['p'] ?? '';
    if ($u === ($config['admin']['user'] ?? 'admin') && password_verify($p, $config['admin']['pass_hash'] ?? '')) {
      $_SESSION['admin_ok'] = true;
      header('Location: '.$_SERVER['PHP_SELF']); 
      exit;
    }
    $err = "Identifiants invalides";
  }
  ?>
  <!doctype html>
  <html lang="fr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Administration – Attestations</title>
    <style>
      :root {
        --primary-color: #0d6efd;
        --background-color: #f8f9fa;
        --text-color: #212529;
        --card-shadow: 0 4px 6px rgba(0,0,0,0.1);
        --border-color: #dee2e6;
      }
      body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background-color: var(--background-color);
        margin: 0;
        color: var(--text-color);
      }
      .login-card {
        width: 100%;
        max-width: 400px;
        padding: 2.5rem;
        box-shadow: var(--card-shadow);
        border-radius: 12px;
        background: white;
        border: 1px solid var(--border-color);
      }
      h1 { text-align: center; margin-bottom: 2rem; }
      label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
      input { width: 100%; padding: .75rem; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; margin-bottom: 1rem; }
      button { width: 100%; padding: .75rem; border-radius: 6px; border: none; background: var(--primary-color); color: white; font-weight: bold; cursor: pointer; }
      .err { background: #f8d7da; padding: 1rem; border-radius: 6px; color: #721c24; margin-bottom: 1rem; border: 1px solid #f5c6cb; }
    </style>
  </head>
  <body>
    <div class="login-card">
      <h1>Administration</h1>
      <?php if (!empty($err)) echo '<div class="err">'.htmlspecialchars($err).'</div>'; ?>
      <form method="post">
        <label for="u">Utilisateur</label>
        <input id="u" name="u" required>
        <label for="p">Mot de passe</label>
        <input id="p" name="p" type="password" required>
        <button type="submit">Se connecter</button>
      </form>
    </div>
  </body>
  </html>
  <?php
  exit;
}

// Fonctions utilitaires
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dt($ts){ return $ts ? date('d/m/Y', (int)$ts) : '—'; }
function etat($row, $now){
  if (!empty($row['deleted_at'])) return ['text' => 'Supprimée', 'class' => 'deleted'];
  if ($row['expiry_at'] <= $now) return ['text' => 'Expirée', 'class' => 'expired'];
  if (($row['expiry_at'] - $now) < (30 * 86400)) return ['text' => 'Expire bientôt', 'class' => 'soon'];
  return ['text' => 'Active', 'class' => 'active'];
}

// Filtres & pagination
$qs = $_GET;
$search = trim($qs['q'] ?? '');
$state = trim($qs['state'] ?? 'active');
$soonDays = (int)($qs['soon'] ?? 30);
$page = max(1, (int)($qs['page'] ?? 1));
$pageSize = (int)($config['admin']['page_size'] ?? 25);
$offset = ($page - 1) * $pageSize;
$now = time();

// Statistiques
$stats = [
    'total'   => (int)$db->query("SELECT COUNT(*) FROM attestations")->fetchColumn(),
    'actifs'  => (int)$db->query("SELECT COUNT(*) FROM attestations WHERE deleted_at IS NULL AND expiry_at > $now")->fetchColumn(),
    'exp'     => (int)$db->query("SELECT COUNT(*) FROM attestations WHERE deleted_at IS NULL AND expiry_at <= $now")->fetchColumn(),
    'del'     => (int)$db->query("SELECT COUNT(*) FROM attestations WHERE deleted_at IS NOT NULL")->fetchColumn(),
    'd7'      => (int)$db->query("SELECT COUNT(*) FROM attestations WHERE uploaded_at >= ".strtotime('-7 days'))->fetchColumn(),
    'd30'     => (int)$db->query("SELECT COUNT(*) FROM attestations WHERE uploaded_at >= ".strtotime('-30 days'))->fetchColumn()
];

// Requête de la liste
$where = ["1=1"];
$params = [];
if ($search !== '') {
  $where[] = "(nom LIKE :q OR prenom LIKE :q OR parent_email LIKE :q)";
  $params[':q'] = '%'.$search.'%';
}
if ($state === 'active')     $where[] = "deleted_at IS NULL AND expiry_at > $now";
if ($state === 'expired')    $where[] = "deleted_at IS NULL AND expiry_at <= $now";
if ($state === 'deleted')    $where[] = "deleted_at IS NOT NULL";
if ($state === 'soon')       $where[] = "deleted_at IS NULL AND expiry_at > $now AND expiry_at <= ".strtotime("+$soonDays days");

$whereSql = implode(' AND ', $where);
$countStmt = $db->prepare("SELECT COUNT(*) FROM attestations WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

$sql = "SELECT * FROM attestations WHERE $whereSql ORDER BY uploaded_at DESC LIMIT :lim OFFSET :off";
$stmt = $db->prepare($sql);
foreach($params as $k => $v){ $stmt->bindValue($k, $v); }
$stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tableau de bord – Attestations</title>
<style>
  :root {
      --primary: #007bff; --secondary: #6c757d; --success: #198754;
      --warning: #ffc107; --danger: #dc3545; --light: #f8f9fa; --dark: #212529;
      --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      --border-color: #dee2e6; --border-radius: 0.375rem;
  }
  body { font-family: var(--font-sans); background-color: #f4f5f7; color: var(--dark); margin: 0; }
  .container { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }
  .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
  .header h1 { margin: 0; }
  .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: var(--border-radius); text-decoration: none; border: 1px solid transparent; }
  .btn-logout { background-color: var(--secondary); color: white; }
  .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
  .kpi { background: white; border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--border-radius); text-align: center; }
  .kpi-label { color: var(--secondary); margin-bottom: 0.5rem; }
  .kpi-value { font-size: 2.25rem; font-weight: 700; color: var(--primary); }
  .filters { background: white; border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--border-radius); margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
  .filters input, .filters select { padding: 0.5rem; border: 1px solid #ccc; border-radius: var(--border-radius); }
  .filters button { background-color: var(--primary); color: white; border: none; cursor: pointer; padding: 0.5rem 1rem; border-radius: var(--border-radius); }
  .table-container { background: white; border: 1px solid var(--border-color); border-radius: var(--border-radius); overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
  th { background-color: var(--light); }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:nth-of-type(even) { background-color: var(--light); }
  .pill { display: inline-block; padding: .25rem .6rem; border-radius: 999px; font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
  .pill.active { background-color: #d1e7dd; color: #0f5132; }
  .pill.soon { background-color: #fff3cd; color: #664d03; }
  .pill.expired { background-color: #f8d7da; color: #842029; }
  .pill.deleted { background-color: #e2e3e5; color: #41464b; }
  .pagination { margin-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; color: var(--secondary); }
  .pagination a { color: var(--primary); text-decoration: none; font-weight: 600; }
  .actions-cell a { color: var(--primary); text-decoration:none; margin-right:10px; }
  .actions-cell a.danger { color: var(--danger); }
  .flash-msg { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; border: 1px solid; }
  .flash-success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
  .flash-error { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
  .global-actions { background: white; border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--border-radius); margin-top: 2rem; border-left: 5px solid var(--danger); }
  .global-actions h3 { margin-top: 0; }
  .btn-danger { background-color: var(--danger); color: white; border: none; cursor: pointer; padding: 0.5rem 1rem; border-radius: var(--border-radius); }
</style>
</head>
<body>
  <div class="container">
    <header class="header">
      <h1>Tableau de bord des attestations</h1>
      <a href="?logout=1" class="btn btn-logout">Se déconnecter</a>
    </header>

    <?php if (isset($_GET['action_success'])): ?>
      <div class="flash-msg flash-success">L'action a été effectuée avec succès.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="flash-msg flash-error">Une erreur est survenue : <?= h($_GET['error']) ?></div>
    <?php endif; ?>

    <div class="kpi-grid">
      <div class="kpi"><div class="kpi-label">Total</div><strong class="kpi-value"><?=h($stats['total'])?></strong></div>
      <div class="kpi"><div class="kpi-label">Actives</div><strong class="kpi-value" style="color:var(--success)"><?=h($stats['actifs'])?></strong></div>
      <div class="kpi"><div class="kpi-label">Expirées</div><strong class="kpi-value" style="color:var(--danger)"><?=h($stats['exp'])?></strong></div>
      <div class="kpi"><div class="kpi-label">Supprimées</div><strong class="kpi-value" style="color:var(--secondary)"><?=h($stats['del'])?></strong></div>
      <div class="kpi"><div class="kpi-label">+7 jours</div><strong class="kpi-value"><?=h($stats['d7'])?></strong></div>
      <div class="kpi"><div class="kpi-label">+30 jours</div><strong class="kpi-value"><?=h($stats['d30'])?></strong></div>
    </div>

    <form method="get" class="filters">
      <input name="q" placeholder="Rechercher Nom, Prénom, Email..." value="<?=h($search)?>" style="flex-grow:1;">
      <select name="state">
        <option value="">Tous les états</option>
        <option value="active" <?= $state==='active'?'selected':'' ?>>Actives</option>
        <option value="expired" <?= $state==='expired'?'selected':'' ?>>Expirées</option>
        <option value="soon" <?= $state==='soon'?'selected':'' ?>>Expire bientôt (<?=h($soonDays)?>j)</option>
        <option value="deleted" <?= $state==='deleted'?'selected':'' ?>>Supprimées</option>
      </select>
      <button type="submit">Filtrer</button>
      <a href="<?=h($_SERVER['PHP_SELF'])?>">Réinitialiser</a>
    </form>

    <div class="table-container">
      <table>
        <thead>
          <tr><th>Nom</th><th>Prénom</th><th>Email</th><th>Déposé le</th><th>Expire le</th><th>État</th><th>Relance</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" style="text-align:center; padding: 2rem; color: var(--secondary);">Aucun résultat pour cette recherche.</td></tr>
          <?php else:
            foreach($rows as $r):
              $e = etat($r, $now);
              $relance_status = $r['reminder_sent'] ? 'Envoyée' : (($r['expiry_at'] <= $now && empty($r['deleted_at'])) ? 'À envoyer' : '—');
          ?>
            <tr>
              <td><?=h($r['nom'])?></td>
              <td><?=h($r['prenom'])?></td>
              <td><?=h($r['parent_email'])?></td>
              <td><?=h(dt($r['uploaded_at']))?></td>
              <td><?=h(dt($r['expiry_at']))?></td>
              <td><span class="pill <?=h($e['class'])?>"><?=h($e['text'])?></span></td>
              <td><?=h($relance_status)?></td>
              <td class="actions-cell">
                <?php if (empty($r['deleted_at'])): ?>
                  <a href="admin_actions.php?action=remind&id=<?=h($r['id'])?>" title="Envoyer un rappel maintenant">Rappel</a>
                  <a href="admin_actions.php?action=delete&id=<?=h($r['id'])?>" class="danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette attestation ? Cette action est irréversible.')" title="Supprimer manuellement">Supprimer</a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    
    <?php
      $totalPages = max(1, (int)ceil($totalRows / $pageSize));
      if ($totalRows > 0):
    ?>
    <div class="pagination">
        <span>Page <?=h($page)?> sur <?=h($totalPages)?> (Total : <?=h($totalRows)?>)</span>
        <div>
            <?php if ($page > 1): ?>
                <a href="?<?=http_build_query(array_merge($qs,['page' => $page - 1]))?>">← Précédent</a>
            <?php endif; ?>
            <?php if ($page > 1 && $page < $totalPages): ?>
                &nbsp;|&nbsp;
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?=http_build_query(array_merge($qs,['page' => $page + 1]))?>">Suivant →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <p style="text-align:center; color: var(--secondary); margin-top: 2rem;">
        Note : Aucun lien de téléchargement direct n'est affiché sur ce tableau de bord pour des raisons de sécurité.
    </p>

    <div class="global-actions">
      <h3>Actions Globales (irréversibles)</h3>
      <p>Attention : L'action suivante supprimera <strong>toutes</strong> les attestations de la base de données et <strong>tous</strong> les fichiers téléversés dans le stockage. Cette opération est définitive.</p>
      <a href="admin_actions.php?action=global_reset" 
         class="btn btn-danger" 
         onclick="return confirm('Êtes-vous absolument sûr de vouloir tout effacer ?\nCette action est IRREVERSIBLE et supprimera toutes les données et tous les fichiers.')">
         Réinitialisation Globale
      </a>
    </div>

  </div>
</body>
</html>
