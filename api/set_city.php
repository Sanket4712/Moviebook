<?php
/**
 * MovieBook - Set User City API
 * 
 * Stores the user's selected city in session.
 * Used by Tickets page to filter movies by showtime availability.
 */

require_once '../includes/auth_check.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Get city from POST request
$city = trim($_POST['city'] ?? '');

if (empty($city)) {
    echo json_encode([
        'success' => false,
        'error' => 'City is required'
    ]);
    exit;
}

// Store city in session
$_SESSION['user_city'] = $city;

// Optionally verify this city has theaters
$theaterCount = 0;
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM theaters WHERE city = ? AND is_active = 1");
        $stmt->execute([$city]);
        $theaterCount = intval($stmt->fetchColumn());
    } catch (PDOException $e) {
        error_log("City verification failed: " . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'city' => $city,
    'has_theaters' => $theaterCount > 0,
    'theater_count' => $theaterCount
]);
