<?php
/**
 * Run User Roles Migration
 * Creates user_roles table and migrates data from users.role
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/db.php';

echo "<h2>User Roles Migration</h2>";

try {
    // Create user_roles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_roles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            role ENUM('user', 'admin', 'theater') NOT NULL,
            theater_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (theater_id) REFERENCES theaters(id) ON DELETE SET NULL,
            UNIQUE KEY unique_user_role_theater (user_id, role, theater_id)
        ) ENGINE=InnoDB
    ");
    echo "<p style='color:green'>✅ user_roles table ready</p>";

    // Migrate theater roles (only with valid theater_id)
    $stmt = $pdo->exec("
        INSERT IGNORE INTO user_roles (user_id, role, theater_id)
        SELECT u.id, 'theater', t.id
        FROM users u
        INNER JOIN theaters t ON t.owner_id = u.id
        WHERE u.role = 'theater'
    ");
    echo "<p>✅ Migrated theater roles</p>";

    // All users get 'user' role
    $stmt = $pdo->exec("
        INSERT IGNORE INTO user_roles (user_id, role, theater_id)
        SELECT id, 'user', NULL FROM users
    ");
    echo "<p>✅ Added user roles</p>";

    // Admin roles
    $stmt = $pdo->exec("
        INSERT IGNORE INTO user_roles (user_id, role, theater_id)
        SELECT id, 'admin', NULL FROM users WHERE role = 'admin'
    ");
    echo "<p>✅ Added admin roles</p>";

    // Show results
    echo "<h3>Current User Roles:</h3>";
    $roles = $pdo->query("
        SELECT ur.id, u.email, ur.role, ur.theater_id, t.name as theater_name
        FROM user_roles ur
        JOIN users u ON ur.user_id = u.id
        LEFT JOIN theaters t ON ur.theater_id = t.id
        ORDER BY u.email, ur.role
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>Email</th><th>Role</th><th>Theater</th></tr>";
    foreach ($roles as $r) {
        $theater = $r['theater_name'] ?? '-';
        echo "<tr><td>{$r['email']}</td><td><b>{$r['role']}</b></td><td>{$theater}</td></tr>";
    }
    echo "</table>";

    echo "<p style='color:green; font-weight:bold'>✅ Migration complete!</p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}
