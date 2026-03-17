<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'penongs_inventory');

// Application timezone (Philippine Time)
date_default_timezone_set('Asia/Manila');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . (isManager() ? 'manager_dashboard.php' : 'login.php'));
        exit();
    }
}

function requireManager() {
    requireLogin();
    if (!isManager()) {
        header('Location: ' . (isAdmin() ? 'admin_dashboard.php' : 'login.php'));
        exit();
    }
}

function logActivity($conn, $user_id, $action, $table_name = null, $record_id = null, $old_value = null, $new_value = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $user_id, $action, $table_name, $record_id, $old_value, $new_value, $ip_address);
    $stmt->execute();
    $stmt->close();
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function showAlert($message, $type = 'success') {
    return "<div class='alert alert-{$type}'>{$message}</div>";
}

// Auto-migrate: Create settings table if it doesn't exist
function ensureSettingsTable($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($check->num_rows === 0) {
        $sql = "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value LONGTEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
        
        // Insert default settings
        $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_name', 'Penongs')");
        $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('admin_email', 'admin@penongs.com')");
        $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('items_per_page', '20')");
    }
}

// Call the migration on every page load
ensureSettingsTable($conn);
?>
