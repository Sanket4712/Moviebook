<?php
/**
 * Admin Bookings Management
 * Shows real booking data from database with proper empty states.
 */
require_once '../includes/admin_check.php';
require_once '../includes/db.php';

// Fetch real booking stats
$totalBookings = 0;
$confirmedBookings = 0;
$pendingBookings = 0;
$cancelledBookings = 0;
$bookings = [];

if ($pdo) {
    try {
        // Check if bookings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'bookings'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            $totalBookings = (int)($pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn() ?: 0);
            $confirmedBookings = (int)($pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn() ?: 0);
            $pendingBookings = (int)($pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn() ?: 0);
            $cancelledBookings = (int)($pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn() ?: 0);
            
            // Fetch recent bookings
            $stmt = $pdo->query("
                SELECT b.*, u.name as user_name, m.title as movie_title, t.name as theater_name
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                LEFT JOIN movies m ON b.movie_id = m.id
                LEFT JOIN theaters t ON b.theater_id = t.id
                ORDER BY b.created_at DESC
                LIMIT 50
            ");
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Admin bookings error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <img src="../logo.png" alt="MOVIEBOOK" class="admin-logo">
                <h2>Admin Panel</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    <span>Users</span>
                </a>
                <a href="theaters.php" class="nav-link">
                    <i class="bi bi-building"></i>
                    <span>Theaters</span>
                </a>
                <a href="movies.php" class="nav-link">
                    <i class="bi bi-film"></i>
                    <span>Movies</span>
                </a>
                <a href="bookings.php" class="nav-link active">
                    <i class="bi bi-ticket-perforated"></i>
                    <span>Bookings</span>
                </a>
                <a href="settings.php" class="nav-link">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="../auth/logout.php" class="nav-link logout">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1>Bookings Management</h1>
                </div>
                <div class="topbar-right">
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&size=40&background=e50914&color=fff" alt="Admin">
                        <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <!-- Stats -->
                <section class="stats-section">
                    <div class="stat-card primary">
                        <div class="stat-icon"><i class="bi bi-ticket-perforated"></i></div>
                        <div class="stat-info">
                            <h3>Total Bookings</h3>
                            <p class="stat-number"><?= number_format($totalBookings) ?></p>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                        <div class="stat-info">
                            <h3>Confirmed</h3>
                            <p class="stat-number"><?= number_format($confirmedBookings) ?></p>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="stat-info">
                            <h3>Pending</h3>
                            <p class="stat-number"><?= number_format($pendingBookings) ?></p>
                        </div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                        <div class="stat-info">
                            <h3>Cancelled</h3>
                            <p class="stat-number"><?= number_format($cancelledBookings) ?></p>
                        </div>
                    </div>
                </section>

                <!-- Bookings Table -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-clock-history"></i> Recent Bookings</h2>
                    </div>
                    
                    <?php if (empty($bookings)): ?>
                    <div class="card-body" style="text-align: center; padding: 48px;">
                        <i class="bi bi-ticket-perforated" style="font-size: 3rem; color: var(--admin-text-muted); margin-bottom: 16px; display: block;"></i>
                        <h3>No Bookings Yet</h3>
                        <p style="color: var(--admin-text-muted);">Bookings will appear here once users start booking tickets.</p>
                    </div>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--admin-border);">
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">ID</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">User</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Movie</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Theater</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Amount</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Status</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                <tr style="border-bottom: 1px solid var(--admin-border);">
                                    <td style="padding: 1rem;">#<?= $b['id'] ?></td>
                                    <td style="padding: 1rem;"><?= htmlspecialchars($b['user_name'] ?? 'Unknown') ?></td>
                                    <td style="padding: 1rem;"><?= htmlspecialchars($b['movie_title'] ?? '-') ?></td>
                                    <td style="padding: 1rem;"><?= htmlspecialchars($b['theater_name'] ?? '-') ?></td>
                                    <td style="padding: 1rem;">₹<?= number_format($b['total_amount'] ?? 0) ?></td>
                                    <td style="padding: 1rem;">
                                        <?php
                                        $status = $b['status'] ?? 'pending';
                                        $statusColor = $status === 'confirmed' ? 'var(--admin-success)' : ($status === 'cancelled' ? 'var(--admin-danger)' : 'var(--admin-warning)');
                                        $textColor = $status === 'pending' ? 'color: #000;' : '';
                                        ?>
                                        <span style="background: <?= $statusColor ?>; padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.85rem; <?= $textColor ?>">
                                            <?= ucfirst($status) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; color: var(--admin-text-muted);">
                                        <?= date('M j, Y', strtotime($b['created_at'])) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.admin-sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>
