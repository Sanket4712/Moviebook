<?php
require_once 'includes/db.php';

$password = 'sa416208';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Generated hash: " . $hash . "\n";

$stmt = $pdo->prepare("UPDATE users SET password = ?");
$result = $stmt->execute([$hash]);

if ($result) {
    echo "Password updated successfully for all users!\n";
    
    // Verify
    $check = $pdo->query("SELECT id, email, LEFT(password, 30) as pwd_start FROM users")->fetchAll();
    foreach ($check as $row) {
        echo "User {$row['id']} ({$row['email']}): {$row['pwd_start']}...\n";
    }
} else {
    echo "Failed to update password\n";
}
