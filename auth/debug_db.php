<?php
/**
 * Database Debug Utility
 * Shows actual table structure to fix column mismatches
 */

require_once '../includes/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>MovieBook - Database Debug</h2>";

if (!$pdo) {
    die("<p style='color:red'>Database connection failed!</p>");
}

echo "<h3>Users Table Structure:</h3>";

try {
    // Get actual column names
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Sample User Data:</h3>";
    $users = $pdo->query("SELECT * FROM users LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p>No users found in database.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>";
        foreach (array_keys($users[0]) as $key) {
            echo "<th>{$key}</th>";
        }
        echo "</tr>";
        foreach ($users as $user) {
            echo "<tr>";
            foreach ($user as $key => $val) {
                // Mask password
                if ($key === 'password') {
                    $val = substr($val, 0, 15) . '...';
                }
                echo "<td>" . htmlspecialchars($val ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
