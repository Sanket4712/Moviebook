<?php
/**
 * Theater Dashboard
 * Shows real theater data for the logged-in theater owner.
 */
require_once '../includes/theater_check.php';
require_once '../includes/db.php';

// Get the theater for the current user
$theaterId = $_SESSION['theater_id'] ?? null;
$theaterName = 'Theater Panel';
$todaysSales = 0;
$activeShows = 0;
$todaysBookings = 0;
$todaysSchedule = [];
$weeklyPerformance = [];

if ($pdo && $theaterId) {
    try {
        // Get theater name
        $stmt = $pdo->prepare("SELECT name FROM theaters WHERE id = ?");
        $stmt->execute([$theaterId]);
        $theater = $stmt->fetch();
        $theaterName = $theater['name'] ?? 'Theater Panel';
        
        // Today's sales from confirmed bookings
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count
            FROM bookings 
            WHERE theater_id = ? AND DATE(created_at) = CURDATE() AND status = 'confirmed'
        ");
        $stmt->execute([$theaterId]);
        $salesData = $stmt->fetch();
        $todaysSales = (float)($salesData['total'] ?? 0);
        $todaysBookings = (int)($salesData['count'] ?? 0);
        
        // Active shows count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM showtimes 
            WHERE theater_id = ? AND showtime >= NOW() AND showtime <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$theaterId]);
        $activeShows = (int)$stmt->fetchColumn();
        
        // Today's schedule
        $stmt = $pdo->prepare("
            SELECT s.*, m.title as movie_title, m.runtime, m.genre,
                   sc.name as screen_name
            FROM showtimes s
            JOIN movies m ON s.movie_id = m.id
            LEFT JOIN screens sc ON s.screen_id = sc.id
            WHERE s.theater_id = ? AND DATE(s.showtime) = CURDATE()
            ORDER BY s.showtime
            LIMIT 10
        ");
        $stmt->execute([$theaterId]);
        $todaysSchedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Theater dashboard error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theater Dashboard - MovieBook</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/theater.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="theater-layout">
        <!-- Sidebar -->
        <aside class="theater-sidebar">
            <div class="sidebar-header">
                <h2>Theater Panel</h2>
                <p class="theater-name"><?= htmlspecialchars($theaterName) ?></p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link active">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="schedule.php" class="nav-link">
                    <i class="bi bi-calendar-event"></i> Schedule Movies
                </a>
                <a href="ticket-sell.php" class="nav-link">
                    <i class="bi bi-ticket-perforated"></i> Ticket Sales
                </a>
                <a href="bookings.php" class="nav-link">
                    <i class="bi bi-list-check"></i> See Bookings
                </a>
                <a href="../index.php" class="nav-link">
                    <i class="bi bi-arrow-left"></i> Back to Site
                </a>
                <a href="../auth/logout.php" class="nav-link logout">
                    <i class="bi bi-box-arrow-left"></i> Sign Out
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="theater-main">
            <header class="theater-header">
                <h1>Theater Dashboard</h1>
                <div class="theater-user">
                    <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Manager') ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['user_name'] ?? 'Manager') ?>&size=40&background=e50914&color=fff" alt="Manager">
                </div>
            </header>

            <div class="theater-content">
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <button class="action-btn blue" onclick="location.href='schedule.php'">
                        <i class="bi bi-film"></i>
                        <span>Schedule Movies</span>
                    </button>
                    <button class="action-btn green" onclick="location.href='ticket-sell.php'">
                        <i class="bi bi-cash-coin"></i>
                        <span>Ticket Sales</span>
                    </button>
                    <button class="action-btn orange" onclick="location.href='bookings.php'">
                        <i class="bi bi-eye"></i>
                        <span>See Bookings</span>
                    </button>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="bi bi-ticket-perforated"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Today's Sales</h3>
                            <p class="stat-value">₹<?= number_format($todaysSales) ?></p>
                            <span class="stat-info"><?= $todaysBookings ?> tickets sold</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="bi bi-film"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Active Shows</h3>
                            <p class="stat-value"><?= $activeShows ?></p>
                            <span class="stat-info">Next 24 hours</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3>Today's Bookings</h3>
                            <p class="stat-value"><?= $todaysBookings ?></p>
                            <span class="stat-info">Confirmed</span>
                        </div>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="schedule-section">
                    <h2>Today's Schedule</h2>
                    <?php if (empty($todaysSchedule)): ?>
                    <div class="empty-state" style="text-align: center; padding: 48px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                        <i class="bi bi-calendar-x" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
                        <h3>No Shows Scheduled Today</h3>
                        <p style="color: var(--text-muted);">Schedule movies to see them appear here.</p>
                        <a href="schedule.php" style="display: inline-block; margin-top: 16px; padding: 10px 20px; background: var(--accent); color: white; border-radius: 8px; text-decoration: none;">
                            <i class="bi bi-plus-circle"></i> Schedule a Show
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="schedule-list">
                        <?php foreach ($todaysSchedule as $show): ?>
                        <div class="schedule-item">
                            <div class="schedule-time">
                                <span class="time"><?= date('g:i A', strtotime($show['showtime'])) ?></span>
                                <span class="screen"><?= htmlspecialchars($show['screen_name'] ?? 'Screen 1') ?></span>
                            </div>
                            <div class="schedule-movie">
                                <h4><?= htmlspecialchars($show['movie_title']) ?></h4>
                                <p><?= htmlspecialchars($show['genre'] ?? 'Movie') ?> • <?= $show['runtime'] ?? '?' ?> min</p>
                            </div>
                            <div class="schedule-stats">
                                <span class="seats-sold"><?= $show['booked_seats'] ?? 0 ?>/<?= $show['total_seats'] ?? 100 ?></span>
                                <span class="revenue">₹<?= number_format($show['revenue'] ?? 0) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theater.js"></script>
</body>
</html>
