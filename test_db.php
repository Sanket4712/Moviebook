<?php
require_once 'config.php';
header('Content-Type: text/plain');

try {
    $conn = getDBConnection();
    
    echo "=== DATABASE CONNECTION ===\n";
    echo "✓ Connected successfully to database: " . DB_NAME . "\n\n";
    
    echo "=== CHECKING TABLES ===\n";
    
    $tables = ['users', 'user_library', 'tmdb_cache', 'reviews', 'our_picks', 'movies', 'admin_users'];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count = $count_result->fetch_assoc()['count'];
            echo "✓ $table - EXISTS ($count rows)\n";
            
            // Show structure for new tables
            if (in_array($table, ['users', 'user_library', 'tmdb_cache'])) {
                $columns = $conn->query("SHOW COLUMNS FROM $table");
                echo "  Columns: ";
                $cols = [];
                while ($col = $columns->fetch_assoc()) {
                    $cols[] = $col['Field'];
                }
                echo implode(', ', $cols) . "\n";
            }
        } else {
            echo "✗ $table - MISSING\n";
        }
    }
    
    echo "\n=== SAMPLE DATA ===\n";
    
    // Check if any users exist
    $user_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    echo "Total users: $user_count\n";
    
    if ($user_count > 0) {
        echo "\nRecent users:\n";
        $users = $conn->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 3");
        while ($user = $users->fetch_assoc()) {
            echo "  - {$user['username']} ({$user['email']}) - Joined: {$user['created_at']}\n";
        }
    }
    
    // Check cache
    $cache_count = $conn->query("SELECT COUNT(*) as count FROM tmdb_cache")->fetch_assoc()['count'];
    echo "\nCached API responses: $cache_count\n";
    
    if ($cache_count > 0) {
        echo "Cache items:\n";
        $cache = $conn->query("SELECT cache_key, cache_type, created_at, expires_at FROM tmdb_cache LIMIT 5");
        while ($item = $cache->fetch_assoc()) {
            $expired = strtotime($item['expires_at']) < time() ? ' (EXPIRED)' : ' (VALID)';
            echo "  - {$item['cache_key']} ({$item['cache_type']})$expired\n";
        }
    }
    
    echo "\n=== TEST QUERIES ===\n";
    
    // Test session check
    session_start();
    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
        echo "✓ User session active: {$_SESSION['username']}\n";
    } else {
        echo "○ No active user session\n";
    }
    
    // Test admin session
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        echo "✓ Admin session active\n";
    } else {
        echo "○ No active admin session\n";
    }
    
    echo "\n=== SYSTEM STATUS ===\n";
    echo "✓ Database: OK\n";
    echo "✓ Tables: " . count(array_filter($tables, function($t) use ($conn) {
        return $conn->query("SHOW TABLES LIKE '$t'")->num_rows > 0;
    })) . "/" . count($tables) . " present\n";
    echo "✓ PHP Version: " . phpversion() . "\n";
    echo "✓ MySQL Version: " . $conn->server_info . "\n";
    
    echo "\n=== READY TO TEST ===\n";
    echo "1. Visit: http://localhost/Moviebook/index.html\n";
    echo "2. Click 'Login' to create an account\n";
    echo "3. Test authentication system: http://localhost/Moviebook/test_auth.html\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nMake sure to:\n";
    echo "1. Import database/users.sql\n";
    echo "2. Start MySQL service\n";
    echo "3. Check config.php settings\n";
}
?>
