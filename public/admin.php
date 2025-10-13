<?php
// public/admin.php
session_start();
$config = require __DIR__ . '/../config.php';
$dbfile = $config['db_file'];
if (!file_exists($dbfile)) { http_response_code(500); echo "DB absente"; exit; }
$db = new PDO('sqlite:' . $dbfile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// logout
if (isset($_GET['logout'])){ session_destroy(); header('Location: '.$_SERVER['PHP_SELF']); exit; }

// auth
if (!isset($_SESSION['admin_ok']) || $_SESSION['admin_ok'] !== true) {
  $err = null;
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['u'] ?? '';
    $p = $_POST['p'] ?? '';
    if ($u === ($config['admin']['user'] ?? 'admin') && password_verify($p, $config['admin']['pass_hash'] ?? '')) {
      $_SESSION['admin_ok'] = true;
      header('Location: '.$_SERVER['PHP_SELF']); exit;
    }
    $err = "Identifiants invalides";
  }
  ?>
  <!doctype html>
  <html lang="fr"><head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin – Attestations</title>
    <style>body{font-family:system-ui;max-width:420px;margin:12vh auto;padding:0 16px} .card{border:1px solid #ddd;padding:16px;border-radius:8px} input,button{width:100%;padding:.6rem;border-radius:6px;border:1px solid #ccc} .err{background:#fff5f5;padding:8px;border-radius:6px;color:#7f1d1d;margin-bottom:12px}</style>
  </head><body>
    <h1>Admin – Attestations</h1>
    <div class="card">
      <?php if (!empty($err)) echo '<div class="err">'.htmlspecialchars($err).'</div>'; ?>
      <form method="post">
        <label>Utilisateur</label><input name="u" required>
        <label>Mot de passe</label><input name="p" type="password" required>
        <button type="submit">Se connecter</button>
      </form>
    </div>
  </body></html>
  <?php
  exit;
}

// helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dt($ts){ return $ts ? date('Y-m-d', (int)$ts) : ''; }
function etat($row, $now){
  if (!empty($row['deleted_at'])) return 'Supprimée';
  if ($row['expiry_at'] <= $now) return 'Expirée';
  return 'Active';
}

// filtres & pagination
$qs = $_GET;
$search = trim($qs['q'] ?? '');
$state = trim($qs['state'] ?? '');
$soonDays = (int)($qs['soon'] ?? 14);
$page = max(1, (int)($qs['page'] ?? 1));
$pageSize = (int)($config['admin']['page_size'] ?? 25);
$offset = ($page-1)*$pageSize;
$now = time();

// stats
$stats = [];
$stats['total']   = (int)$db->query("SELECT COUNT(*) FROM attestations")->fetchColumn();
$stats['actifs']  = (int)$db->query("SELECT COUNT(*) FROM attestations WHERE deleted_at IS NULL AND expiry_at > $now")->fetchColumn();
$stats['exp']     = (int)$db->query("SELECT COUNT(*) FROM attestations WHERE deleted_at IS NULL AND expiry_at <= $now")->fetchColumn();
$stats['del']     = (int)$db->query("SELECT COUNT(*) FROM attestations WHERE deleted_at IS NOT NULL")->fetchColumn();
$stats['d7']      = (int)$db->query("SELECT COUNT(*) FROM attestations WHERE uploaded_at >= ".strtotime('-7 days'))->fetchColumn();
$stats['d30']     = (int)$db->query("SELECT COUNT(*) FROM attestations WHERE uploaded_at >= ".strtotime('-30 days'))->fetchColumn();

// prochains à expirer
$soonList = $db->query("SELECT nom, prenom, parent_email, expiry_at FROM attestations
  WHERE deleted_at IS NULL AND expiry_at > $now AND expiry_at <= ".strtotime('+30 days')."
  ORDER BY expiry_at ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// requête liste
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
$countSql = "SELECT COUNT(*) FROM attestations WHERE $whereSql";
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalRows = (int)$stmt->fetchColumn();

$sql = "SELECT * FROM attestations WHERE $whereSql ORDER BY uploaded_at DESC LIMIT :lim OFFSET :off";
$stmt = $db->prepare($sql);
foreach($params as $k=>$v){ $stmt->bindValue($k, $v); }
$stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// render
?>
<!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – Attestations</title>
<style>
:root{--muted:#6b7280}
body{font-family:system-ui;max-width:1200px;margin:24px auto;padding:0 16px}
.kpi{display:inline-block;border:1px solid #e5e7eb;padding:10px;border-radius:8px;margin-right:8px}
table{width:100%;border-collapse:collapse;margin-top:12px}
th,td{border-bottom:1px solid #eee;padding:8px 6px;text-align:left}
th{background:#fafafa}
.pill{display:inline-block;padding:.2rem .45rem;border-radius:999px;border:1px solid #e5e7eb;font-size:.85rem}
.ok{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
.bad{background:#fff5f5;color:#7f1d1d;border-color:#fecaca}
.muted{color:var(--muted)}
</style>
</head><body>
  <h1>Tableau de bord – Attestations</h1>

  <div>
    <div class="kpi"><div class="muted">Total</div><strong><?=h($stats['total'])?></strong></div>
    <div class="kpi"><div class="muted">Actives</div><strong><?=h($stats['actifs'])?></strong></div>
    <div class="kpi"><div class="muted">Expirées</div><strong><?=h($stats['exp'])?></strong></div>
    <div class="kpi"><div class="muted">Supprimées</div><strong><?=h($stats['del'])?></strong></div>
    <div class="kpi"><div class="muted">+7 jours</div><strong><?=h($stats['d7'])?></strong></div>
    <div class="kpi"><div class="muted">+30 jours</div><strong><?=h($stats['d30'])?></strong></div>
  </div>

  <h3>À échéance sous 30 jours</h3>
  <div class="muted">
    <?php if (!$soonList) echo 'R.A.S.'; else foreach($soonList as $s) echo h($s['nom'].' '.$s['prenom']) . ' → ' . h(dt($s['expiry_at'])) . ' · '; ?>
  </div>

  <form method="get" style="margin-top:12px">
    <input name="q" placeholder="Nom, Prénom, Email" value="<?=h($search)?>">
    <select name="state">
      <option value="">Tous</option>
      <option value="active" <?= $state==='active'?'selected':'' ?>>Actives</option>
      <option value="expired" <?= $state==='expired'?'selected':'' ?>>Expirées</option>
      <option value="soon" <?= $state==='soon'?'selected':'' ?>>Expire bientôt</option>
      <option value="deleted" <?= $state==='deleted'?'selected':'' ?>>Supprimées</option>
    </select>
    <input type="number" name="soon" min="1" max="90" value="<?=h($soonDays)?>">
    <button type="submit">Filtrer</button>
    <a href="<?=h($_SERVER['PHP_SELF'])?>">Réinitialiser</a>
  </form>

  <table>
    <thead>
      <tr><th>Nom</th><th>Prénom</th><th>Email</th><th>Déposé le</th><th>Expire le</th><th>État</th><th>Relance</th></tr>
    </thead>
    <tbody>
      <?php if (!$rows) { ?>
        <tr><td colspan="7" class="muted">Aucun résultat</td></tr>
      <?php } else {
        foreach($rows as $r){
          $e = etat($r,$now);
          $badgeClass = $e==='Active' ? 'ok' : 'bad';
          $rel = $r['reminder_sent'] ? 'Envoyée' : (($r['expiry_at'] <= $now) ? 'À envoyer' : '—');
      ?>
        <tr>
          <td><?=h($r['nom'])?></td>
          <td><?=h($r['prenom'])?></td>
          <td><?=h($r['parent_email'])?></td>
          <td><?=h(dt($r['uploaded_at']))?></td>
          <td><?=h(dt($r['expiry_at']))?></td>
          <td><span class="pill <?=$badgeClass?>"><?=h($e)?></span></td>
          <td><?=h($rel)?></td>
        </tr>
      <?php } } ?>
    </tbody>
  </table>

  <?php
    $pages = max(1, (int)ceil($totalRows / $pageSize));
    echo '<div style="margin-top:10px;color:var(--muted)">';
    echo 'Page '.h($page).' / '.h($pages).' · Total '.h($totalRows);
    if ($pages>1) {
      if ($page>1) echo ' · <a href="?'.http_build_query(array_merge($qs,['page'=>$page-1])).'">← Préc.</a>';
      if ($page<$pages) echo ' · <a href="?'.http_build_query(array_merge($qs,['page'=>$page+1])).'">Suiv. →</a>';
    }
    echo '</div>';
  ?>

  <p class="muted">NB : Aucun lien de téléchargement n’est affiché ici.</p>
  <p><a href="?logout=1">Se déconnecter</a></p>
</body></html>