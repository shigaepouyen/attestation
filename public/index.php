<?php
// public/index.php
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Déposer votre attestation</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:720px;margin:24px auto;padding:0 16px;line-height:1.45}
  .card{border:1px solid #ddd;border-radius:10px;padding:18px}
  label{display:block;margin:.7rem 0 .25rem}
  input,button{width:100%;padding:.7rem;border:1px solid #bbb;border-radius:8px}
  .row{display:flex;gap:12px}.row>div{flex:1}
  .hint{color:#555;font-size:.95rem}
  .hp{position:absolute;left:-5000px}
</style>
</head>
<body>
  <h1>Déposer votre attestation d’honorabilité</h1>

  <p style="color:#374151;line-height:1.5;margin-bottom:1rem">
    Dans le cadre de la réglementation en vigueur, toute personne intervenant auprès des élèves
    (par exemple dans le cadre du BDI, d’une sortie scolaire ou d’une animation) doit fournir une
    <strong>attestation d’honorabilité</strong>.  
  </p>

  <p style="color:#374151;line-height:1.5;margin-bottom:1rem">
    Cette attestation est délivrée par le <strong>Ministère de l’Éducation nationale</strong> à l’issue
    d’une simple démarche en ligne :  
  </p>

  <ol style="color:#374151;line-height:1.6;margin-bottom:1rem;padding-left:1.4rem">
    <li>Rendez-vous sur le site officiel :
        <a href="https://honorabilite.social.gouv.fr/jai-besoin-dune-attestation-dhonorabilite"
           target="_blank" rel="noopener noreferrer">honorabilite.social.gouv.fr</a></li>
    <li>Saisissez vos informations d’identité et validez la demande.</li>
    <li>Quelques jours plus tard, vous recevrez un e-mail du ministère avec un lien
        pour télécharger votre attestation (format PDF).</li>
    <li>Une fois le PDF téléchargé, revenez sur cette page pour le déposer ci-dessous.</li>
  </ol>

  <p style="color:#374151;line-height:1.5;margin-bottom:1.5rem">
    L’attestation est valable <strong>6 mois</strong>.  
    Vous serez automatiquement invité à la renouveler une seule fois lorsque sa validité arrivera à échéance.
  </p>

  <p style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;border-radius:8px;padding:0.75rem 1rem;margin-bottom:1.5rem">
    💡 <strong>Important :</strong> votre adresse e-mail est obligatoire.  
    Elle nous permet d’associer le document à votre dossier et de vous prévenir lorsque
    l’attestation devra être renouvelée.
  </p>

  <div class="card">
    <form action="upload.php" method="post" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf" value="<?=htmlspecialchars($csrf)?>">
      <div class="hp"><label>Ne pas remplir</label><input name="website" autocomplete="off"></div>

      <div class="row">
        <div>
          <label for="nom">Nom</label>
          <input id="nom" name="nom" required autocomplete="family-name" placeholder="DURAND">
        </div>
        <div>
          <label for="prenom">Prénom</label>
          <input id="prenom" name="prenom" required autocomplete="given-name" placeholder="Sophie">
        </div>
      </div>

      <label for="email">E-mail (obligatoire)</label>
      <input id="email" name="email" type="email" required placeholder="prenom.nom@email.fr">

      <label for="pdf">Attestation (PDF uniquement)</label>
      <input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" required>

      <p class="hint">Nous supprimerons automatiquement l’attestation à l’expiration (6 mois) et enverrons un rappel unique.</p>
      <button type="submit">Envoyer</button>
    </form>
  </div>
</body>
</html>