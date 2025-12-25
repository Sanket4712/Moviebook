<?php
require_once '../config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDBConnection();

if ($method === 'POST') {
    createBooking();
} elseif ($method === 'GET') {
    getBooking();
} else {
    echo json_encode(['error' => 'Invalid request method']);
}

function createBooking() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $showtime_id = $input['showtime_id'] ?? 0;
    $customer_name = $input['customer_name'] ?? '';
    $customer_email = $input['customer_email'] ?? '';
    $customer_phone = $input['customer_phone'] ?? '';
    $selected_seats = $input['selected_seats'] ?? [];
    
    if (!$showtime_id || empty($customer_name) || empty($customer_email) || empty($selected_seats)) {
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get showtime price
        $stmt = $conn->prepare("SELECT price FROM showtimes WHERE id = ?");
        $stmt->bind_param("i", $showtime_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $showtime = $result->fetch_assoc();
        
        if (!$showtime) {
            throw new Exception("Showtime not found");
        }
        
        $total_amount = $showtime['price'] * count($selected_seats);
        
        // Check if seats are available
        $placeholders = str_repeat('?,', count($selected_seats) - 1) . '?';
        $check_query = "SELECT id FROM seats WHERE showtime_id = ? AND CONCAT(seat_row, seat_number) IN ($placeholders) AND is_booked = 0";
        $stmt = $conn->prepare($check_query);
        
        $types = str_repeat('s', count($selected_seats));
        $stmt->bind_param("i" . $types, $showtime_id, ...$selected_seats);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows != count($selected_seats)) {
            throw new Exception("Some seats are already booked");
        }
        
        // Book the seats
        $update_query = "UPDATE seats SET is_booked = 1 WHERE showtime_id = ? AND CONCAT(seat_row, seat_number) IN ($placeholders)";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i" . $types, $showtime_id, ...$selected_seats);
        $stmt->execute();
        
        // Update available seats count
        $update_showtime = "UPDATE showtimes SET available_seats = available_seats - ? WHERE id = ?";
        $stmt = $conn->prepare($update_showtime);
        $seat_count = count($selected_seats);
        $stmt->bind_param("ii", $seat_count, $showtime_id);
        $stmt->execute();
        
        // Create booking
        $booking_reference = 'BK' . strtoupper(substr(md5(uniqid()), 0, 8));
        $seats_json = json_encode($selected_seats);
        
        $insert_booking = "INSERT INTO bookings (showtime_id, customer_name, customer_email, customer_phone, seats_booked, total_amount, booking_reference) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_booking);
        $stmt->bind_param("issssds", $showtime_id, $customer_name, $customer_email, $customer_phone, $seats_json, $total_amount, $booking_reference);
        $stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'booking_reference' => $booking_reference,
            'total_amount' => $total_amount,
            'message' => 'Booking successful'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getBooking() {
    global $conn;
    
    $booking_reference = $_GET['reference'] ?? '';
    
    if (empty($booking_reference)) {
        echo json_encode(['error' => 'Booking reference required']);
        return;
    }
    
    $query = "SELECT b.*, s.show_date, s.show_time, s.screen_number, m.title as movie_title, m.poster_path
              FROM bookings b
              JOIN showtimes s ON b.showtime_id = s.id
              JOIN movies m ON s.movie_id = m.id
              WHERE b.booking_reference = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $booking_reference);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $row['seats_booked'] = json_decode($row['seats_booked']);
        echo json_encode(['success' => true, 'booking' => $row]);
    } else {
        echo json_encode(['error' => 'Booking not found']);
    }
}
?>
