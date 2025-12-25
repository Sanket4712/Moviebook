<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../config.php';

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

switch($action) {
    case 'login':
        adminLogin();
        break;
    case 'logout':
        adminLogout();
        break;
    case 'check_auth':
        checkAuth();
        break;
    case 'get_our_picks':
        getOurPicks();
        break;
    case 'add_to_picks':
        if (!isAuthenticated()) return;
        addToPicks();
        break;
    case 'remove_from_picks':
        if (!isAuthenticated()) return;
        removeFromPicks();
        break;
    case 'movies':
        if (!isAuthenticated()) return;
        handleMovies();
        break;
    case 'add_movie':
        if (!isAuthenticated()) return;
        addMovie();
        break;
    case 'update_movie':
        if (!isAuthenticated()) return;
        updateMovie();
        break;
    case 'delete_movie':
        if (!isAuthenticated()) return;
        deleteMovie();
        break;
    case 'showtimes':
        if (!isAuthenticated()) return;
        handleShowtimes();
        break;
    case 'add_showtime':
        if (!isAuthenticated()) return;
        addShowtime();
        break;
    case 'update_showtime':
        if (!isAuthenticated()) return;
        updateShowtime();
        break;
    case 'delete_showtime':
        if (!isAuthenticated()) return;
        deleteShowtime();
        break;
    case 'bookings':
        if (!isAuthenticated()) return;
        getBookings();
        break;
    case 'dashboard_stats':
        if (!isAuthenticated()) return;
        getDashboardStats();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function isAuthenticated() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        echo json_encode(['error' => 'Unauthorized', 'redirect' => 'login.html']);
        return false;
    }
    return true;
}

function adminLogin() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            echo json_encode(['success' => true, 'message' => 'Login successful']);
        } else {
            echo json_encode(['error' => 'Invalid credentials']);
        }
    } else {
        echo json_encode(['error' => 'Invalid credentials']);
    }
}

function adminLogout() {
    session_destroy();
    echo json_encode(['success' => true]);
}

function checkAuth() {
    echo json_encode(['authenticated' => isAuthenticated()]);
}

function handleMovies() {
    global $conn;
    
    $query = "SELECT m.*, COUNT(DISTINCT s.id) as showtime_count FROM movies m LEFT JOIN showtimes s ON m.id = s.movie_id GROUP BY m.id ORDER BY m.created_at DESC";
    $result = $conn->query($query);
    
    $movies = [];
    while($row = $result->fetch_assoc()) {
        $movies[] = $row;
    }
    
    echo json_encode(['success' => true, 'movies' => $movies]);
}

function addMovie() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $tmdb_id = $input['tmdb_id'] ?? 0;
    $title = $input['title'] ?? '';
    $overview = $input['overview'] ?? '';
    $poster_path = $input['poster_path'] ?? '';
    $backdrop_path = $input['backdrop_path'] ?? '';
    $release_date = $input['release_date'] ?? null;
    $runtime = $input['runtime'] ?? 0;
    $rating = $input['rating'] ?? 0;
    $is_showing = $input['is_showing'] ?? 0;
    $show_start_date = $input['show_start_date'] ?? null;
    $show_end_date = $input['show_end_date'] ?? null;
    
    $stmt = $conn->prepare("INSERT INTO movies (tmdb_id, title, overview, poster_path, backdrop_path, release_date, runtime, rating, is_showing, show_start_date, show_end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssissss", $tmdb_id, $title, $overview, $poster_path, $backdrop_path, $release_date, $runtime, $rating, $is_showing, $show_start_date, $show_end_date);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'movie_id' => $conn->insert_id]);
    } else {
        echo json_encode(['error' => 'Failed to add movie: ' . $conn->error]);
    }
}

function updateMovie() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? 0;
    $is_showing = $input['is_showing'] ?? 0;
    $show_start_date = $input['show_start_date'] ?? null;
    $show_end_date = $input['show_end_date'] ?? null;
    
    $stmt = $conn->prepare("UPDATE movies SET is_showing = ?, show_start_date = ?, show_end_date = ? WHERE id = ?");
    $stmt->bind_param("issi", $is_showing, $show_start_date, $show_end_date, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to update movie']);
    }
}

function deleteMovie() {
    global $conn;
    
    $id = $_GET['id'] ?? 0;
    
    $stmt = $conn->prepare("DELETE FROM movies WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to delete movie']);
    }
}

function handleShowtimes() {
    global $conn;
    
    $movie_id = $_GET['movie_id'] ?? null;
    
    if ($movie_id) {
        $stmt = $conn->prepare("SELECT * FROM showtimes WHERE movie_id = ? ORDER BY show_date, show_time");
        $stmt->bind_param("i", $movie_id);
    } else {
        $stmt = $conn->prepare("SELECT s.*, m.title FROM showtimes s JOIN movies m ON s.movie_id = m.id ORDER BY s.show_date DESC, s.show_time");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $showtimes = [];
    while($row = $result->fetch_assoc()) {
        $showtimes[] = $row;
    }
    
    echo json_encode(['success' => true, 'showtimes' => $showtimes]);
}

function addShowtime() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $movie_id = $input['movie_id'] ?? 0;
    $show_date = $input['show_date'] ?? '';
    $show_time = $input['show_time'] ?? '';
    $screen_number = $input['screen_number'] ?? 1;
    $price = $input['price'] ?? 0;
    
    $stmt = $conn->prepare("INSERT INTO showtimes (movie_id, show_date, show_time, screen_number, price) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issid", $movie_id, $show_date, $show_time, $screen_number, $price);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'showtime_id' => $conn->insert_id]);
    } else {
        echo json_encode(['error' => 'Failed to add showtime']);
    }
}

function updateShowtime() {
    global $conn;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? 0;
    $price = $input['price'] ?? 0;
    
    $stmt = $conn->prepare("UPDATE showtimes SET price = ? WHERE id = ?");
    $stmt->bind_param("di", $price, $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to update showtime']);
    }
}

function deleteShowtime() {
    global $conn;
    
    $id = $_GET['id'] ?? 0;
    
    $stmt = $conn->prepare("DELETE FROM showtimes WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to delete showtime']);
    }
}

function getBookings() {
    global $conn;
    
    $query = "SELECT b.*, s.show_date, s.show_time, m.title FROM bookings b JOIN showtimes s ON b.showtime_id = s.id JOIN movies m ON s.movie_id = m.id ORDER BY b.booking_date DESC LIMIT 100";
    $result = $conn->query($query);
    
    $bookings = [];
    while($row = $result->fetch_assoc()) {
        $row['seats_booked'] = json_decode($row['seats_booked']);
        $bookings[] = $row;
    }
    
    echo json_encode(['success' => true, 'bookings' => $bookings]);
}

function getDashboardStats() {
    global $conn;
    
    $stats = [];
    
    // Total movies
    $result = $conn->query("SELECT COUNT(*) as count FROM movies WHERE is_showing = 1");
    $stats['active_movies'] = $result->fetch_assoc()['count'];
    
    // Total bookings today
    $result = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE DATE(booking_date) = CURDATE()");
    $stats['bookings_today'] = $result->fetch_assoc()['count'];
    
    // Total revenue today
    $result = $conn->query("SELECT SUM(total_amount) as total FROM bookings WHERE DATE(booking_date) = CURDATE()");
    $stats['revenue_today'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Total revenue this month
    $result = $conn->query("SELECT SUM(total_amount) as total FROM bookings WHERE MONTH(booking_date) = MONTH(CURDATE()) AND YEAR(booking_date) = YEAR(CURDATE())");
    $stats['revenue_month'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Upcoming showtimes
    $result = $conn->query("SELECT COUNT(*) as count FROM showtimes WHERE show_date >= CURDATE()");
    $stats['upcoming_showtimes'] = $result->fetch_assoc()['count'];
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

// Our Picks Management
function getOurPicks() {
    global $conn;
    
    $query = "SELECT * FROM our_picks ORDER BY display_order ASC, added_at DESC";
    $result = $conn->query($query);
    $picks = [];
    
    while($row = $result->fetch_assoc()) {
        $picks[] = $row;
    }
    
    echo json_encode(['success' => true, 'picks' => $picks]);
}

function addToPicks() {
    global $conn;
    
    $tmdb_id = $_POST['tmdb_id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $poster_path = $_POST['poster_path'] ?? '';
    $backdrop_path = $_POST['backdrop_path'] ?? '';
    $vote_average = $_POST['vote_average'] ?? 0;
    $release_date = $_POST['release_date'] ?? null;
    $overview = $_POST['overview'] ?? '';
    
    // Check if already exists
    $check = $conn->prepare("SELECT id FROM our_picks WHERE tmdb_id = ?");
    $check->bind_param("i", $tmdb_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Movie already in Our Picks']);
        return;
    }
    
    // Get next display order
    $result = $conn->query("SELECT MAX(display_order) as max_order FROM our_picks");
    $max_order = $result->fetch_assoc()['max_order'] ?? 0;
    $display_order = $max_order + 1;
    
    $stmt = $conn->prepare("INSERT INTO our_picks (tmdb_id, title, poster_path, backdrop_path, vote_average, release_date, overview, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssdssi", $tmdb_id, $title, $poster_path, $backdrop_path, $vote_average, $release_date, $overview, $display_order);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Movie added to Our Picks']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding movie']);
    }
}

function removeFromPicks() {
    global $conn;
    
    $id = $_POST['id'] ?? 0;
    
    $stmt = $conn->prepare("DELETE FROM our_picks WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Movie removed from Our Picks']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing movie']);
    }
}
?>
