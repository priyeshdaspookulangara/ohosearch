<?php
require __DIR__ . '/vendor/autoload.php';

use App\Database;

$db = Database::getInstance();

$password = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $db->prepare("INSERT OR IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->execute(['Admin User', 'admin@example.com', $password, 'admin']);

$passwordContr = password_hash('contr123', PASSWORD_BCRYPT);
$stmt->execute(['Contributor User', 'contr@example.com', $passwordContr, 'contributor']);

echo "Users seeded successfully.\n";
