<?php
require_once 'config.php';
requireManager();

// Add profile_picture column if it doesn't exist
$check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='profile_picture'");
if ($check_column->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
}

// Get manager profile picture
$user_id = $_SESSION['user_id'];
$profile_query = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
$profile_query->bind_param("i", $user_id);
$profile_query->execute();
$profile_result = $profile_query->get_result()->fetch_assoc();
$profile_picture = $profile_result['profile_picture'] ?? '';

// Get branch name
$branch_name = 'N/A';
if ($_SESSION['branch_id']) {
    $branch_query = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
    $branch_query->bind_param("i", $_SESSION['branch_id']);
    $branch_query->execute();
    $branch_result = $branch_query->get_result();
    if ($branch_result->num_rows > 0) {
        $branch_name = $branch_result->fetch_assoc()['branch_name'];
    }
}

// Get today's stats
$today = date('Y-m-d');
$total_items_today = $conn->prepare("SELECT COUNT(*) as count FROM daily_inventory WHERE inventory_date = ? AND branch_id = ?");
$total_items_today->bind_param("si", $today, $_SESSION['branch_id']);
$total_items_today->execute();
$items_count = $total_items_today->get_result()->fetch_assoc()['count'];

// Get total categories
$total_categories = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];

// Get total items in system
$total_items = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'active'")->fetch_assoc()['count'];

// Get low stock items (ending inventory < 10)
$low_stock = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM daily_inventory 
    WHERE branch_id = ? 
    AND inventory_date = ? 
    AND ending_inventory < 10
");
$low_stock->bind_param("is", $_SESSION['branch_id'], $today);
$low_stock->execute();
$low_stock_count = $low_stock->get_result()->fetch_assoc()['count'];

// Get out of stock items for today (ending inventory <= 0)
$out_of_stock_query = $conn->prepare("
    SELECT i.item_name, c.category_name, di.ending_inventory, i.unit
    FROM daily_inventory di
    JOIN items i ON di.item_id = i.id
    JOIN categories c ON i.category_id = c.id
    WHERE di.branch_id = ?
    AND di.inventory_date = ?
    AND di.ending_inventory <= 0
    ORDER BY c.category_name, i.item_name
");
$out_of_stock_query->bind_param("is", $_SESSION['branch_id'], $today);
$out_of_stock_query->execute();
$out_of_stock_items = $out_of_stock_query->get_result();
$out_of_stock_count = $out_of_stock_items->num_rows;

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
    <title>Manager Dashboard - Penongs Inventory</title>
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
            background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
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
            gap: 15px;
        }
        
        .profile-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid white;
            flex-shrink: 0;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar-default {
            font-size: 22px;
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
            background: rgba(255, 255, 255, 0.2);
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
            background: rgba(255, 255, 255, 0.3);
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
            background: linear-gradient(90deg, #FADBD8 0%, transparent 100%);
            border-left: 4px solid #E74C3C;
            color: #C0392B;
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
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .quick-actions h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(244, 208, 63, 0.3);
        }
        
        .action-btn.red {
            background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
        }
        
        .action-btn.red:hover {
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        /* Out Of Stock */
        .stock-alerts {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .stock-alerts h2 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #C0392B;
        }

        .stock-list {
            list-style: none;
            border: 1px solid #FADBD8;
            border-radius: 10px;
            overflow: hidden;
        }

        .stock-list li {
            padding: 12px 15px;
            border-bottom: 1px solid #FDEDEC;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .stock-list li:last-child {
            border-bottom: none;
        }

        .stock-item-name {
            font-weight: 600;
            color: #333;
        }

        .stock-item-meta {
            font-size: 13px;
            color: #7F8C8D;
        }

        .stock-status {
            background: #E74C3C;
            color: white;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .no-stock-alerts {
            color: #27AE60;
            font-weight: 600;
            background: #EAFAF1;
            border: 1px solid #D5F5E3;
            border-radius: 10px;
            padding: 14px;
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
        
        /* Branch Badge */
        .branch-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            display: inline-block;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 12px 15px;
            }
            
            .navbar-brand {
                font-size: 16px;
            }
            
            .navbar-user {
                gap: 10px;
            }
            
            .profile-avatar {
                width: 38px;
                height: 38px;
            }
            
            .profile-avatar-default {
                font-size: 18px;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                display: none;
            }
            
            .profile-avatar {
                width: 38px;
                height: 38px;
            }
            
            .profile-avatar-default {
                font-size: 18px;
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
                <div class="navbar-brand">
                    🍗 Penongs Manager
                    <span class="branch-badge"><?php echo htmlspecialchars($branch_name); ?></span>
                </div>
            </div>
            <div class="navbar-user">
                <div class="profile-avatar">
                    <?php if (!empty($profile_picture) && file_exists($profile_picture)): ?>
                        <img src="<?php echo $profile_picture; ?>" alt="Profile">
                    <?php else: ?>
                        <div class="profile-avatar-default">👤</div>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="user-role">Manager</div>
                </div>
                <button onclick="confirmLogout()" class="btn-logout">Logout</button>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="manager_dashboard.php" class="active"><span class="menu-icon">📊</span> Dashboard</a></li>
            <li><a href="daily_inventory.php"><span class="menu-icon">📦</span> Daily Inventory</a></li>
            <li><a href="inventory_upload.php"><span class="menu-icon">📤</span> Upload Inventory</a></li>
            <li><a href="categories.php"><span class="menu-icon">🗂️</span> Categories</a></li>
            <li><a href="items.php"><span class="menu-icon">📋</span> Items</a></li>
            <li><a href="reports.php"><span class="menu-icon">📈</span> Reports</a></li>
            <li><a href="profile.php"><span class="menu-icon">👤</span> My Profile</a></li>
        </ul>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! | <?php echo date('F d, Y'); ?></p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card yellow">
                <div class="stat-icon">📦</div>
                <div class="stat-value"><?php echo $items_count; ?></div>
                <div class="stat-label">Items Logged Today</div>
            </div>
            
            <div class="stat-card red">
                <div class="stat-icon">🗂️</div>
                <div class="stat-value"><?php echo $total_categories; ?></div>
                <div class="stat-label">Total Categories</div>
            </div>
            
            <div class="stat-card yellow">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            
            <div class="stat-card red">
                <div class="stat-icon">⚠️</div>
                <div class="stat-value"><?php echo $low_stock_count; ?></div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>⚡ Quick Actions</h2>
            <div class="actions-grid">
                <a href="daily_inventory.php" class="action-btn">
                    <span>📦</span> Daily Inventory
                </a>
                <a href="categories.php" class="action-btn red">
                    <span>🗂️</span> Manage Categories
                </a>
                <a href="items.php" class="action-btn">
                    <span>📋</span> Manage Items
                </a>
                <a href="reports.php" class="action-btn red">
                    <span>📈</span> View Reports
                </a>
            </div>
        </div>

        <div class="stock-alerts">
            <h2>🚫 Out of Stock Items Today (<?php echo $out_of_stock_count; ?>)</h2>
            <?php if ($out_of_stock_count > 0): ?>
                <ul class="stock-list">
                    <?php while ($stock_item = $out_of_stock_items->fetch_assoc()): ?>
                        <li>
                            <div>
                                <div class="stock-item-name"><?php echo htmlspecialchars($stock_item['item_name']); ?></div>
                                <div class="stock-item-meta"><?php echo htmlspecialchars($stock_item['category_name']); ?> | Unit: <?php echo htmlspecialchars($stock_item['unit']); ?></div>
                            </div>
                            <span class="stock-status">Ending: <?php echo number_format($stock_item['ending_inventory'], 2); ?></span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <div class="no-stock-alerts">All items currently have stock for today.</div>
            <?php endif; ?>
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
