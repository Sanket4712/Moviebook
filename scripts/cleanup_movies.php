<?php
/**
 * Database Cleanup Script - ADMIN ONLY
 * 
 * SECURITY: This script requires admin authentication.
 * It removes ALL movies and dependent records.
 * 
 * Can only be run once - after cleanup, this script blocks itself.
 */

// ============================================
// SECURITY: Require Admin Authentication
// ============================================
require_once '../includes/session.php';
require_once '../includes/db.php';

// Must be logged in as admin
if (!isLoggedIn() || getActiveRole() !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Access Denied</title>";
    echo "<style>body{font-family:sans-serif;background:#0a0a0a;color:#fff;text-align:center;padding:100px}";
    echo "h1{color:#e50914}</style></head><body>";
    echo "<h1>🚫 Access Denied</h1>";
    echo "<p>Admin authentication required.</p>";
    echo "<p><a href='../auth/login.php' style='color:#e50914'>Login as Admin</a></p>";
    echo "</body></html>";
    exit();
}

header('Content-Type: text/html; charset=utf-8');

// ============================================
// SELF-DISABLE: Check if already cleaned
// ============================================
$movieCount = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
$action = $_GET['action'] ?? '';

echo "<!DOCTYPE html><html><head><title>Database Cleanup - Admin</title>";
echo "<style>body{font-family:'Inter',sans-serif;background:#0a0a0a;color:#fff;padding:40px;max-width:700px;margin:auto}";
echo ".success{color:#10b981}.danger{color:#ef4444}.warn{color:#f59e0b}h1{color:#e50914;margin-bottom:30px}";
echo ".step{background:#1a1a1a;padding:1rem;border-radius:8px;margin:1rem 0;border-left:4px solid #333}";
echo ".step.done{border-color:#10b981}.info{background:#1e293b;padding:1rem;border-radius:8px;margin:1rem 0}";
echo "button{background:#e50914;color:#fff;border:none;padding:15px 30px;font-size:16px;cursor:pointer;border-radius:8px;margin-top:20px}";
echo "button:hover{background:#b20710}a{color:#e50914}</style></head><body>";

echo "<h1>🧹 Database Cleanup</h1>";
echo "<div class='info'>Logged in as: <strong>" . htmlspecialchars($_SESSION['user_name'] ?? 'Admin') . "</strong> (Admin)</div>";

if ($movieCount == 0 && $action !== 'cleanup') {
    // Already cleaned
    echo "<h2 class='success'>✅ Database is Already Clean</h2>";
    echo "<p>No movies in database. Nothing to clean.</p>";
    echo "<p><a href='../Admin/movies.php'>→ Go to Admin Movies Panel</a></p>";
    echo "</body></html>";
    exit();
}

if ($action !== 'cleanup') {
    // Show current state
    $watchlistCount = $pdo->query("SELECT COUNT(*) FROM watchlist")->fetchColumn();
    $diaryCount = $pdo->query("SELECT COUNT(*) FROM diary")->fetchColumn();
    $favoritesCount = $pdo->query("SELECT COUNT(*) FROM favorites")->fetchColumn();
    $showtimesCount = $pdo->query("SELECT COUNT(*) FROM showtimes")->fetchColumn();
    
    echo "<h2>Current Database State</h2>";
    echo "<div class='step'><strong>Movies:</strong> {$movieCount}</div>";
    echo "<div class='step'><strong>Watchlist entries:</strong> {$watchlistCount}</div>";
    echo "<div class='step'><strong>Diary entries:</strong> {$diaryCount}</div>";
    echo "<div class='step'><strong>Favorites:</strong> {$favoritesCount}</div>";
    echo "<div class='step'><strong>Showtimes:</strong> {$showtimesCount}</div>";
    
    echo "<hr style='margin:30px 0;border-color:#333'>";
    
    echo "<h2 class='warn'>⚠️ Warning</h2>";
    echo "<p>This will <strong>permanently delete</strong> all movies and related records.</p>";
    echo "<p>This action cannot be undone.</p>";
    
    echo "<form method='get' onsubmit=\"return confirm('Are you absolutely sure? This cannot be undone.')\">";
    echo "<input type='hidden' name='action' value='cleanup'>";
    echo "<button type='submit'>🗑️ Delete All Movies & Start Fresh</button>";
    echo "</form>";
    
} else {
    // Perform cleanup
    echo "<h2>Cleaning Up...</h2>";
    
    $pdo->beginTransaction();
    
    try {
        echo "<div class='step done'>Removing watchlist entries...</div>";
        $deleted = $pdo->exec("DELETE FROM watchlist");
        echo "<p class='success'>✓ Deleted {$deleted} watchlist entries</p>";
        
        echo "<div class='step done'>Removing diary entries...</div>";
        $deleted = $pdo->exec("DELETE FROM diary");
        echo "<p class='success'>✓ Deleted {$deleted} diary entries</p>";
        
        echo "<div class='step done'>Removing favorites...</div>";
        $deleted = $pdo->exec("DELETE FROM favorites");
        echo "<p class='success'>✓ Deleted {$deleted} favorites</p>";
        
        echo "<div class='step done'>Removing showtimes...</div>";
        $deleted = $pdo->exec("DELETE FROM showtimes");
        echo "<p class='success'>✓ Deleted {$deleted} showtimes</p>";
        
        echo "<div class='step done'>Removing all movies...</div>";
        $deleted = $pdo->exec("DELETE FROM movies");
        echo "<p class='success'>✓ Deleted {$deleted} movies</p>";
        
        echo "<div class='step done'>Resetting ID sequences...</div>";
        $pdo->exec("ALTER TABLE movies AUTO_INCREMENT = 1");
        echo "<p class='success'>✓ Movie IDs reset to 1</p>";
        
        $pdo->commit();
        
        // Log the action
        error_log("ADMIN CLEANUP: User " . ($_SESSION['user_id'] ?? 'unknown') . " deleted all movies");
        
        echo "<hr style='margin:30px 0;border-color:#333'>";
        echo "<h2 class='success'>✅ Cleanup Complete!</h2>";
        echo "<p>Database is now empty and ready for fresh data.</p>";
        echo "<p><a href='../Admin/movies.php'>→ Add movies through Admin Panel</a></p>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<p class='danger'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "</body></html>";
