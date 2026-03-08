<?php
/**
 * Theater Ticket Sales
 * Shows today's showtimes for counter ticket sales with real data.
 */
require_once '../includes/theater_check.php';
require_once '../includes/db.php';

$theaterId = $_SESSION['theater_id'] ?? null;
$theaterName = 'Theater Panel';
$totalSales = 0;
$totalTickets = 0;
$screens = [];
$showtimesByScreen = [];

if ($pdo && $theaterId) {
    try {
        // Get theater name
        $stmt = $pdo->prepare("SELECT name FROM theaters WHERE id = ?");
        $stmt->execute([$theaterId]);
        $theater = $stmt->fetch();
        $theaterName = $theater['name'] ?? 'Theater Panel';
        
        // Get today's sales
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as tickets
            FROM bookings 
            WHERE theater_id = ? AND DATE(created_at) = CURDATE() AND status = 'confirmed'
        ");
        $stmt->execute([$theaterId]);
        $salesData = $stmt->fetch();
        $totalSales = (float)($salesData['total'] ?? 0);
        $totalTickets = (int)($salesData['tickets'] ?? 0);
        
        // Get screens
        $stmt = $pdo->prepare("SELECT id, name, total_seats FROM screens WHERE theater_id = ?");
        $stmt->execute([$theaterId]);
        $screens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get today's showtimes grouped by screen
        $stmt = $pdo->prepare("
            SELECT s.*, m.title as movie_title, m.poster_url, sc.name as screen_name, sc.id as screen_id,
                   COALESCE((SELECT COUNT(*) FROM bookings WHERE showtime_id = s.id AND status = 'confirmed'), 0) as booked_seats,
                   COALESCE((SELECT SUM(total_amount) FROM bookings WHERE showtime_id = s.id AND status = 'confirmed'), 0) as revenue
            FROM showtimes s
            JOIN movies m ON s.movie_id = m.id
            LEFT JOIN screens sc ON s.screen_id = sc.id
            WHERE s.theater_id = ? AND DATE(s.showtime) = CURDATE()
            ORDER BY sc.name, s.showtime
        ");
        $stmt->execute([$theaterId]);
        $showtimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by screen
        foreach ($showtimes as $show) {
            $screenId = $show['screen_id'] ?? 'default';
            $showtimesByScreen[$screenId][] = $show;
        }
        
    } catch (PDOException $e) {
        error_log("Theater ticket-sell error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Sales - Theater Panel</title>
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
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="schedule.php" class="nav-link">
                    <i class="bi bi-calendar-event"></i> Schedule Movies
                </a>
                <a href="ticket-sell.php" class="nav-link active">
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
                <h1>Ticket Sales</h1>
                <div class="date-selector">
                    <input type="date" value="<?= date('Y-m-d') ?>">
                </div>
            </header>

            <div class="theater-content">
                <!-- Sales Summary -->
                <div class="sales-summary">
                    <div class="summary-card total">
                        <h3>Today's Sales</h3>
                        <p class="amount">₹<?= number_format($totalSales) ?></p>
                        <span class="tickets"><?= $totalTickets ?> tickets</span>
                    </div>
                </div>

                <?php if (empty($showtimesByScreen)): ?>
                <!-- Empty State -->
                <div class="empty-state" style="text-align: center; padding: 64px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                    <i class="bi bi-calendar-x" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 20px; display: block;"></i>
                    <h2>No Shows Today</h2>
                    <p style="color: var(--text-muted); max-width: 400px; margin: 16px auto;">
                        No showtimes are scheduled for today. Schedule movies to enable ticket sales.
                    </p>
                    <a href="schedule.php" style="display: inline-block; margin-top: 16px; padding: 10px 20px; background: var(--accent); color: white; border-radius: 8px; text-decoration: none;">
                        <i class="bi bi-calendar-plus"></i> Schedule Shows
                    </a>
                </div>
                <?php else: ?>
                <!-- Ticket Sell Grid -->
                <div class="ticket-sell-grid">
                    <?php foreach ($showtimesByScreen as $screenId => $shows): 
                        $screenName = $shows[0]['screen_name'] ?? 'Screen';
                    ?>
                    <div class="screen-section">
                        <h2><?= htmlspecialchars($screenName) ?></h2>
                        <div class="sell-cards">
                            <?php foreach ($shows as $show): 
                                $totalSeats = $show['total_seats'] ?? 100;
                                $bookedSeats = $show['booked_seats'] ?? 0;
                                $revenue = $show['revenue'] ?? 0;
                                $isFull = $bookedSeats >= $totalSeats;
                                $isLimited = ($bookedSeats / $totalSeats) > 0.9;
                            ?>
                            <div class="sell-card <?= $isFull ? 'housefull' : ($isLimited ? 'limited' : 'available') ?>">
                                <div class="sell-card-header">
                                    <h4><?= date('g:i A', strtotime($show['showtime'])) ?></h4>
                                    <span class="status-indicator <?= $isFull ? 'red' : ($isLimited ? 'yellow' : 'green') ?>"></span>
                                </div>
                                <p class="movie-title"><?= htmlspecialchars($show['movie_title']) ?></p>
                                <div class="sell-stats">
                                    <span><?= $bookedSeats ?>/<?= $totalSeats ?> sold</span>
                                    <span>₹<?= number_format($revenue) ?></span>
                                </div>
                                <button class="btn-sell-ticket" <?= $isFull ? 'disabled' : '' ?>>
                                    <?= $isFull ? 'Housefull' : 'Sell Ticket' ?>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Sell Ticket Modal -->
    <div id="sellTicketModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Sell Ticket</h2>
                <button class="btn-close" onclick="closeSellModal()" type="button">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="ticket-info" id="ticketInfo">
                    <!-- Populated by JS -->
                </div>
                <form id="sellTicketForm">
                    <div class="form-group">
                        <label>Number of Tickets</label>
                        <input type="number" min="1" max="10" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" placeholder="Enter customer name">
                    </div>
                    <div class="form-group">
                        <label>Customer Phone</label>
                        <input type="tel" placeholder="Enter phone number">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <div class="form-actions" style="width: 100%; display: flex; gap: 10px;">
                    <button type="button" class="btn-secondary" onclick="closeSellModal()" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn-primary" style="flex: 1;">Confirm Sale</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theater.js"></script>
</body>
</html>
