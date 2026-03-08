<?php
/**
 * Fix diary table schema
 * 
 * Run this script to add missing columns to the diary table.
 */

require_once __DIR__ . '/../includes/db.php';

if (!$pdo) {
    die("Database connection failed\n");
}

echo "Fixing diary table schema...\n\n";

try {
    // Check if watched_date column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM diary LIKE 'watched_date'");
    $hasWatchedDate = $stmt->fetch();
    
    if (!$hasWatchedDate) {
        echo "Adding 'watched_date' column to diary table...\n";
        $pdo->exec("ALTER TABLE diary ADD COLUMN watched_date DATE NOT NULL DEFAULT (CURDATE()) AFTER movie_id");
        echo "✓ Added watched_date column\n";
    } else {
        echo "✓ watched_date column already exists\n";
    }
    
    // Check if rating column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM diary LIKE 'rating'");
    $hasRating = $stmt->fetch();
    
    if (!$hasRating) {
        echo "Adding 'rating' column to diary table...\n";
        $pdo->exec("ALTER TABLE diary ADD COLUMN rating DECIMAL(2,1) DEFAULT NULL COMMENT 'User rating 0.5 to 5.0' AFTER watched_date");
        echo "✓ Added rating column\n";
    } else {
        echo "✓ rating column already exists\n";
    }
    
    // Check if liked column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM diary LIKE 'liked'");
    $hasLiked = $stmt->fetch();
    
    if (!$hasLiked) {
        echo "Adding 'liked' column to diary table...\n";
        $pdo->exec("ALTER TABLE diary ADD COLUMN liked BOOLEAN DEFAULT FALSE AFTER rating");
        echo "✓ Added liked column\n";
    } else {
        echo "✓ liked column already exists\n";
    }
    
    // Check if rewatch column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM diary LIKE 'rewatch'");
    $hasRewatch = $stmt->fetch();
    
    if (!$hasRewatch) {
        echo "Adding 'rewatch' column to diary table...\n";
        $pdo->exec("ALTER TABLE diary ADD COLUMN rewatch BOOLEAN DEFAULT FALSE AFTER liked");
        echo "✓ Added rewatch column\n";
    } else {
        echo "✓ rewatch column already exists\n";
    }
    
    // Check if review column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM diary LIKE 'review'");
    $hasReview = $stmt->fetch();
    
    if (!$hasReview) {
        echo "Adding 'review' column to diary table...\n";
        $pdo->exec("ALTER TABLE diary ADD COLUMN review TEXT AFTER rewatch");
        echo "✓ Added review column\n";
    } else {
        echo "✓ review column already exists\n";
    }
    
    echo "\n✓ Diary table schema fixed successfully!\n";
    
    // Show final table structure
    echo "\nFinal diary table structure:\n";
    $stmt = $pdo->query("DESCRIBE diary");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
