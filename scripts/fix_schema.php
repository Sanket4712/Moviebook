<?php
/**
 * Add Missing Columns to Movies Table
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Fix Database Schema</title>";
echo "<style>body{font-family:sans-serif;background:#0a0a0a;color:#fff;padding:40px;max-width:900px;margin:auto}";
echo ".success{color:#00c853}.error{color:#ff1744}h1{color:#e50914}</style></head><body>";
echo "<h1>🔧 Fixing Database Schema</h1>";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=moviebook;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p class='success'>✅ Database connected</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ DB Error: " . $e->getMessage() . "</p>";
    exit("</body></html>");
}

// Columns to add
$columns = [
    'poster_url' => "ALTER TABLE movies ADD COLUMN poster_url VARCHAR(500) AFTER description",
    'backdrop_url' => "ALTER TABLE movies ADD COLUMN backdrop_url VARCHAR(500) AFTER poster_url",
    'director' => "ALTER TABLE movies ADD COLUMN director VARCHAR(100) AFTER backdrop_url",
    'genre' => "ALTER TABLE movies ADD COLUMN genre VARCHAR(100) AFTER director",
    'runtime' => "ALTER TABLE movies ADD COLUMN runtime INT AFTER genre",
    'rating' => "ALTER TABLE movies ADD COLUMN rating DECIMAL(3,1) DEFAULT 0.0 AFTER runtime",
    'release_date' => "ALTER TABLE movies ADD COLUMN release_date DATE AFTER rating",
    'status' => "ALTER TABLE movies ADD COLUMN status ENUM('now_showing', 'coming_soon', 'ended') DEFAULT 'now_showing' AFTER release_date",
    'tmdb_id' => "ALTER TABLE movies ADD COLUMN tmdb_id INT UNIQUE AFTER id"
];

foreach ($columns as $col => $sql) {
    try {
        // Check if column exists
        $check = $pdo->query("SHOW COLUMNS FROM movies LIKE '$col'");
        if ($check->rowCount() == 0) {
            $pdo->exec($sql);
            echo "<p class='success'>✅ Added column: $col</p>";
        } else {
            echo "<p>✓ Column exists: $col</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>⚠️ $col: " . $e->getMessage() . "</p>";
    }
}

echo "<hr><h2 class='success'>Schema updated!</h2>";
echo "<p><a href='simple_fix.php' style='color:#e50914'>→ Now run poster fix</a></p>";
echo "<p><a href='../User/films.php' style='color:#e50914'>→ View Films</a></p>";
echo "</body></html>";
