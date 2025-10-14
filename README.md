# 🧾 Système de gestion des attestations d'honorabilité

Collecter, suivre et gérer automatiquement les attestations d’honorabilité des parents intervenants, avec un portail de consultation sécurisé pour la direction.

---

## 🎯 Objectif

Le système assure :
- **Dépôt de fichiers** simple et sécurisé via un formulaire public.
- **Portail web sécurisé** pour la direction listant toutes les attestations valides.
- **Téléchargement en un clic** d’une **archive ZIP** de tous les documents valides.
- **Notification par e‑mail** à la direction uniquement si de **nouvelles** attestations sont déposées.
- **Suppression automatique** des fichiers et des données **après 6 mois**.
- **Relance automatique** par e‑mail **avant l’expiration**.
- **Tableau de bord administratif** pour le suivi global.

---

## 📁 Structure du projet

```
attestation/
│
├── config.php                  → Configuration principale (chemins, SMTP, admin)
│
├── db/
│   ├── create_db.php           → Script de création de la base SQLite
│   └── attestations.sqlite     → Base de données (générée)
│
├── storage/
│   ├── uploads/                → Stockage sécurisé des PDF (hors webroot)
│   ├── logs/                   → Journaux d'activité (CSV et logs)
│   └── master_token.txt        → Token temporaire pour le rapport de la direction
│
├── cron/
│   ├── reminders.php           → Cron quotidien : relances + purge expirations
│   └── weekly_digest.php       → Cron hebdo : envoi lien de rapport à la direction
│
├── lib/
│   ├── PHPMailer/              → Librairie d’e-mails
│   └── sendmail.php            → Wrapper pour PHPMailer
│
├── public/
│   ├── index.php               → Formulaire de dépôt public
│   ├── upload.php              → Traitement de l’envoi
│   ├── download.php            → Téléchargement sécurisé d’un fichier
│   ├── admin.php               → Tableau de bord admin
│   ├── rapport.php             → **NOUVEAU** Portail direction (liste + téléchargements)
│   └── archive.php             → **NOUVEAU** Génération ZIP à la volée
│
└── tools/                      → Outils CLI (optionnel)
    ├── make_admin_pass.php     → Générateur de hash du mot de passe admin
    └── create_db_web.php       → Variante Web temporaire de création BDD
```

> Notes
> - Les répertoires `db/`, `storage/`, `storage/uploads/`, `storage/logs/` doivent être **inscriptibles** par le serveur web.
> - `storage/uploads/` doit se trouver **hors webroot** si possible. Si ce n’est pas le cas, protéger par règles serveur (deny all) et accès via proxy PHP uniquement.

---

## ⚙️ Prérequis

- **PHP 8.1+**
- Extensions PHP : `pdo_sqlite`, `fileinfo`, `zip` (classe `ZipArchive`).
- Accès **SSH** ou **FTP** pour l’installation.
- Un compte e‑mail (ex : Google Workspace) avec **mot de passe d’application**.
- Serveur web configuré pour exécuter PHP (Apache, Nginx, etc.).

---

## 🚀 Installation

### 1) Créer la base de données SQLite

**Via SSH (recommandé)** :
```bash
cd db
/usr/local/php8.2/bin/php create_db.php
```

**Alternative Web (temporaire)** : exposer `tools/create_db_web.php` le temps de l’initialisation, puis **supprimer** le fichier.

---

### 2) Générer le mot de passe administrateur

```bash
cd tools
/usr/local/php8.2/bin/php make_admin_pass.php
```
Copiez le **hash généré** et collez‑le dans `config.php` > `admin.pass_hash`.

---

### 3) Configurer `config.php`

Copier `config_exemple.php` vers `config.php`, puis ajuster :
- `site_base_url`
- `director_email`
- Bloc `smtp` : hôte, port, utilisateur, mot de passe d’application, sécurité
- `admin.pass_hash` (hash généré à l’étape 2)
- Chemins absolus vers `storage/` et sous‑dossiers

> Bonnes pratiques : prévoir des **variables d’environnement** (ex : via `.env` chargé par `config.php`) pour les secrets SMTP.

---

## ⏰ Tâches automatisées (CRON)

### 1️⃣ Relances quotidiennes + purge
- **Rôle** : envoie les e‑mails de **rappel** avant expiration et **supprime** les attestations expirées.
- **Commande** :
```bash
/usr/local/php8.2/bin/php /path/to/attestation/cron/reminders.php
```
- **Fréquence** : tous les jours, ex : `15 0 * * *`.

### 2️⃣ Rapport hebdomadaire à la direction
- **Rôle** : envoie un e‑mail **uniquement** s’il y a eu **de nouveaux dépôts** depuis le dernier rapport, avec un **lien sécurisé** à usage unique vers `public/rapport.php`.
- **Commande** :
```bash
/usr/local/php8.2/bin/php /path/to/attestation/cron/weekly_digest.php
```
- **Fréquence** : chaque lundi à 08:00, ex : `0 8 * * 1`.

---

## 🔐 Interfaces de gestion

### 1. Portail de la Direction
- **Accès** : via lien sécurisé à **usage unique** envoyé par e‑mail hebdomadaire si activité.
- **Fonctionnalités** :
  - Liste des attestations **valides** : Nom, Prénom, Date d’expiration.
  - **Téléchargement individuel** sécurisé.
  - **Téléchargement ZIP** de toutes les attestations valides.

### 2. Tableau de bord Administrateur
- **URL** : `https://VOTRE-DOMAINE.FR/attestation/public/admin.php`
- **Fonctionnalités** :
  - Vue globale + statistiques (total, actives, expirées).
  - Liste filtrable/paginée de toutes les attestations, y compris expirées/supprimées.
  - **Aucun lien de téléchargement** n’est affiché en admin pour des raisons de sécurité.

---

## 🔄 Fonctionnement du système

| Étape         | Déclencheur                  | Résultat                                                                 |
|---------------|------------------------------|--------------------------------------------------------------------------|
| Dépôt         | Parent via formulaire        | PDF sauvegardé, enregistrement BDD, log CSV                              |
| Remplacement  | Dépôt avec e‑mail existant   | Ancien fichier supprimé, nouveau pris en compte                          |
| Rapport hebdo | `weekly_digest.php`          | S’il y a du nouveau : e‑mail direction + lien sécurisé vers `rapport.php` |
| Consultation  | Lien reçu par la direction   | Accès à la liste et téléchargements                                      |
| Expiration    | `reminders.php`              | E‑mail de relance au parent, **suppression** du fichier expiré           |
| Suivi admin   | `admin.php`                  | Stats + historique                                                       |

---

## 🛡️ Sécurité

- Les PDF sont stockés **hors webroot**. L’accès se fait par **proxy PHP** via `download.php` avec **tokens uniques** et durée de vie limitée.
- Le **portail direction** est protégé par un **master token** temporaire stocké dans `storage/master_token.txt`. Les liens envoyés expirent automatiquement.
- L’**admin** est protégé par **mot de passe** (hash Argon2ID ou bcrypt) et verrouillage progressif des tentatives.
- Les fichiers **expirés** sont purgés physiquement du serveur et leurs traces sont anonymisées dans les logs si requis.
- Les en‑têtes `Content-Disposition` et `X-Content-Type-Options: nosniff` sont forcés pour les téléchargements.
- Les uploads sont validés : **MIME type** via `finfo`, taille max, **scan simple** PDF, nom normalisé, dossier par **UUID**.
- **CSRF**: jetons sur les formulaires, cookies `SameSite=Lax`, désérialisation interdite.
- **Rate‑limit** basique côté dépôt public. Captcha optionnel.

---

## 🗃️ Modèle de données (SQLite)

Table `attestations` (exemple minimal) :
```sql
CREATE TABLE IF NOT EXISTS attestations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  parent_email TEXT NOT NULL,
  parent_firstname TEXT NOT NULL,
  parent_lastname TEXT NOT NULL,
  file_path TEXT NOT NULL,
  uploaded_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  last_digest_at DATETIME,
  checksum TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'active' -- active | expired | deleted
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_attestations_email ON attestations(parent_email);
CREATE INDEX IF NOT EXISTS idx_attestations_expires ON attestations(expires_at);
```
---

## 🔧 Configuration – exemple

```php
<?php
return [
  'site_base_url' => 'https://VOTRE-DOMAINE.FR/attestation/public',
  'paths' => [
    'storage' => '/var/www/attestation/storage',
    'uploads' => '/var/www/attestation/storage/uploads',
    'logs'    => '/var/www/attestation/storage/logs',
    'db'      => '/var/www/attestation/db/attestations.sqlite',
  ],
  'smtp' => [
    'host' => 'smtp.example.com',
    'port' => 587,
    'user' => 'notifier@example.com',
    'pass' => 'MOTDEPASSE_APPLICATION',
    'secure' => 'tls',
    'from' => ['address' => 'notifier@example.com', 'name' => 'Attestations Bot'],
  ],
  'director_email' => 'direction@example.com',
  'admin' => [
    'pass_hash' => 'COLLER_LE_HASH_ICI',
    'session_name' => 'attestation_admin',
  ],
  'security' => [
    'token_ttl_minutes' => 30,
    'digest_lookback_days' => 7
  ],
  'retention_months' => 6
];
```
---

## ✉️ Flux e‑mail

- **Parents** : accusé de réception optionnel, relance avant expiration.
- **Direction** : e‑mail **uniquement si** nouveautés la semaine écoulée, avec **lien sécurisé**.
- **Expéditeur** : utiliser une adresse dédiée (éviter « no‑reply » pour la délivrabilité).

---

## 🧰 Dépannage rapide

- **ZIP vide** : vérifier droits sur `storage/uploads/` et statuts `active` en BDD.
- **Mails non reçus** : vérifier SMTP, SPF/DKIM/DMARC, ports sortants, file d’attente CRON.
- **Token invalide** : TTL trop court, horloge serveur, cache reverse‑proxy.
- **Upload refusé** : MIME/type non PDF, taille trop grande, fichier corrompu.

---

## 🔁 Sauvegarde et restauration

- **Sauvegarder** : `db/attestations.sqlite` et `storage/uploads/`.
- **Restaurer** : remettre les répertoires, puis exécuter `create_db.php` si schéma absent.
- Les tokens et journaux peuvent être régénérés au besoin.

---

## 🧪 Tests rapides (manuels)

1. Déposer 2 PDF avec le **même e‑mail** : vérifier le **remplacement** et checksum.
2. Forcer une **expiration** courte en BDD et lancer `reminders.php` : purge + e‑mail.
3. Créer 1 nouvel enregistrement et lancer `weekly_digest.php` : e‑mail direction avec lien.
4. Ouvrir le **lien** : voir la liste valide + **ZIP** fonctionnel.

---

## 🔭 Roadmap courte

- Double stockage optionnel chiffré (libsodium).
- SSO pour l’admin (OIDC).
- Webhooks d’audit vers SI interne.
- Captcha adaptatif côté dépôt.

---

## ✅ Check‑list d’exploitation

- [ ] `config.php` correctement rempli
- [ ] CRONs actifs et logués
- [ ] Répertoires inscriptibles
- [ ] SPF/DKIM/DMARC OK
- [ ] Accès admin protégé et testé
- [ ] Portail direction testé avec lien temps‑réel
- [ ] Sauvegardes programmées

---

## 📜 Licence

Projet interne. À adapter selon la politique de votre organisation.
