<?php
// db/create_db.php
$dbfile = __DIR__ . '/attestations.sqlite';
if (file_exists($dbfile)) { echo "DB exists at $dbfile\n"; exit; }
$db = new PDO('sqlite:' . $dbfile);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
CREATE TABLE attestations (
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
CREATE INDEX idx_expiry ON attestations(expiry_at);
");

echo "DB created at $dbfile\n";