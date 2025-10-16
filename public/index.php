<?php
// public/index.php
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf--8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Déposer votre attestation d’honorabilité</title>
<style>
  :root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #198754;
    --background-color: #f8f9fa;
    --text-color: #212529;
    --heading-color: #343a40;
    --border-color: #dee2e6;
    --card-background: #ffffff;
    --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --border-radius: 0.5rem;
    --box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  }

  body {
    font-family: var(--font-sans);
    background-color: var(--background-color);
    color: var(--text-color);
    margin: 0;
    padding: 2rem 1rem;
    line-height: 1.6;
  }

  .container {
    max-width: 800px;
    margin: 0 auto;
  }

  header {
    text-align: center;
    margin-bottom: 2.5rem;
  }

  header h1 {
    color: var(--heading-color);
    font-size: 2.25rem;
    margin-bottom: 0.5rem;
  }

  header p {
    font-size: 1.1rem;
    color: var(--secondary-color);
  }

  .card {
    background-color: var(--card-background);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 2.5rem;
    box-shadow: var(--box-shadow);
    margin-bottom: 2rem;
  }

  .info-box {
    background-color: #e9f7ff;
    color: #0056b3;
    border: 1px solid #b8daff;
    border-left: 5px solid var(--primary-color);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-radius: var(--border-radius);
  }

  .info-box strong {
    color: var(--primary-color);
  }

  ol {
    padding-left: 1.5rem;
    margin-bottom: 1.5rem;
  }

  li {
    margin-bottom: 0.75rem;
  }

  a {
    color: var(--primary-color);
    text-decoration: none;
    font-weight: 500;
  }

  a:hover {
    text-decoration: underline;
  }

  form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
  }

  form .row {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
  }

  form .row > div {
    flex: 1;
  }

  form input {
    width: 100%;
    padding: 0.8rem;
    border: 1px solid #ced4da;
    border-radius: var(--border-radius);
    box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  form input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
    outline: none;
  }

  form input[type="file"] {
    padding: 0.5rem;
  }

  form button {
    width: 100%;
    padding: 1rem;
    border: none;
    border-radius: var(--border-radius);
    background-color: var(--primary-color);
    color: white;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    margin-top: 1.5rem;
    transition: background-color 0.2s;
  }

  form button:hover {
    background-color: #0056b3;
  }

  .hint {
    color: var(--secondary-color);
    font-size: 0.9rem;
    margin-top: 1rem;
    text-align: center;
  }

  /* Champ honeypot pour les bots */
  .hp {
    position: absolute;
    left: -5000px;
  }
  
  /* Responsive */
  @media (max-width: 600px) {
    form .row {
      flex-direction: column;
      gap: 1rem;
    }
  }

</style>
</head>
<body>
  <div class="container">
    <header>
      <h1>Déposer votre attestation d’honorabilité</h1>
      <p>Un processus simple et sécurisé pour les intervenants.</p>
    </header>

    <div class="card">
      <h2>Instructions</h2>
      <p>
        Dans le cadre de la réglementation, toute personne intervenant auprès des élèves doit fournir une <strong>attestation d’honorabilité</strong>.
      </p>

      <ol>
        <li>
          Rendez-vous sur le site officiel :
          <a href="https://honorabilite.social.gouv.fr/jai-besoin-dune-attestation-dhonorabilite" target="_blank" rel="noopener noreferrer">honorabilite.social.gouv.fr</a>
        </li>
        <li>Saisissez vos informations et validez la demande.</li>
        <li>Vous recevrez un e-mail du ministère avec un lien pour télécharger votre attestation au format PDF.</li>
        <li>Une fois le PDF obtenu, revenez sur cette page pour le déposer via le formulaire ci-dessous.</li>
      </ol>
      <p>
        L’attestation est valable <strong>6 mois</strong>. Le système vous enverra un rappel unique par e-mail avant son expiration.
      </p>
    </div>

    <div class="info-box">
      <strong>Important :</strong> Votre adresse e-mail est essentielle. Elle nous permet d'associer le document à votre dossier et de vous notifier lorsque le renouvellement est nécessaire.
    </div>

    <div class="card">
      <h2>Formulaire de dépôt</h2>
      <form id="upload-form" action="upload.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="hp"><label>Ne pas remplir</label><input name="website" autocomplete="off"></div>

        <div class="row">
          <div>
            <label for="nom">Nom de l'intervenant</label>
            <input id="nom" name="nom" required autocomplete="family-name" placeholder="Par exemple : DURAND">
          </div>
          <div>
            <label for="prenom">Prénom de l'intervenant</label>
            <input id="prenom" name="prenom" required autocomplete="given-name" placeholder="Par exemple : Sophie">
          </div>
        </div>

        <div>
            <label for="email">Votre e-mail (obligatoire)</label>
            <input id="email" name="email" type="email" required placeholder="sophie.durand@email.fr" autocomplete="email">
        </div>
        
        <div style="margin-top: 1.5rem;">
            <label for="pdf">Votre attestation (Fichier PDF uniquement)</label>
            <input id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" required>
        </div>
        
        <p class="hint">Le fichier sera stocké de manière sécurisée et supprimé automatiquement après 6 mois.</p>
        <button id="submit-btn" type="submit">Envoyer mon attestation</button>
      </form>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('upload-form');
  const submitBtn = document.getElementById('submit-btn');
  const fileInput = document.getElementById('pdf');

  form.addEventListener('submit', function (e) {
    // 1. Validation du fichier
    const file = fileInput.files[0];
    if (!file) {
      alert('Veuillez sélectionner un fichier PDF.');
      e.preventDefault(); // Empêche l'envoi
      return;
    }
    if (file.type !== 'application/pdf') {
      alert('Le fichier sélectionné n\'est pas un PDF valide.');
      e.preventDefault(); // Empêche l'envoi
      return;
    }

    // 2. Empêcher les soumissions multiples
    if (submitBtn.disabled) {
        e.preventDefault();
        return;
    }

    // Désactiver le bouton et afficher un message de chargement
    submitBtn.disabled = true;
    submitBtn.textContent = 'Envoi en cours...';

    // Pour la robustesse, réactiver le bouton après un court délai
    // au cas où la soumission serait annulée par le navigateur (ex: navigation arrière)
    setTimeout(() => {
        if (submitBtn.disabled) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Envoyer mon attestation';
        }
    }, 5000); // 5 secondes
  });
});
</script>
</body>
</html>
