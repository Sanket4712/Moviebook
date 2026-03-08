<?php
/**
 * MovieBook - Database Migration Runner
 * 
 * Run this to add TMDB support to the database.
 * Access: http://localhost/Moviebook/scripts/run_migration.php
 */

header('Content-Type: text/html; charset=utf-8');

require_once '../includes/db.php';

echo "<!DOCTYPE html><html><head><title>Run Migration</title>";
echo "<style>body{font-family:sans-serif;background:#1a1a1a;color:#fff;padding:40px;max-width:800px;margin:auto}";
echo ".success{color:#00c853;padding:10px;background:#1a1a1a;border-left:3px solid #00c853;margin:10px 0}";
echo ".error{color:#ff1744;padding:10px;background:#1a1a1a;border-left:3px solid #ff1744;margin:10px 0}";
echo ".info{color:#2196f3;padding:10px;background:#1a1a1a;border-left:3px solid #2196f3;margin:10px 0}";
echo "h1{color:#e50914}pre{background:#0a0a0a;padding:15px;border-radius:6px;overflow-x:auto}</style></head><body>";

echo "<h1>🔧 MovieBook Database Migration</h1>";

if (!$pdo) {
    echo "<div class='error'>❌ Database connection failed!</div>";
    exit;
}

$migrations = [];

// Migration 1: Add tmdb_id column
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM movies LIKE 'tmdb_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE movies ADD COLUMN tmdb_id INT UNIQUE AFTER id");
        $migrations[] = ['success', 'Added tmdb_id column to movies table'];
    } else {
        $migrations[] = ['info', 'tmdb_id column already exists'];
    }
} catch (PDOException $e) {
    $migrations[] = ['error', 'Failed to add tmdb_id: ' . $e->getMessage()];
}

// Migration 2: Add vote_count column
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM movies LIKE 'vote_count'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE movies ADD COLUMN vote_count INT DEFAULT 0 AFTER rating");
        $migrations[] = ['success', 'Added vote_count column'];
    } else {
        $migrations[] = ['info', 'vote_count column already exists'];
    }
} catch (PDOException $e) {
    $migrations[] = ['error', 'Failed to add vote_count: ' . $e->getMessage()];
}

// Migration 3: Add popularity column  
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM movies LIKE 'popularity'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE movies ADD COLUMN popularity DECIMAL(10,3) DEFAULT 0 AFTER vote_count");
        $migrations[] = ['success', 'Added popularity column'];
    } else {
        $migrations[] = ['info', 'popularity column already exists'];
    }
} catch (PDOException $e) {
    $migrations[] = ['error', 'Failed to add popularity: ' . $e->getMessage()];
}

// Report results
echo "<h2>Migration Results</h2>";
foreach ($migrations as $m) {
    $class = $m[0];
    $msg = $m[1];
    $icon = $class === 'success' ? '✅' : ($class === 'error' ? '❌' : 'ℹ️');
    echo "<div class='{$class}'>{$icon} {$msg}</div>";
}

// Show current schema
echo "<h2>Current Movies Table Schema</h2>";
echo "<pre>";
$stmt = $pdo->query("DESCRIBE movies");
while ($row = $stmt->fetch()) {
    echo "{$row['Field']} - {$row['Type']}" . ($row['Key'] ? " ({$row['Key']})" : "") . "\n";
}
echo "</pre>";

// Show movie count
$stmt = $pdo->query("SELECT COUNT(*) FROM movies");
$count = $stmt->fetchColumn();
echo "<p>Total movies in database: <strong>{$count}</strong></p>";

echo "<hr>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li><a href='import_movies.php' style='color:#e50914'>Import Movies from TMDB →</a></li>";
echo "<li><a href='../User/films.php' style='color:#e50914'>View Films Page →</a></li>";
echo "<li><a href='../User/profile.php' style='color:#e50914'>View Profile →</a></li>";
echo "</ol>";

echo "</body></html>";
