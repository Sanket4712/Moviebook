<?php
/**
 * Admin Users Management
 * Shows real users from database with proper empty states.
 */
require_once '../includes/admin_check.php';
require_once '../includes/db.php';

// Fetch real user stats
$totalUsers = 0;
$activeUsers = 0;
$newUsers30d = 0;
$users = [];

if ($pdo) {
    try {
        $totalUsers = (int)($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?: 0);
        
        // Active = logged in within 30 days (if last_login exists, else all are active)
        $activeUsers = $totalUsers; // Simplified for now
        
        // New users in last 30 days
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $newUsers30d = (int)($stmt->fetchColumn() ?: 0);
        
        // Fetch users with pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        $stmt = $pdo->prepare("
            SELECT id, name, email, role, created_at
            FROM users 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$perPage, $offset]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Admin users error: " . $e->getMessage());
    }
}

$totalPages = max(1, ceil($totalUsers / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
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
                <a href="users.php" class="nav-link active">
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
                    <h1>User Management</h1>
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
                        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <p class="stat-number"><?= number_format($totalUsers) ?></p>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-icon"><i class="bi bi-person-check"></i></div>
                        <div class="stat-info">
                            <h3>Active Users</h3>
                            <p class="stat-number"><?= number_format($activeUsers) ?></p>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-icon"><i class="bi bi-person-plus"></i></div>
                        <div class="stat-info">
                            <h3>New (30 days)</h3>
                            <p class="stat-number"><?= number_format($newUsers30d) ?></p>
                        </div>
                    </div>
                </section>

                <!-- Users Table -->
                <section class="section-card">
                    <div class="card-header">
                        <h2><i class="bi bi-people"></i> All Users</h2>
                    </div>
                    
                    <?php if (empty($users)): ?>
                    <div class="card-body" style="text-align: center; padding: 48px;">
                        <i class="bi bi-people" style="font-size: 3rem; color: var(--admin-text-muted); margin-bottom: 16px; display: block;"></i>
                        <h3>No Users Yet</h3>
                        <p style="color: var(--admin-text-muted);">Users will appear here once they register.</p>
                    </div>
                    <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--admin-border);">
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">ID</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Name</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Email</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Role</th>
                                    <th style="padding: 1rem; text-align: left; color: var(--admin-text-muted);">Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr style="border-bottom: 1px solid var(--admin-border);">
                                    <td style="padding: 1rem;">#<?= $u['id'] ?></td>
                                    <td style="padding: 1rem;">
                                        <div style="display: flex; align-items: center; gap: 0.8rem;">
                                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($u['name']) ?>&size=36&background=e50914&color=fff" style="border-radius: 50%; width: 36px; height: 36px;">
                                            <span><?= htmlspecialchars($u['name']) ?></span>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem; color: var(--admin-text-muted);"><?= htmlspecialchars($u['email']) ?></td>
                                    <td style="padding: 1rem;">
                                        <span style="background: <?= $u['role'] === 'admin' ? 'var(--admin-danger)' : ($u['role'] === 'theater' ? 'var(--admin-warning)' : 'var(--admin-success)') ?>; padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.85rem; <?= $u['role'] === 'theater' ? 'color: #000;' : '' ?>">
                                            <?= ucfirst($u['role']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; color: var(--admin-text-muted);">
                                        <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-top: 1px solid var(--admin-border);">
                        <p style="color: var(--admin-text-muted);">
                            Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalUsers) ?> users)
                        </p>
                        <div style="display: flex; gap: 0.5rem;">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" style="padding: 0.5rem 1rem; background: var(--admin-dark); border: 1px solid var(--admin-border); color: var(--admin-text); border-radius: 6px; text-decoration: none;">Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" style="padding: 0.5rem 1rem; background: var(--admin-dark); border: 1px solid var(--admin-border); color: var(--admin-text); border-radius: 6px; text-decoration: none;">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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
