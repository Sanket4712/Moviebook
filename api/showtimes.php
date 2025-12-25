<?php
require_once '../config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$conn = getDBConnection();

switch($action) {
    case 'get_showtimes':
        getShowtimes();
        break;
    case 'get_seats':
        getSeats();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getShowtimes() {
    global $conn;
    $movie_id = $_GET['movie_id'] ?? 0;
    
    if (!$movie_id) {
        echo json_encode(['error' => 'Movie ID required']);
        return;
    }
    
    $query = "SELECT s.*, m.title as movie_title
              FROM showtimes s
              JOIN movies m ON s.movie_id = m.id
              WHERE s.movie_id = ? 
              AND s.show_date >= CURDATE()
              ORDER BY s.show_date, s.show_time";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $showtimes = [];
    while($row = $result->fetch_assoc()) {
        $showtimes[] = $row;
    }
    
    echo json_encode(['success' => true, 'showtimes' => $showtimes]);
}

function getSeats() {
    global $conn;
    $showtime_id = $_GET['showtime_id'] ?? 0;
    
    if (!$showtime_id) {
        echo json_encode(['error' => 'Showtime ID required']);
        return;
    }
    
    // Get or create seats for this showtime
    $check_query = "SELECT COUNT(*) as seat_count FROM seats WHERE showtime_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $showtime_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['seat_count'] == 0) {
        // Create seats (6 rows x 10 seats = 60 seats)
        $rows = ['A', 'B', 'C', 'D', 'E', 'F'];
        $insert_query = "INSERT INTO seats (showtime_id, seat_row, seat_number) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        
        foreach ($rows as $row) {
            for ($i = 1; $i <= 10; $i++) {
                $stmt->bind_param("isi", $showtime_id, $row, $i);
                $stmt->execute();
            }
        }
    }
    
    // Get all seats
    $query = "SELECT * FROM seats WHERE showtime_id = ? ORDER BY seat_row, seat_number";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $showtime_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $seats = [];
    while($row = $result->fetch_assoc()) {
        $seats[] = $row;
    }
    
    echo json_encode(['success' => true, 'seats' => $seats]);
}
?>
