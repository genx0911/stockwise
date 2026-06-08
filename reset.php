<?php
require_once 'config/database.php';
$db = Database::getInstance();
$hash = password_hash('admin123', PASSWORD_BCRYPT);
$db->prepare("UPDATE users SET password = ? WHERE email = 'admin@stockwise.com'")->execute([$hash]);
$db->prepare("UPDATE users SET password = ? WHERE email = 'staff@stockwise.com'")->execute([$hash]);
echo "Done! Password reset to: admin123";
?>