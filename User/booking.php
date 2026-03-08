<?php 
require_once '../includes/auth_check.php'; 
require_once '../includes/db.php';

// Get movie from URL parameter
$movieId = intval($_GET['movie_id'] ?? 0);
$movie = null;
$theaters = [];
$showtimesGrouped = [];

if ($pdo && $movieId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$movieId]);
    $movie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($movie) {
        // Fetch theaters with showtimes for this movie
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT s.*, t.id as theater_id, t.name as theater_name, t.location, t.city
            FROM showtimes s
            JOIN theaters t ON s.theater_id = t.id
            WHERE s.movie_id = ? AND s.show_date >= ? AND t.is_active = 1
            ORDER BY t.name, s.show_date, s.show_time
        ");
        $stmt->execute([$movieId, $today]);
        $showtimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by theater
        foreach ($showtimes as $show) {
            $tid = $show['theater_id'];
            if (!isset($showtimesGrouped[$tid])) {
                $showtimesGrouped[$tid] = [
                    'id' => $tid,
                    'name' => $show['theater_name'],
                    'location' => $show['location'],
                    'city' => $show['city'],
                    'shows' => []
                ];
            }
            $showtimesGrouped[$tid]['shows'][] = $show;
        }
    }
}

// Redirect if no movie found - never show fake data
if (!$movie) {
    header("Location: home.php");
    exit;
}

$posterUrl = $movie['poster_url'] ?: '';

$runtime = $movie['runtime'] ? floor($movie['runtime']/60) . 'h ' . ($movie['runtime']%60) . 'm' : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Tickets - <?php echo htmlspecialchars($movie['title']); ?> - MovieBook</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/booking.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <a href="../index.php">
                    <img src="../logo.png" alt="MOVIEBOOK" class="logo-img">
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="films.php">Films</a></li>
                <li><a href="home.php">Tickets</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
            <div class="nav-right">
                <button class="btn-signout" onclick="location.href='../auth/logout.php'">
                    <i class="bi bi-box-arrow-right"></i> Sign Out
                </button>
            </div>
        </div>
    </nav>

    <nav class="bottom-nav">
        <a href="films.php" class="bottom-nav-item"><i class="bi bi-film"></i><span>Films</span></a>
        <a href="home.php" class="bottom-nav-item"><i class="bi bi-ticket-perforated"></i><span>Tickets</span></a>
        <a href="profile.php" class="bottom-nav-item"><i class="bi bi-person-circle"></i><span>Profile</span></a>
    </nav>

    <div class="booking-page">
        <div class="container">
            <!-- Header -->
            <div class="booking-header">
                <button class="btn-back" onclick="history.back()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <h1>Book Tickets</h1>
            </div>

            <!-- Movie Info -->
            <div class="movie-booking-info">
                <img src="<?php echo htmlspecialchars($posterUrl); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                <div class="movie-booking-details">
                    <h2><?php echo htmlspecialchars($movie['title']); ?></h2>
                    <p><i class="fas fa-star"></i> <?php echo number_format($movie['rating'], 1); ?>/10 • <?php echo htmlspecialchars($movie['genre'] ?? 'Drama'); ?> • <?php echo $runtime; ?></p>
                </div>
            </div>

            <!-- Step Progress -->
            <div class="booking-steps">
                <div class="step active" data-step="1">
                    <span class="step-number"><span>1</span></span>
                    <span>Select Show</span>
                </div>
                <div class="step" data-step="2">
                    <span class="step-number"><span>2</span></span>
                    <span>Select Seats</span>
                </div>
                <div class="step" data-step="3">
                    <span class="step-number"><span>3</span></span>
                    <span>Payment</span>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="booking-content">
                <div class="booking-main">
                    <!-- Date Selection -->
                    <section class="date-selection">
                        <h3>Choose Date</h3>
                        <div class="date-cards">
                            <!-- Generated dynamically by JS -->
                        </div>
                    </section>

                    <!-- Theater & Time Selection -->
                    <section class="theater-selection">
                        <h3>Choose Show</h3>
                        
                        <?php if (empty($showtimesGrouped)): ?>
                        <div class="no-shows">
                            <i class="fas fa-calendar-times"></i>
                            <p>No shows available for this movie.</p>
                            <?php if ($movieId == 0): ?>
                            <p><a href="films.php" style="color:#e50914">Browse films to book tickets →</a></p>
                            <?php else: ?>
                            <p>Check back later or try a different date.</p>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        
                        <?php foreach ($showtimesGrouped as $theater): ?>
                        <div class="theater-card">
                            <div class="theater-info">
                                <h4><?php echo htmlspecialchars($theater['name']); ?></h4>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($theater['location']); ?></p>
                            </div>
                            <div class="showtime-slots">
                                <?php 
                                // Group shows by date
                                $todayShows = array_filter($theater['shows'], function($s) {
                                    return $s['show_date'] === date('Y-m-d');
                                });
                                
                                foreach ($todayShows as $show): 
                                    $time = date('g:i A', strtotime($show['show_time']));
                                ?>
                                <button class="time-slot" 
                                        data-showtime-id="<?php echo $show['id']; ?>" 
                                        data-price="<?php echo $show['price']; ?>"
                                        data-seats="<?php echo $show['available_seats']; ?>">
                                    <?php echo $time; ?>
                                    <span class="slot-price">₹<?php echo $show['price']; ?></span>
                                </button>
                                <?php endforeach; ?>
                                
                                <?php if (empty($todayShows)): ?>
                                <span class="no-today-shows">No shows today</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php endif; ?>
                    </section>

                    <!-- Seat Selection (Initially Hidden) -->
                    <section class="seat-selection" style="display: none;">
                        <h3>Choose Seats</h3>
                        
                        <div class="screen-indicator">
                            <div class="screen">SCREEN THIS WAY</div>
                        </div>

                        <div class="seat-map">
                            <!-- Premium Section -->
                            <div class="seat-category">
                                <div class="seat-category-label">
                                    <span>Premium</span>
                                    <span id="premiumPrice">₹350</span>
                                </div>
                                <div class="seat-row">
                                    <span class="row-label">A</span>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat occupied" data-category="premium"></button>
                                    <button class="seat occupied" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat occupied" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                </div>
                                <div class="seat-row">
                                    <span class="row-label">B</span>
                                    <button class="seat occupied" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat occupied" data-category="premium"></button>
                                    <button class="seat occupied" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                    <button class="seat" data-category="premium"></button>
                                </div>
                            </div>

                            <!-- Executive Section -->
                            <div class="seat-category">
                                <div class="seat-category-label">
                                    <span>Executive</span>
                                    <span id="executivePrice">₹280</span>
                                </div>
                                <div class="seat-row">
                                    <span class="row-label">C</span>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat occupied" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat occupied" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                </div>
                                <div class="seat-row">
                                    <span class="row-label">D</span>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat occupied" data-category="executive"></button>
                                    <button class="seat occupied" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat occupied" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                </div>
                                <div class="seat-row">
                                    <span class="row-label">E</span>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat occupied" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat" data-category="executive"></button>
                                    <button class="seat occupied" data-category="executive"></button>
                                </div>
                            </div>

                            <!-- Regular Section -->
                            <div class="seat-category">
                                <div class="seat-category-label">
                                    <span>Regular</span>
                                    <span id="regularPrice">₹180</span>
                                </div>
                                <div class="seat-row">
                                    <span class="row-label">F</span>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat occupied" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat occupied" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                </div>
                                <div class="seat-row">
                                    <span class="row-label">G</span>
                                    <button class="seat occupied" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat occupied" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                </div>
                                <div class="seat-row">
                                    <span class="row-label">H</span>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat occupied" data-category="regular"></button>
                                    <button class="seat occupied" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                    <button class="seat" data-category="regular"></button>
                                </div>
                            </div>
                        </div>

                        <div class="seat-legend">
                            <div class="legend-item">
                                <span class="seat small"></span>
                                <span>Available</span>
                            </div>
                            <div class="legend-item">
                                <span class="seat small selected"></span>
                                <span>Selected</span>
                            </div>
                            <div class="legend-item">
                                <span class="seat small occupied"></span>
                                <span>Sold</span>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Booking Summary -->
                <div class="booking-summary">
                    <h3>Summary</h3>
                    <div class="summary-details">
                        <div class="summary-row">
                            <span>Movie</span>
                        <span><?php echo htmlspecialchars($movie['title']); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Date</span>
                            <span id="selectedDate">-</span>
                        </div>
                        <div class="summary-row">
                            <span>Theater</span>
                            <span id="selectedTheater">-</span>
                        </div>
                        <div class="summary-row">
                            <span>Time</span>
                            <span id="selectedTime">-</span>
                        </div>
                        <div class="summary-row">
                            <span>Seats</span>
                            <span id="selectedSeats">-</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span id="totalAmount">₹0</span>
                        </div>
                    </div>
                    <button class="btn-proceed" onclick="proceedToPayment()" disabled>
                        <i class="fas fa-lock"></i>
                        <span id="proceedText">Select show to continue</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/booking.js"></script>
</body>
</html>
