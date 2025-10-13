# 🧾 Système de gestion des attestations

## 🎯 Objectif

Collecter, suivre et gérer automatiquement les **attestations d'honorabilité** des parents intervenants.  
Le système assure :

- Dépôt sécurisé via formulaire  
- Suppression automatique après **6 mois**  
- Relance automatique par e-mail  
- Envoi hebdomadaire à la direction  
- Tableau de bord administratif **sans lien de téléchargement**

---

## 📁 Structure du projet

```
attestation/
│
├── config.php                  → configuration principale
│
├── db/
│   ├── create_db.php           → création initiale de la base SQLite
│   └── attestations.sqlite     → base de données (créée automatiquement)
│
├── storage/
│   ├── uploads/                → stockage des PDF (hors web)
│   └── logs/                   → CSV et logs
│
├── cron/
│   ├── reminders.php           → relances + suppression (cron quotidien)
│   └── weekly_digest.php       → envoi hebdomadaire à la direction
│
├── lib/
│   ├── PHPMailer/              → librairie PHPMailer
│   └── sendmail.php            → wrapper SMTP Gmail
│
├── public/
│   ├── index.php               → formulaire de dépôt
│   ├── upload.php              → traitement des fichiers
│   ├── download.php            → lien sécurisé (direction)
│   └── admin.php               → tableau de bord
```

---

## ⚙️ Prérequis

- PHP 8.1 ou supérieur (mutualisé OVH compatible)  
- Accès SSH ou FTP  
- Compte **Google Workspace** configuré avec :
  - une adresse expéditrice, ex. `attestations@domaine.fr`
  - un **mot de passe d’application Gmail**
- Dossiers inscriptibles :  
  `db/`, `storage/`, `storage/uploads/`, `storage/logs/`

---

## 🚀 Installation

### 1. Créer la base SQLite

```bash
cd db
/usr/local/php8.2/bin/php create_db.php
```

→ crée le fichier `attestations.sqlite`.

---

### 2. Générer le mot de passe admin

```bash
cd tools
/usr/local/php8.2/bin/php make_admin_pass.php
```

Copie le hash affiché et colle-le dans `config.php`, section :

```php
'admin' => [
  'user' => 'admin',
  'pass_hash' => 'TON_HASH_ICI'
]
```

> Supprime `make_admin_pass.php` après utilisation.

---

### 3. Configurer `config.php`

```php
'site_base_url'  => 'https://TON-DOMAINE.FR/attestations',
'director_email' => 'directrice@college.fr',

'smtp' => [
  'user' => 'attestations@domaine.fr',
  'pass' => 'MOT_DE_PASSE_APPLICATION'
]
```

---

### 4. Installer PHPMailer

Télécharge PHPMailer sur [https://github.com/PHPMailer/PHPMailer](https://github.com/PHPMailer/PHPMailer)

Copie les fichiers suivants dans `attestation/lib/PHPMailer/` :

```
PHPMailer.php
SMTP.php
Exception.php
```

---

## 🧪 Test initial

1. Accède au formulaire :  
   👉 https://TON-DOMAINE.FR/attestations/

2. Dépose une attestation test (PDF).

3. Vérifie :
   - fichier dans `storage/uploads/`
   - entrée dans `db/attestations.sqlite`
   - ligne dans `storage/logs/receptions.csv`

---

## ⏰ Crons automatiques (OVH)

### 1️⃣ Relances quotidiennes + suppression

- **Commande :**
  ```
  /usr/local/php8.2/bin/php /home/LOGIN/www/honorabilite/cron/reminders.php
  ```

- **Fréquence :** Tous les jours (ex. 00:15)

---

### 2️⃣ Livraison hebdomadaire à la direction

- **Commande :**
  ```
  /usr/local/php8.2/bin/php /home/LOGIN/www/honorabilite/cron/weekly_digest.php
  ```

- **Fréquence :** Chaque lundi à 08:00

---

## 🔐 Tableau de bord administrateur

URL :  
👉 https://TON-DOMAINE.FR/attestations/admin.php  

Identifiants : ceux définis dans `config.php`

### Fonctions :

- Vue globale (total, actives, expirées, supprimées)  
- Statistiques sur 7 et 30 jours  
- Liste filtrable (recherche, état, bientôt expirées)  
- Aucun lien de téléchargement  
- Bouton **Se déconnecter**

---

## 🔄 Fonctionnement du système

| Étape | Déclencheur | Résultat |
|-------|--------------|----------|
| **Dépôt** | Parent via formulaire | PDF sauvegardé + ligne DB |
| **Remplacement** | Même adresse email | Ancien fichier supprimé, nouvelle version enregistrée |
| **Expiration (6 mois)** | Cron `reminders.php` | Email de relance + suppression fichier |
| **Digest hebdo** | Cron `weekly_digest.php` | Email à la directrice si nouvelles attestations |
| **Consultation** | Board admin | Liste & statistiques sans lien direct |

---

## 🧾 Tests rapides

1. **Test SMTP / digest :**
   ```bash
   /usr/local/php8.2/bin/php cron/weekly_digest.php
   ```
   → La directrice reçoit le mail.

2. **Test relance :**
   - Modifie manuellement `expiry_at` dans la base (valeur passée).  
   - Exécute :
     ```bash
     /usr/local/php8.2/bin/php cron/reminders.php
     ```
   → Le parent reçoit un mail, le fichier est supprimé.

---

## 🛡️ Sécurité & maintenance

- Les fichiers PDF sont **hors webroot** : `storage/uploads/`  
- Seul `download.php` permet l’accès via un **token sécurisé**  
- Base SQLite non exposée via HTTP  
- Sauvegarde recommandée : `db/attestations.sqlite` + `storage/uploads/`  
- Les fichiers expirés sont supprimés automatiquement  
- Les logs sont conservés dans `storage/logs/`

---

## ✅ Récapitulatif express

| Action | Commande / URL | Fréquence |
|--------|----------------|------------|
| Créer la base | `php db/create_db.php` | une fois |
| Générer mot de passe admin | `php tools/make_admin_pass.php` | une fois |
| Formulaire de dépôt | https://TON-DOMAINE.FR/attestations/ | en continu |
| Relance + suppression | `cron/reminders.php` | chaque jour |
| Livraison hebdo | `cron/weekly_digest.php` | chaque lundi |
| Tableau de bord admin | https://TON-DOMAINE.FR/attestations/admin.php | à la demande |

---

🟢 **Tout est automatisé une fois configuré.**  
Les seuls suivis à faire : vérifier les mails hebdo et, de temps à autre, ouvrir le board admin.
