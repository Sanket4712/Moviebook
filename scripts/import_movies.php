<?php
/**
 * BLOCKED - Import Movies Script
 * 
 * This script has been disabled. Movies can ONLY be added through the Admin panel.
 * AI assistance is allowed for data fetching, but admin must confirm all additions.
 * 
 * INVARIANT: AI assists the admin, AI never owns the database.
 * Every movie exists only because an admin explicitly chose to add it.
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Script Disabled - MovieBook</title>
    <style>
        body { 
            font-family: sans-serif; 
            background: #1a1a1a; 
            color: #fff; 
            padding: 40px; 
            max-width: 600px; 
            margin: 40px auto;
            text-align: center;
        }
        .blocked {
            background: rgba(220, 53, 69, 0.1);
            border: 2px solid #dc3545;
            border-radius: 12px;
            padding: 30px;
        }
        h1 { color: #dc3545; }
        p { color: #888; line-height: 1.6; }
        a { color: #e50914; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="blocked">
        <h1>🚫 Script Disabled</h1>
        <p>
            Automatic movie imports have been <strong>permanently disabled</strong>.<br>
            Movies can <strong>only</strong> be added through the Admin panel.
        </p>
        <p>
            <strong>Invariant:</strong> AI assists the admin, AI never owns the database.<br>
            Every movie exists only because an admin explicitly chose to add it.
        </p>
        <p style="margin-top: 30px;">
            <a href="../Admin/movies.php">Go to Admin Movies Panel →</a>
        </p>
    </div>
</body>
</html>
