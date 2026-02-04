<?php
/**
 * Test Import Functionality
 * Debug script to test if movies are being created
 */

require_once 'includes/session.php';
require_once 'includes/db.php';

// Check if user is logged in as admin
if (!isLoggedIn() || getActiveRole() !== 'admin') {
    die('Admin access required');
}

echo "<h2>Import Test Debug</h2>";

// Check database connection
if (!$pdo) {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    exit;
}
echo "<p style='color: green;'>✓ Database connection OK</p>";

// Check if movies table exists
try {
    $result = $pdo->query("SELECT COUNT(*) as count FROM movies");
    $count = $result->fetch()['count'];
    echo "<p>✓ Movies table exists - Currently has $count movies</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Movies table error: " . $e->getMessage() . "</p>";
    exit;
}

// List recent movies
echo "<h3>Recent Movies (last 10):</h3>";
try {
    $stmt = $pdo->query("SELECT id, title, release_date, status, rating FROM movies ORDER BY created_at DESC LIMIT 10");
    $movies = $stmt->fetchAll();
    
    if (empty($movies)) {
        echo "<p><em>No movies in database</em></p>";
    } else {
        echo "<table border='1' cellpadding='8'>";
        echo "<tr><th>ID</th><th>Title</th><th>Release Date</th><th>Status</th><th>Rating</th></tr>";
        foreach ($movies as $m) {
            $status = $m['status'] ?? 'unknown';
            $rating = $m['rating'] ?? 0;
            echo "<tr>";
            echo "<td>{$m['id']}</td>";
            echo "<td>{$m['title']}</td>";
            echo "<td>{$m['release_date']}</td>";
            echo "<td>{$status}</td>";
            echo "<td>{$rating}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Query error: " . $e->getMessage() . "</p>";
}

// Check movies with 'ended' status (recently imported)
echo "<h3>Movies with 'ended' status (Quick-imported):</h3>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM movies WHERE status = 'ended'");
    $endedCount = $stmt->fetch()['count'];
    echo "<p>Found $endedCount movies with 'ended' status</p>";
    
    if ($endedCount > 0) {
        $stmt = $pdo->query("SELECT id, title, created_at FROM movies WHERE status = 'ended' ORDER BY created_at DESC LIMIT 5");
        $movies = $stmt->fetchAll();
        echo "<ul>";
        foreach ($movies as $m) {
            echo "<li>{$m['title']} (created: {$m['created_at']})</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Check API logs
echo "<h3>Recent API Log Entries:</h3>";
$logFile = __DIR__ . '/logs/php_errors.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -20);
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 400px; overflow: auto;'>";
    foreach ($recentLines as $line) {
        if (strpos($line, 'Letterboxd') !== false || strpos($line, 'bulk import') !== false) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p><em>No log file found</em></p>";
}

echo "<h3>How to Test Import:</h3>";
echo "<ol>";
echo "<li>Go to <a href='Admin/movies.php'>Admin Movies</a></li>";
echo "<li>Click 'Import from Letterboxd CSV'</li>";
echo "<li>Upload a CSV file with columns: Name, Year, Rating</li>";
echo "<li>Check this page again to see if movies were created</li>";
echo "</ol>";

?>
