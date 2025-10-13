<?php
// tools/make_admin_pass.php  (CLI only)
if (PHP_SAPI !== 'cli') { echo "Usage: php make_admin_pass.php\n"; exit(1); }
echo "Mot de passe admin (stdin): ";
$pw = trim(fgets(STDIN));
if ($pw === '') { echo "Empty. Abort.\n"; exit(1); }
echo "Hash (à coller dans config.php > admin > pass_hash) :\n";
echo password_hash($pw, PASSWORD_DEFAULT) . "\n";