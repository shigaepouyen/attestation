<?php
// config.example.php
return [
  // Chemins
  'storage_dir' => __DIR__ . '/storage/uploads',
  'log_dir'     => __DIR__ . '/storage/logs',
  'db_file'     => __DIR__ . '/db/attestations.sqlite',
  'csv_path'    => __DIR__ . '/storage/logs/receptions.csv',

  // Limites
  'max_size_mb' => 10,
  'allowed_mime' => ['application/pdf'],
  'allowed_ext'  => ['pdf'],
  'filename_suffix'=> '_AttestationHonorabilite.pdf',

  // URL publique (exemple)
  'site_base_url' => 'https://exemple.fr/attestations',

  // Destinataire du digest hebdo (exemple)
  'director_email' => 'direction@exemple.fr',
  'director_title' => 'Madame la Directrice',
  'director_email_cc' => [
    // 'copie1@exemple.fr',
    // 'copie2@exemple.fr',
  ],
  'digest_period_days' => 7, // Période pour le digest (e.g., 7 pour un rapport hebdo)

  // SMTP via Google Workspace (expéditeur)
  'smtp' => [
    'enabled' => true,
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'secure' => 'tls',
    'user' => 'attestations@exemple.fr',
    'pass' => 'motdepasse_application',
    'from_email' => 'contact@exemple.fr',
    'from_name'  => 'APEL Exemple'
  ],

  // Admin board
  'admin' => [
    'user' => 'admin',
    'pass_hash' => 'HASH_ADMIN',
    'page_size' => 25
  ],

  // Nombre de jours avant purge définitive des attestations supprimées
  'purge_deleted_after_days' => 365
];
