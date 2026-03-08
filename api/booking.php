<?php
/**
 * MovieBook - Booking API
 * 
 * Handles booking operations: theaters, showtimes, seat selection, payment
 * Actions: get_theaters, get_showtimes, book, get_booking, cancel
 */

header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to book tickets']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    switch ($action) {
        
        case 'get_theaters':
            // Get all active theaters
            $stmt = $pdo->query("
                SELECT t.*, u.name as owner_name 
                FROM theaters t 
                JOIN users u ON t.owner_id = u.id 
                WHERE t.is_active = 1 
                ORDER BY t.name
            ");
            $theaters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'theaters' => $theaters]);
            break;
            
        case 'get_showtimes':
            // Get showtimes for a movie (optionally filtered by date/theater)
            $movieId = intval($_GET['movie_id'] ?? 0);
            $date = $_GET['date'] ?? date('Y-m-d');
            $theaterId = intval($_GET['theater_id'] ?? 0);
            
            if ($movieId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Movie ID required']);
                exit;
            }
            
            $sql = "
                SELECT s.*, t.name as theater_name, t.location, t.city, t.total_seats,
                       m.title as movie_title
                FROM showtimes s
                JOIN theaters t ON s.theater_id = t.id
                JOIN movies m ON s.movie_id = m.id
                WHERE s.movie_id = ? AND s.show_date >= ?
            ";
            $params = [$movieId, $date];
            
            if ($theaterId > 0) {
                $sql .= " AND s.theater_id = ?";
                $params[] = $theaterId;
            }
            
            $sql .= " ORDER BY s.show_date, s.show_time";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $showtimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group by theater
            $groupedByTheater = [];
            foreach ($showtimes as $show) {
                $tid = $show['theater_id'];
                if (!isset($groupedByTheater[$tid])) {
                    $groupedByTheater[$tid] = [
                        'theater_id' => $tid,
                        'theater_name' => $show['theater_name'],
                        'location' => $show['location'],
                        'city' => $show['city'],
                        'total_seats' => $show['total_seats'],
                        'shows' => []
                    ];
                }
                $groupedByTheater[$tid]['shows'][] = [
                    'id' => $show['id'],
                    'date' => $show['show_date'],
                    'time' => $show['show_time'],
                    'price' => $show['price'],
                    'available_seats' => $show['available_seats']
                ];
            }
            
            echo json_encode([
                'success' => true, 
                'showtimes' => array_values($groupedByTheater),
                'date' => $date
            ]);
            break;
            
        case 'get_seats':
            // Get seat availability for a showtime
            $showtimeId = intval($_GET['showtime_id'] ?? 0);
            
            if ($showtimeId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Showtime ID required']);
                exit;
            }
            
            // Get showtime details
            $stmt = $pdo->prepare("
                SELECT s.*, t.total_seats, t.name as theater_name
                FROM showtimes s
                JOIN theaters t ON s.theater_id = t.id
                WHERE s.id = ?
            ");
            $stmt->execute([$showtimeId]);
            $showtime = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$showtime) {
                echo json_encode(['success' => false, 'error' => 'Showtime not found']);
                exit;
            }
            
            // Get booked seats
            $stmt = $pdo->prepare("
                SELECT seats FROM bookings 
                WHERE showtime_id = ? AND status != 'cancelled'
            ");
            $stmt->execute([$showtimeId]);
            $bookedSeats = [];
            while ($row = $stmt->fetch()) {
                $bookedSeats = array_merge($bookedSeats, explode(',', $row['seats']));
            }
            
            echo json_encode([
                'success' => true,
                'showtime' => $showtime,
                'booked_seats' => $bookedSeats,
                'total_seats' => $showtime['total_seats']
            ]);
            break;
            
        case 'book':
            // Create a booking
            $showtimeId = intval($_POST['showtime_id'] ?? 0);
            $seats = trim($_POST['seats'] ?? '');
            
            if ($showtimeId <= 0 || empty($seats)) {
                echo json_encode(['success' => false, 'error' => 'Showtime and seats required']);
                exit;
            }
            
            // Get showtime price
            $stmt = $pdo->prepare("SELECT price, available_seats FROM showtimes WHERE id = ?");
            $stmt->execute([$showtimeId]);
            $showtime = $stmt->fetch();
            
            if (!$showtime) {
                echo json_encode(['success' => false, 'error' => 'Invalid showtime']);
                exit;
            }
            
            $seatArray = explode(',', $seats);
            $seatCount = count($seatArray);
            $totalAmount = $seatCount * $showtime['price'];
            
            // Check if seats available
            if ($showtime['available_seats'] < $seatCount) {
                echo json_encode(['success' => false, 'error' => 'Not enough seats available']);
                exit;
            }
            
            // Generate booking code
            $bookingCode = 'MB' . strtoupper(substr(md5(uniqid()), 0, 8));
            
            // Insert booking
            $stmt = $pdo->prepare("
                INSERT INTO bookings (user_id, showtime_id, seats, total_amount, booking_code, status)
                VALUES (?, ?, ?, ?, ?, 'confirmed')
            ");
            $stmt->execute([$userId, $showtimeId, $seats, $totalAmount, $bookingCode]);
            
            // Update available seats
            $stmt = $pdo->prepare("UPDATE showtimes SET available_seats = available_seats - ? WHERE id = ?");
            $stmt->execute([$seatCount, $showtimeId]);
            
            echo json_encode([
                'success' => true,
                'booking_code' => $bookingCode,
                'seats' => $seatArray,
                'total_amount' => $totalAmount
            ]);
            break;
            
        case 'my_bookings':
            // Get user's bookings
            $stmt = $pdo->prepare("
                SELECT b.*, s.show_date, s.show_time, s.price,
                       t.name as theater_name, t.location,
                       m.title as movie_title, m.poster_url
                FROM bookings b
                JOIN showtimes s ON b.showtime_id = s.id
                JOIN theaters t ON s.theater_id = t.id
                JOIN movies m ON s.movie_id = m.id
                WHERE b.user_id = ?
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$userId]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'bookings' => $bookings]);
            break;
            
        case 'cancel':
            // Cancel a booking
            $bookingId = intval($_POST['booking_id'] ?? 0);
            
            if ($bookingId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Booking ID required']);
                exit;
            }
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
            $stmt->execute([$bookingId, $userId]);
            $booking = $stmt->fetch();
            
            if (!$booking) {
                echo json_encode(['success' => false, 'error' => 'Booking not found']);
                exit;
            }
            
            if ($booking['status'] === 'cancelled') {
                echo json_encode(['success' => false, 'error' => 'Already cancelled']);
                exit;
            }
            
            // Update status
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$bookingId]);
            
            // Restore seats
            $seatCount = count(explode(',', $booking['seats']));
            $stmt = $pdo->prepare("UPDATE showtimes SET available_seats = available_seats + ? WHERE id = ?");
            $stmt->execute([$seatCount, $booking['showtime_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Booking cancelled']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Booking API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
