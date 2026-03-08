<?php
/**
 * BLOCKED - Script Disabled
 * This script has been disabled. Admin-only operations
 * must go through the Admin panel.
 */
http_response_code(403);
header('Content-Type: text/html');
echo "<!DOCTYPE html><html><head><title>Disabled</title></head>";
echo "<body style='font-family:sans-serif;background:#0a0a0a;color:#fff;text-align:center;padding:50px'>";
echo "<h1 style='color:#e50914'>Script Disabled</h1>";
echo "<p>Use the <a href='../Admin/movies.php' style='color:#e50914'>Admin Panel</a> instead.</p>";
echo "</body></html>";
