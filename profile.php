<?php
require_once 'config.php';
requireManager();

$error = '';
$success = '';

// Add profile_picture column if it doesn't exist
$check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME='profile_picture'");
if ($check_column->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL");
}

// Get manager profile
$user_id = $_SESSION['user_id'];
$user_query = $conn->prepare("SELECT u.*, b.branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_profile = $user_query->get_result()->fetch_assoc();

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = 'uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['profile_picture'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
        // Delete old picture if exists
        if (!empty($user_profile['profile_picture']) && file_exists($user_profile['profile_picture'])) {
            unlink($user_profile['profile_picture']);
        }
        
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $file_name = $user_id . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $file_path, $user_id);
                
                if ($stmt->execute()) {
                    $success = 'Profile picture uploaded successfully!';
                    logActivity($conn, $_SESSION['user_id'], "Updated profile picture", 'users', $user_id);
                    // Refresh user profile
                    $user_query = $conn->prepare("SELECT u.*, b.branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
                    $user_query->bind_param("i", $user_id);
                    $user_query->execute();
                    $user_profile = $user_query->get_result()->fetch_assoc();
                } else {
                    $error = 'Error saving picture information.';
                }
                $stmt->close();
            } else {
                $error = 'Database error: ' . $conn->error;
            }
        } else {
            $error = 'Error uploading file.';
        }
    } else {
        $error = 'Invalid file. Please upload a JPG, PNG, or GIF image (max 5MB).';
    }}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'] ?? '';
    
    if (empty($full_name)) {
        $error = 'Full name is required.';
    } else {
        if (!empty($password)) {
            // Update with password change
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->bind_param("sssi", $full_name, $email, $hashed_password, $user_id);
        } else {
            // Update without password change
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $full_name, $email, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            $success = 'Profile updated successfully!';
            logActivity($conn, $_SESSION['user_id'], "Updated own profile", 'users', $user_id);
            // Refresh user profile
            $user_query = $conn->prepare("SELECT u.*, b.branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.id = ?");
            $user_query->bind_param("i", $user_id);
            $user_query->execute();
            $user_profile = $user_query->get_result()->fetch_assoc();
        } else {
            $error = 'Error updating profile.';
        }
        $stmt->close();
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    logActivity($conn, $_SESSION['user_id'], 'User logged out');
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Penongs Inventory</title>
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid white;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar-default {
            font-size: 20px;
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
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 64px;
            width: 260px;
            height: calc(100vh - 64px);
            background: white;
            border-right: 2px solid #E8E8E8;
            overflow-y: auto;
            z-index: 50;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }
        
        .sidebar-menu li {
            margin: 0;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #FEF5E7;
            border-left-color: #E74C3C;
            color: #E74C3C;
            font-weight: 600;
        }
        
        .menu-icon {
            font-size: 18px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 32px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            font-size: 14px;
            color: #7F8C8D;
        }
        
        .profile-container {
            max-width: 800px;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .profile-picture-section {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #E8E8E8;
        }
        
        .profile-picture-display {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: #F8F9FA;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 3px solid #E8E8E8;
            overflow: hidden;
        }
        
        .profile-picture-display img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-picture-default {
            font-size: 60px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            margin-top: 10px;
        }
        
        .file-input-wrapper input[type="file"] {
            display: none;
        }
        
        .file-input-label {
            padding: 10px 20px;
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .file-input-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 208, 63, 0.3);
        }
        
        .profile-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #E8E8E8;
        }
        
        .profile-section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .profile-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #F8F9FA;
            border-radius: 10px;
        }
        
        .info-label {
            font-size: 12px;
            color: #7F8C8D;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #E74C3C;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-close {
            background: #95A5A6;
            color: white;
            margin-left: 10px;
        }
        
        .btn-close:hover {
            background: #7F8C8D;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-danger {
            background-color: #FADBD8;
            color: #C0392B;
            border-left: 4px solid #E74C3C;
        }
        
        .alert-success {
            background-color: #D5F4E6;
            color: #27AE60;
            border-left: 4px solid #27AE60;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
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
        
        .success-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1001;
            align-items: center;
            justify-content: center;
        }
        
        .success-modal.active { display: flex; }
        
        .success-modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
        }
        
        .success-icon { font-size: 64px; margin-bottom: 20px; }
        .success-title { font-size: 24px; font-weight: bold; color: #27AE60; margin-bottom: 15px; }
        .success-message { font-size: 16px; color: #555; margin-bottom: 30px; }
        .modal-btn { padding: 12px 30px; background: linear-gradient(135deg, #27AE60 0%, #229954 100%); color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; }
        
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .mobile-toggle {
            display: none;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
        }
        
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
            
            .profile-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">
                🍗 Penongs
                <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
            </div>
            <div class="navbar-user">
                <div class="profile-avatar">
                    <?php if (!empty($user_profile['profile_picture']) && file_exists($user_profile['profile_picture'])): ?>
                        <img src="<?php echo $user_profile['profile_picture']; ?>" alt="Profile">
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
            <li><a href="manager_dashboard.php"><span class="menu-icon">📊</span> Dashboard</a></li>
            <li><a href="daily_inventory.php"><span class="menu-icon">📦</span> Daily Inventory</a></li>
            <li><a href="categories.php"><span class="menu-icon">🗂️</span> Categories</a></li>
            <li><a href="items.php"><span class="menu-icon">📋</span> Items</a></li>
            <li><a href="reports.php"><span class="menu-icon">📈</span> Reports</a></li>
            <li><a href="profile.php" class="active"><span class="menu-icon">👤</span> My Profile</a></li>
        </ul>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">👤 My Profile</h1>
            <p class="page-subtitle">Manage your account information</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="profile-container">
            <!-- Profile Picture Section -->
            <div class="profile-picture-section">
                <h2 class="section-title" style="justify-content: center; margin-bottom: 0;">📸 Profile Picture</h2>
                <div class="profile-picture-display">
                    <?php if (!empty($user_profile['profile_picture']) && file_exists($user_profile['profile_picture'])): ?>
                        <img src="<?php echo $user_profile['profile_picture']; ?>" alt="Profile Picture">
                    <?php else: ?>
                        <div class="profile-picture-default">👤</div>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm" style="display: inline;">
                    <div class="file-input-wrapper">
                        <input type="file" name="profile_picture" id="profilePicInput" accept="image/*" onchange="document.getElementById('uploadForm').submit()">
                        <label for="profilePicInput" class="file-input-label">📤 Upload Picture</label>
                    </div>
                </form>
                <p style="font-size: 12px; color: #7F8C8D; margin-top: 10px;">JPG, PNG, or GIF (Max 5MB)</p>
            </div>
            
            <!-- Profile Information Section -->
            <div class="profile-section">
                <h2 class="section-title">📋 Account Information</h2>
                <div class="profile-info">
                    <div class="info-item">
                        <div class="info-label">Username</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_profile['username']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Role</div>
                        <div class="info-value">Manager</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Branch</div>
                        <div class="info-value"><?php echo htmlspecialchars($user_profile['branch_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Account Status</div>
                        <div class="info-value" style="color: <?php echo $user_profile['status'] === 'active' ? '#27AE60' : '#E74C3C'; ?>">
                            <?php echo ucfirst($user_profile['status']); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Profile Section -->
            <div class="profile-section">
                <h2 class="section-title">✏️ Edit Profile</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user_profile['full_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user_profile['email']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>New Password (Leave empty to keep current password)</label>
                        <input type="password" name="password" placeholder="Enter new password or leave blank">
                    </div>
                    
                    <div>
                        <button type="submit" name="update_profile" class="btn btn-primary">💾 Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" style="color: #E74C3C;">🚪 Logout Confirmation</h2>
            <p style="margin-bottom: 20px; color: #555; font-size: 15px;">
                Are you sure you want to logout from your account?
            </p>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-close" onclick="closeLogoutModal()">Cancel</button>
                <a href="?logout=1" class="btn btn-primary" style="text-decoration: none;">Yes, Logout</a>
            </div>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div id="successModal" class="success-modal">
        <div class="success-modal-content">
            <div class="success-icon">✅</div>
            <div class="success-title">Success!</div>
            <div class="success-message"><?php echo $success; ?></div>
            <button onclick="closeSuccessModal()" class="modal-btn">OK, Got it!</button>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        function confirmLogout() {
            document.getElementById('logoutModal').classList.add('active');
        }
        
        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }
        
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }
        
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogoutModal();
            }
        });
        
        <?php if ($success): ?>
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('successModal').classList.add('active');
        });
        <?php endif; ?>
        
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
</body>
</html>
