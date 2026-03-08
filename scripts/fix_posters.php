<?php
/**
 * BLOCKED - Legacy Script
 * 
 * This script has been disabled. Movie modifications can ONLY 
 * be done through the Admin panel.
 * 
 * INVARIANT: The movies table is ONLY written through api/admin_movies.php
 */

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Script Disabled</title></head><body style='background:#1a1a1a;color:#fff;font-family:sans-serif;text-align:center;padding:40px'>";
echo "<h1 style='color:#dc3545'>🚫 Script Disabled</h1>";
echo "<p>Movie modifications can only be done through the <a href='../Admin/movies.php' style='color:#e50914'>Admin Panel</a>.</p>";
echo "</body></html>";
