<?php
/**
 * HTML to PHP Converter Script
 * 
 * This script converts HTML files to PHP files with:
 * 1. PHP header for auth check
 * 2. All .html links changed to .php
 */

$directories = [
    'User' => '../includes/auth_check.php',
    'Admin' => '../includes/admin_check.php',
    'Theater' => '../includes/theater_check.php'
];

$baseDir = __DIR__;

foreach ($directories as $dir => $authFile) {
    $fullPath = $baseDir . '/' . $dir;
    
    if (!is_dir($fullPath)) {
        echo "Directory not found: $fullPath\n";
        continue;
    }
    
    $files = glob($fullPath . '/*.html');
    
    foreach ($files as $htmlFile) {
        $phpFile = str_replace('.html', '.php', $htmlFile);
        
        // Skip if PHP file already exists
        if (file_exists($phpFile)) {
            echo "Skipping (already exists): $phpFile\n";
            continue;
        }
        
        $content = file_get_contents($htmlFile);
        
        // Replace .html links with .php
        $content = str_replace('.html', '.php', $content);
        
        // Add PHP header
        $phpHeader = "<?php require_once '$authFile'; ?>\n";
        $content = $phpHeader . $content;
        
        // Write the new PHP file
        file_put_contents($phpFile, $content);
        echo "Created: $phpFile\n";
    }
}

echo "\nConversion complete!\n";
