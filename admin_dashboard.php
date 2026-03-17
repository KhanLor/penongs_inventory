<?php
require_once 'config.php';
requireAdmin();

// Get statistics
$total_managers_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'manager'");
$total_managers = $total_managers_query->fetch_assoc()['count'];

$total_branches_query = $conn->query("SELECT COUNT(*) as count FROM branches WHERE status = 'active'");
$total_branches = $total_branches_query->fetch_assoc()['count'];

$total_items_query = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'active'");
$total_items = $total_items_query->fetch_assoc()['count'];

$total_reports_query = $conn->query("SELECT COUNT(DISTINCT CONCAT(inventory_date, '-', branch_id)) as count FROM daily_inventory");
$total_reports = $total_reports_query->fetch_assoc()['count'];

// Get recent activities
$recent_activities = $conn->query("
    SELECT al.*, u.full_name, u.username 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");

// Handle logout
if (isset($_GET['logout'])) {
    logActivity($conn, $_SESSION['user_id'], 'User logged out');
    session_destroy();
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Penongs Inventory</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #F8F9FA;
            min-height: 100vh;
        }
        
        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .navbar-brand {
            font-size: 24px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 16px;
        }
        
        .user-role {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .btn-logout {
            background: rgba(231, 76, 60, 0.9);
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-logout:hover {
            background: #C0392B;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        .btn-close {
            background: #95A5A6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-confirm {
            background: #E74C3C;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            margin-left: 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 64px;
            width: 260px;
            height: calc(100vh - 64px);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }
        
        .sidebar-menu li {
            margin: 5px 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 25px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            gap: 12px;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: linear-gradient(90deg, #FEF5E7 0%, transparent 100%);
            border-left: 4px solid #F4D03F;
            color: #F39C12;
        }
        
        .menu-icon {
            font-size: 20px;
            width: 24px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
            min-height: calc(100vh - 64px);
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            color: #7F8C8D;
            font-size: 14px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card.yellow {
            border-left: 4px solid #F4D03F;
        }
        
        .stat-card.red {
            border-left: 4px solid #E74C3C;
        }
        
        .stat-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #7F8C8D;
            font-size: 14px;
        }
        
        /* Recent Activity */
        .activity-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .activity-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            padding: 12px 0;
            border-bottom: 1px solid #F0F0F0;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-user {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .activity-action {
            color: #7F8C8D;
            font-size: 13px;
            margin-top: 2px;
        }
        
        .activity-time {
            color: #95A5A6;
            font-size: 12px;
            white-space: nowrap;
        }
        
        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 12px 15px;
            }
            
            .navbar-brand {
                font-size: 18px;
            }
            
            .navbar-user {
                gap: 10px;
            }
            
            .user-info {
                display: none;
            }
            
            .btn-logout {
                padding: 8px 15px;
                font-size: 13px;
            }
            
            .sidebar {
                transform: translateX(-100%);
                z-index: 200;
                top: 60px;
                height: calc(100vh - 60px);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .navbar-content {
                padding: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-content">
            <div style="display: flex; align-items: center; gap: 15px;">
                <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
                <div class="navbar-brand">🍗 Penongs Admin</div>
            </div>
            <div class="navbar-user">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
                <button onclick="confirmLogout()" class="btn-logout">Logout</button>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="active"><span class="menu-icon">📊</span> Dashboard</a></li>
            <li><a href="users.php"><span class="menu-icon">👥</span> Manage Users</a></li>
            <li><a href="branches.php"><span class="menu-icon">🏢</span> Manage Branches</a></li>
            <li><a href="reports_admin.php"><span class="menu-icon">📈</span> View Reports</a></li>
            <li><a href="activity_logs.php"><span class="menu-icon">📋</span> Activity Logs</a></li>
            <li><a href="settings.php"><span class="menu-icon">⚙️</span> System Settings</a></li>
        </ul>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card yellow">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo $total_managers; ?></div>
                <div class="stat-label">Total Managers</div>
            </div>
            
            <div class="stat-card red">
                <div class="stat-icon">🏢</div>
                <div class="stat-value"><?php echo $total_branches; ?></div>
                <div class="stat-label">Active Branches</div>
            </div>
            
            <div class="stat-card yellow">
                <div class="stat-icon">📦</div>
                <div class="stat-value"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            
            <div class="stat-card red">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $total_reports; ?></div>
                <div class="stat-label">Inventory Reports</div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="activity-card">
            <h2 class="activity-header">
                <span>📋</span> Recent Activity
            </h2>
            <ul class="activity-list">
                <?php if ($recent_activities->num_rows > 0): ?>
                    <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                        <li class="activity-item">
                            <div class="activity-info">
                                <div class="activity-user"><?php echo htmlspecialchars($activity['full_name']); ?></div>
                                <div class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></div>
                            </div>
                            <div class="activity-time">
                                <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                            </div>
                        </li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li class="activity-item">
                        <div class="activity-info">
                            <div class="activity-action">No recent activity</div>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </main>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
        
        function confirmLogout() {
            document.getElementById('logoutModal').classList.add('active');
        }
        
        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }
        
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogoutModal();
            }
        });
    </script>
    
    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" style="color: #E74C3C;">🚪 Logout Confirmation</h2>
            <p style="margin-bottom: 20px; color: #555; font-size: 15px;">
                Are you sure you want to logout from your account?
            </p>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-close" onclick="closeLogoutModal()">Cancel</button>
                <a href="?logout=1" class="btn-confirm">Yes, Logout</a>
            </div>
        </div>
    </div>
</body>
</html>
