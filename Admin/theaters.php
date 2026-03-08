<?php
/**
 * Admin Theaters Management
 * Shows real theater data from database with proper empty states.
 */
require_once '../includes/admin_check.php';
require_once '../includes/db.php';

// Fetch real theater stats
$totalTheaters = 0;
$pendingTheaters = 0;
$activeTheaters = 0;
$theaters = [];
$pendingRequests = [];

if ($pdo) {
    try {
        // Check if theaters table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'theaters'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            $totalTheaters = (int)($pdo->query("SELECT COUNT(*) FROM theaters")->fetchColumn() ?: 0);
            $activeTheaters = (int)($pdo->query("SELECT COUNT(*) FROM theaters WHERE status = 'active'")->fetchColumn() ?: 0);
            $pendingTheaters = (int)($pdo->query("SELECT COUNT(*) FROM theaters WHERE status = 'pending'")->fetchColumn() ?: 0);
            
            // Fetch pending requests
            $stmt = $pdo->query("
                SELECT t.*, u.name as owner_name, u.email as owner_email
                FROM theaters t
                LEFT JOIN users u ON t.owner_id = u.id
                WHERE t.status = 'pending'
                ORDER BY t.created_at DESC
                LIMIT 10
            ");
            $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch active theaters
            $stmt = $pdo->query("
                SELECT t.*, u.name as owner_name
                FROM theaters t
                LEFT JOIN users u ON t.owner_id = u.id
                WHERE t.status = 'active'
                ORDER BY t.name
                LIMIT 20
            ");
            $theaters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Admin theaters error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theater Management - Admin Panel</title>
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
                <a href="theaters.php" class="nav-link active">
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
                    <h1>Theater Management</h1>
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
                        <div class="stat-icon"><i class="bi bi-building"></i></div>
                        <div class="stat-info">
                            <h3>Total Theaters</h3>
                            <p class="stat-number"><?= number_format($totalTheaters) ?></p>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="stat-info">
                            <h3>Pending</h3>
                            <p class="stat-number"><?= number_format($pendingTheaters) ?></p>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                        <div class="stat-info">
                            <h3>Active</h3>
                            <p class="stat-number"><?= number_format($activeTheaters) ?></p>
                        </div>
                    </div>
                </section>

                <!-- Pending Requests -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-building-add"></i> Pending Requests</h2>
                        <?php if ($pendingTheaters > 0): ?>
                        <span class="badge-danger"><?= $pendingTheaters ?> Pending</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($pendingRequests)): ?>
                    <div class="card-body" style="text-align: center; padding: 48px;">
                        <i class="bi bi-check-circle" style="font-size: 3rem; color: var(--admin-success); margin-bottom: 16px; display: block;"></i>
                        <h3>No Pending Requests</h3>
                        <p style="color: var(--admin-text-muted);">All theater registration requests have been processed.</p>
                    </div>
                    <?php else: ?>
                    <div class="request-list">
                        <?php foreach ($pendingRequests as $req): ?>
                        <div class="request-item">
                            <div class="request-info">
                                <h4><?= htmlspecialchars($req['name']) ?></h4>
                                <p>Owner: <?= htmlspecialchars($req['owner_name'] ?? 'Unknown') ?> | <?= htmlspecialchars($req['owner_email'] ?? '') ?></p>
                                <p style="margin-top: 0.5rem; color: var(--admin-text-muted); font-size: 0.85rem;">
                                    <?= htmlspecialchars($req['city'] ?? '') ?>
                                </p>
                                <span class="request-date">Requested <?= date('M j, Y', strtotime($req['created_at'])) ?></span>
                            </div>
                            <div class="request-actions">
                                <button class="btn-approve" onclick="approveTheater(<?= $req['id'] ?>)">
                                    <i class="bi bi-check-circle"></i> Approve
                                </button>
                                <button class="btn-reject" onclick="rejectTheater(<?= $req['id'] ?>)">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Active Theaters -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-building-check"></i> Active Theaters</h2>
                    </div>
                    
                    <?php if (empty($theaters)): ?>
                    <div class="card-body" style="text-align: center; padding: 48px;">
                        <i class="bi bi-building" style="font-size: 3rem; color: var(--admin-text-muted); margin-bottom: 16px; display: block;"></i>
                        <h3>No Active Theaters</h3>
                        <p style="color: var(--admin-text-muted);">Theaters will appear here once approved.</p>
                    </div>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--admin-border);">
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Theater</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">City</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Owner</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($theaters as $t): ?>
                                <tr style="border-bottom: 1px solid var(--admin-border);">
                                    <td style="padding: 1rem; font-weight: 500;"><?= htmlspecialchars($t['name']) ?></td>
                                    <td style="padding: 1rem; color: var(--admin-text-muted);"><?= htmlspecialchars($t['city'] ?? '-') ?></td>
                                    <td style="padding: 1rem;"><?= htmlspecialchars($t['owner_name'] ?? '-') ?></td>
                                    <td style="padding: 1rem;">
                                        <span style="background: var(--admin-success); padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.85rem;">Active</span>
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
        
        function approveTheater(id) {
            alert('Theater approval API not yet implemented. Theater ID: ' + id);
        }
        
        function rejectTheater(id) {
            alert('Theater rejection API not yet implemented. Theater ID: ' + id);
        }
    </script>
</body>
</html>
