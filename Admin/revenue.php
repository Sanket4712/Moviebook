<?php
/**
 * Admin Revenue & Earnings
 * Shows real revenue data from database with proper empty states.
 * All values are calculated from actual bookings.
 */
require_once '../includes/admin_check.php';
require_once '../includes/db.php';

// Fetch real revenue stats
$totalRevenue = 0;
$platformFee = 0;
$netEarnings = 0;
$theaterPayouts = 0;
$recentTransactions = [];

// Platform fee percentage
$platformFeeRate = 0.15; // 15%

if ($pdo) {
    try {
        // Check if bookings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'bookings'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            // Total revenue from confirmed bookings
            $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'confirmed'");
            $totalRevenue = (float)($stmt->fetchColumn() ?: 0);
            
            // Calculate splits
            $platformFee = $totalRevenue * $platformFeeRate;
            $netEarnings = $totalRevenue - $platformFee;
            $theaterPayouts = $netEarnings * 0.80; // 80% goes to theaters
            
            // Recent transactions (recent confirmed bookings)
            $stmt = $pdo->query("
                SELECT b.id, b.total_amount, b.created_at, t.name as theater_name
                FROM bookings b
                LEFT JOIN theaters t ON b.theater_id = t.id
                WHERE b.status = 'confirmed'
                ORDER BY b.created_at DESC
                LIMIT 10
            ");
            $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Admin revenue error: " . $e->getMessage());
    }
}

// Format currency
function formatCurrency($amount) {
    if ($amount >= 100000) {
        return '₹' . number_format($amount / 100000, 2) . 'L';
    } elseif ($amount >= 1000) {
        return '₹' . number_format($amount / 1000, 1) . 'K';
    }
    return '₹' . number_format($amount);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue & Earnings - Admin Panel</title>
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
                <a href="bookings.php" class="nav-link">
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
                    <h1>Revenue & Earnings</h1>
                </div>
                <div class="topbar-right">
                    <div class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin&size=40&background=e50914&color=fff" alt="Admin">
                        <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <?php if ($totalRevenue == 0): ?>
                <!-- Empty State -->
                <section class="section-card">
                    <div class="card-body" style="text-align: center; padding: 64px;">
                        <i class="bi bi-cash-stack" style="font-size: 4rem; color: var(--admin-text-muted); margin-bottom: 20px; display: block;"></i>
                        <h2>No Revenue Data Yet</h2>
                        <p style="color: var(--admin-text-muted); max-width: 400px; margin: 16px auto;">
                            Revenue will appear here once users start booking tickets. 
                            All values are calculated from actual confirmed bookings.
                        </p>
                    </div>
                </section>
                <?php else: ?>
                <!-- Stats -->
                <section class="stats-section">
                    <div class="stat-card primary">
                        <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                        <div class="stat-info">
                            <h3>Total Revenue</h3>
                            <p class="stat-number"><?= formatCurrency($totalRevenue) ?></p>
                        </div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-icon"><i class="bi bi-percent"></i></div>
                        <div class="stat-info">
                            <h3>Platform Fee (15%)</h3>
                            <p class="stat-number"><?= formatCurrency($platformFee) ?></p>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                        <div class="stat-info">
                            <h3>Net Earnings</h3>
                            <p class="stat-number"><?= formatCurrency($netEarnings) ?></p>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon"><i class="bi bi-building-check"></i></div>
                        <div class="stat-info">
                            <h3>Theater Payouts</h3>
                            <p class="stat-number"><?= formatCurrency($theaterPayouts) ?></p>
                        </div>
                    </div>
                </section>

                <!-- Revenue Breakdown -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-pie-chart"></i> Revenue Breakdown</h2>
                    </div>
                    <div class="revenue-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; padding: 20px;">
                        <div class="revenue-item" style="background: var(--admin-gray); padding: 20px; border-radius: 12px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                                <i class="bi bi-wallet2" style="color: var(--admin-red);"></i>
                                <span style="color: var(--admin-text-muted);">Gross Revenue</span>
                            </div>
                            <p style="font-size: 24px; font-weight: 600;"><?= formatCurrency($totalRevenue) ?></p>
                        </div>
                        <div class="revenue-item" style="background: var(--admin-gray); padding: 20px; border-radius: 12px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                                <i class="bi bi-percent" style="color: var(--admin-danger);"></i>
                                <span style="color: var(--admin-text-muted);">Platform Fee</span>
                            </div>
                            <p style="font-size: 24px; font-weight: 600; color: var(--admin-danger);">-<?= formatCurrency($platformFee) ?></p>
                        </div>
                        <div class="revenue-item" style="background: var(--admin-gray); padding: 20px; border-radius: 12px;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                                <i class="bi bi-cash-coin" style="color: var(--admin-success);"></i>
                                <span style="color: var(--admin-text-muted);">Net Earnings</span>
                            </div>
                            <p style="font-size: 24px; font-weight: 600; color: var(--admin-success);"><?= formatCurrency($netEarnings) ?></p>
                        </div>
                    </div>
                </section>

                <!-- Recent Transactions -->
                <?php if (!empty($recentTransactions)): ?>
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-credit-card"></i> Recent Transactions</h2>
                    </div>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--admin-border);">
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">ID</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Theater</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Amount</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Platform Fee</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Payout</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $t): 
                                    $amount = $t['total_amount'] ?? 0;
                                    $fee = $amount * $platformFeeRate;
                                    $payout = $amount - $fee;
                                ?>
                                <tr style="border-bottom: 1px solid var(--admin-border);">
                                    <td style="padding: 1rem;">#TX<?= $t['id'] ?></td>
                                    <td style="padding: 1rem;"><?= htmlspecialchars($t['theater_name'] ?? 'Unknown') ?></td>
                                    <td style="padding: 1rem;">₹<?= number_format($amount) ?></td>
                                    <td style="padding: 1rem; color: var(--admin-danger);">-₹<?= number_format($fee) ?></td>
                                    <td style="padding: 1rem; color: var(--admin-success);">₹<?= number_format($payout) ?></td>
                                    <td style="padding: 1rem; color: var(--admin-text-muted);">
                                        <?= date('M j, Y', strtotime($t['created_at'])) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>
                <?php endif; ?>
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
