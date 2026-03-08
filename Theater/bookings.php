<?php
/**
 * Theater Bookings
 * Shows real bookings for the logged-in theater.
 */
require_once '../includes/theater_check.php';
require_once '../includes/db.php';

$theaterId = $_SESSION['theater_id'] ?? null;
$theaterName = 'Theater Panel';
$confirmedCount = 0;
$pendingCount = 0;
$cancelledCount = 0;
$bookings = [];

if ($pdo && $theaterId) {
    try {
        // Get theater name
        $stmt = $pdo->prepare("SELECT name FROM theaters WHERE id = ?");
        $stmt->execute([$theaterId]);
        $theater = $stmt->fetch();
        $theaterName = $theater['name'] ?? 'Theater Panel';
        
        // Get booking counts
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE theater_id = ? AND status = 'confirmed'");
        $stmt->execute([$theaterId]);
        $confirmedCount = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE theater_id = ? AND status = 'pending'");
        $stmt->execute([$theaterId]);
        $pendingCount = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE theater_id = ? AND status = 'cancelled'");
        $stmt->execute([$theaterId]);
        $cancelledCount = (int)$stmt->fetchColumn();
        
        // Fetch recent bookings
        $stmt = $pdo->prepare("
            SELECT b.*, u.name as user_name, m.title as movie_title, s.showtime as show_time,
                   sc.name as screen_name
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN movies m ON b.movie_id = m.id
            LEFT JOIN showtimes s ON b.showtime_id = s.id
            LEFT JOIN screens sc ON s.screen_id = sc.id
            WHERE b.theater_id = ?
            ORDER BY b.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$theaterId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Theater bookings error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings - Theater Panel</title>
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
                <a href="ticket-sell.php" class="nav-link">
                    <i class="bi bi-ticket-perforated"></i> Ticket Sales
                </a>
                <a href="bookings.php" class="nav-link active">
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
                <h1>See Bookings</h1>
                <div class="header-actions">
                    <input type="date" value="<?= date('Y-m-d') ?>">
                </div>
            </header>

            <div class="theater-content">
                <!-- Booking Stats -->
                <div class="booking-stats">
                    <div class="booking-stat-card confirmed">
                        <i class="bi bi-check-circle"></i>
                        <div>
                            <h3>Confirmed</h3>
                            <p><?= number_format($confirmedCount) ?></p>
                        </div>
                    </div>
                    <div class="booking-stat-card pending">
                        <i class="bi bi-hourglass-split"></i>
                        <div>
                            <h3>Pending</h3>
                            <p><?= number_format($pendingCount) ?></p>
                        </div>
                    </div>
                    <div class="booking-stat-card cancelled">
                        <i class="bi bi-x-circle"></i>
                        <div>
                            <h3>Cancelled</h3>
                            <p><?= number_format($cancelledCount) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Bookings Grid -->
                <?php if (empty($bookings)): ?>
                <div class="empty-state" style="text-align: center; padding: 64px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                    <i class="bi bi-ticket-perforated" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 20px; display: block;"></i>
                    <h2>No Bookings Yet</h2>
                    <p style="color: var(--text-muted);">Bookings will appear here once users start booking tickets for your shows.</p>
                </div>
                <?php else: ?>
                <div class="bookings-grid">
                    <?php foreach ($bookings as $b): 
                        $status = $b['status'] ?? 'pending';
                        $statusClass = $status === 'confirmed' ? 'confirmed' : ($status === 'cancelled' ? 'cancelled' : 'pending');
                        $statusBadge = $status === 'confirmed' ? 'green' : ($status === 'cancelled' ? 'red' : 'yellow');
                    ?>
                    <div class="booking-card <?= $statusClass ?>">
                        <div class="booking-header">
                            <div>
                                <h4><?= htmlspecialchars($b['movie_title'] ?? 'Movie') ?></h4>
                                <p><?= htmlspecialchars($b['screen_name'] ?? 'Screen') ?> • <?= $b['show_time'] ? date('g:i A', strtotime($b['show_time'])) : '--' ?></p>
                            </div>
                            <span class="status-badge <?= $statusBadge ?>"><?= ucfirst($status) ?></span>
                        </div>
                        <div class="booking-details">
                            <div class="detail-row">
                                <span>Booking ID:</span>
                                <strong>#BK<?= $b['id'] ?></strong>
                            </div>
                            <div class="detail-row">
                                <span>Customer:</span>
                                <strong><?= htmlspecialchars($b['user_name'] ?? 'Guest') ?></strong>
                            </div>
                            <div class="detail-row">
                                <span>Seats:</span>
                                <strong><?= htmlspecialchars($b['seats'] ?? '-') ?></strong>
                            </div>
                            <div class="detail-row">
                                <span>Amount:</span>
                                <strong>₹<?= number_format($b['total_amount'] ?? 0) ?></strong>
                            </div>
                        </div>
                        <div class="booking-actions">
                            <button class="btn-view"><i class="bi bi-eye"></i> View</button>
                            <button class="btn-print"><i class="bi bi-printer"></i> Print</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/theater.js"></script>
</body>
</html>
