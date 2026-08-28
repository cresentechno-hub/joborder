<?php

declare(strict_types=1);

// Run from the project root (Laragon Terminal or cmd with php on PATH):
//   php database/seed_admin.php
// Creates the first Admin login. password_hash() must run in PHP, which is
// why this isn't baked into schema.sql.

if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

require __DIR__ . '/../config/bootstrap.php';

use App\Core\Database;
use App\Models\User;

function prompt(string $label): string
{
    echo $label;
    return trim((string) fgets(STDIN));
}

echo "=== Job Order System - Create first Admin user ===" . PHP_EOL;

$fullName = prompt('Full name: ');
$username = prompt('Username: ');
$email    = prompt('Email: ');
$password = prompt('Password (min 8 chars): ');

if (strlen($password) < 8) {
    fwrite(STDERR, 'Password must be at least 8 characters.' . PHP_EOL);
    exit(1);
}

if ($fullName === '' || $username === '' || $email === '') {
    fwrite(STDERR, 'Full name, username and email are required.' . PHP_EOL);
    exit(1);
}

$pdo = Database::getInstance();
$stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Admin' LIMIT 1");
$stmt->execute();
$role = $stmt->fetch();

if (!$role) {
    fwrite(STDERR, 'Admin role not found. Did you import database/schema.sql?' . PHP_EOL);
    exit(1);
}

if (User::findByUsername($username) || User::findByEmail($email)) {
    fwrite(STDERR, 'A user with that username/email already exists.' . PHP_EOL);
    exit(1);
}

$id = User::create([
    'username'      => $username,
    'email'         => $email,
    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
    'full_name'     => $fullName,
    'role_id'       => $role['id'],
    'is_active'     => 1,
]);

echo "Admin user #{$id} ({$username}) created successfully." . PHP_EOL;
