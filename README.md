# 🧾 Système de gestion des attestations d'honorabilité

Une application PHP simple et robuste pour collecter, suivre et gérer les attestations d’honorabilité des intervenants. Elle inclut des rappels automatiques, un portail de gestion pour les administrateurs et un accès sécurisé pour la direction.

---

## 🎯 Fonctionnalités

*   **Portail de Dépôt Public** : Un formulaire simple et sécurisé pour que les intervenants puissent téléverser leur attestation au format PDF.
*   **Date de Fin de Validité** : Le formulaire propose une date de fin de validité par défaut (6 mois à partir du jour même), que l'utilisateur peut ajuster si nécessaire.
*   **Tableau de Bord Administrateur** : Une interface sécurisée pour les administrateurs avec des statistiques, une liste filtrable des attestations et des actions manuelles (rappel, suppression).
*   **Rappels Automatiques** : Un script cron envoie des rappels par e-mail aux intervenants avant l'expiration de leur attestation.
*   **Purge Automatique** : Les attestations expirées sont automatiquement marquées comme supprimées et les fichiers associés sont effacés.
*   **Portail pour la Direction** : Un lien sécurisé, envoyé par e-mail, permet à la direction de consulter la liste des attestations actives et de télécharger une archive ZIP.
*   **Journalisation** : Les actions importantes (uploads, erreurs) sont journalisées pour l'audit.

---

## 📁 Structure du projet

```
.
├── config_exemple.php      # Fichier d'exemple de configuration
├── config.php              # Fichier de configuration (à créer)
├── db/
│   ├── create_db.php       # Script pour créer la base de données SQLite
│   └── attestations.sqlite # Fichier de la base de données (généré)
├── cron/
│   ├── reminders.php       # Tâche cron pour les rappels et la purge
│   ├── weekly_digest.php   # Tâche cron pour le rapport hebdomadaire à la direction
│   └── purge.php           # Tâche cron pour la suppression définitive des données
├── lib/
│   └── sendmail.php        # Utilitaire pour l'envoi d'e-mails (via PHPMailer)
├── public/
│   ├── index.php           # Formulaire de dépôt public
│   ├── upload.php          # Script de traitement du téléversement
│   ├── admin.php           # Tableau de bord administrateur
│   ├── admin_actions.php   # Script de traitement des actions admin
│   ├── rapport.php         # Portail de consultation pour la direction
│   ├── archive.php         # Génération de l'archive ZIP pour la direction
│   └── download.php        # Script de téléchargement sécurisé des fichiers
├── storage/
│   ├── uploads/            # Stockage des fichiers PDF (hors webroot)
│   ├── logs/               # Fichiers de log
│   └── master_token.txt    # Jeton d'accès temporaire pour le rapport de la direction
└── tools/
    └── make_admin_pass.php # Outil pour générer le hachage du mot de passe admin
```

---

## ⚙️ Prérequis

*   PHP 8.1+
*   Extensions PHP : `pdo_sqlite`, `fileinfo`, `mbstring`, `zip`.
*   Un serveur web (Apache, Nginx, etc.).
*   Un compte e-mail pour l'envoi des notifications (compatible SMTP).

---

## 🚀 Installation

1.  **Téléverser les fichiers** sur votre serveur.

2.  **Configurer le projet** :
    *   Copiez `config_exemple.php` vers `config.php`.
    *   Modifiez `config.php` pour définir les chemins (`storage_dir`, `db_file`, etc.), l'URL de base (`site_base_url`), et les paramètres SMTP pour l'envoi d'e-mails.

3.  **Créer la base de données** :
    *   Assurez-vous que le répertoire `db/` est inscriptible par le serveur web.
    *   Exécutez le script de création en ligne de commande :
        ```bash
        # Assurez-vous que l'utilisateur www-data (ou l'utilisateur de votre serveur web) a les droits
        sudo -u www-data php db/create_db.php
        ```

4.  **Créer un compte administrateur** :
    *   Générez un hachage de mot de passe :
        ```bash
        php tools/make_admin_pass.php
        ```
    *   Copiez le hachage généré et collez-le dans `config.php` à la clé `admin.pass_hash`. Définissez également un nom d'utilisateur.

5.  **Configurer les permissions** :
    *   Le serveur web (généralement `www-data`) doit avoir les droits d'écriture sur les répertoires `storage/` et `db/`.
        ```bash
        sudo chown -R www-data:www-data storage db
        sudo chmod -R 775 storage db
        ```

6.  **Configurer les tâches CRON** :
    *   Ajoutez les tâches suivantes à votre crontab (`crontab -e`) pour automatiser les rappels et les rapports. Adaptez le chemin vers PHP et les scripts.

    ```cron
    # Rappels d'expiration et suppression logique des attestations expirées (tous les jours à 2h)
    0 2 * * * /usr/bin/php /path/to/your/project/cron/reminders.php

    # Rapport hebdomadaire pour la direction (tous les lundis à 8h)
    0 8 * * 1 /usr/bin/php /path/to/your/project/cron/weekly_digest.php

    # Purge définitive des attestations marquées comme supprimées (tous les jours à 3h)
    0 3 * * * /usr/bin/php /path/to/your/project/cron/purge.php
    ```

---

## 🗃️ Modèle de Données (SQLite)

La base de données contient une table `attestations` avec la structure suivante :

```sql
CREATE TABLE attestations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nom TEXT NOT NULL,
  prenom TEXT NOT NULL,
  parent_email TEXT NOT NULL UNIQUE,
  filename TEXT NOT NULL,
  token TEXT NOT NULL UNIQUE,
  uploaded_at INTEGER NOT NULL,
  expiry_at INTEGER NOT NULL,     -- Date de fin de validité, fournie par l'utilisateur
  reminder_sent INTEGER DEFAULT 0,
  deleted_at INTEGER DEFAULT NULL -- Timestamp de suppression (soft delete)
);
```

---

## 🛡️ Sécurité

*   **Stockage des fichiers** : Les fichiers PDF sont stockés en dehors du répertoire web public (`public/`) pour empêcher l'accès direct. Ils sont servis via un script PHP (`download.php`) qui vérifie les permissions.
*   **Noms de fichiers** : Les noms de fichiers originaux sont remplacés par des UUIDs pour éviter les conflits et les injections.
*   **Authentification** : L'accès au panneau d'administration est protégé par un nom d'utilisateur et un mot de passe haché (Argon2ID).
*   **Protection CSRF** : Des jetons CSRF sont utilisés sur tous les formulaires pour se prémunir contre les attaques de type Cross-Site Request Forgery.
*   **Honeypot** : Un champ caché est présent dans le formulaire de dépôt pour tromper les bots spammeurs.
*   **Validation des données** : Toutes les données entrantes (champs de formulaire, fichiers) sont rigoureusement validées côté serveur.
*   **Soft Delete** : Les attestations ne sont pas immédiatement supprimées de la base de données, mais marquées avec un `deleted_at`, permettant une éventuelle récupération et assurant l'intégrité des logs.