<?php
require_once 'config.php';
requireAdmin();

$success = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $setting_key = sanitize($_POST['setting_key']);
    $setting_value = sanitize($_POST['setting_value']);
    
    // Check if setting exists
    $check = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
    $check->bind_param("s", $setting_key);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing setting
        $update = $conn->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        $update->bind_param("ss", $setting_value, $setting_key);
        if ($update->execute()) {
            $success = "Setting updated successfully!";
            logActivity($conn, $_SESSION['user_id'], "Updated system setting: $setting_key");
        } else {
            $error = "Failed to update setting.";
        }
        $update->close();
    } else {
        // Insert new setting
        $insert = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $insert->bind_param("ss", $setting_key, $setting_value);
        if ($insert->execute()) {
            $success = "Setting created successfully!";
            logActivity($conn, $_SESSION['user_id'], "Created system setting: $setting_key");
        } else {
            $error = "Failed to create setting.";
        }
        $insert->close();
    }
    $check->close();
}

// Get all settings
$settings_result = $conn->query("SELECT * FROM settings ORDER BY setting_key");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get system stats
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_branches = $conn->query("SELECT COUNT(*) as count FROM branches")->fetch_assoc()['count'];
$total_items = $conn->query("SELECT COUNT(*) as count FROM items")->fetch_assoc()['count'];
$total_logs = $conn->query("SELECT COUNT(*) as count FROM activity_logs")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Penongs Inventory</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #333;
        }
        
        .back-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 208, 63, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #D5F4E6;
            color: #27AE60;
            border-left-color: #27AE60;
        }
        
        .alert-danger {
            background: #FADBD8;
            color: #C0392B;
            border-left-color: #E74C3C;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #7F8C8D;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #F4D03F;
        }
        
        .settings-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .settings-card h2 {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #E8E8E8;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #F4D03F;
            box-shadow: 0 0 0 3px rgba(244, 208, 63, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn-save {
            padding: 12px 30px;
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 208, 63, 0.3);
        }
        
        .settings-list {
            margin-top: 20px;
        }
        
        .setting-item {
            padding: 15px;
            background: #F8F9FA;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #F4D03F;
        }
        
        .setting-item strong {
            color: #333;
            font-size: 14px;
        }
        
        .setting-item p {
            color: #7F8C8D;
            font-size: 13px;
            margin-top: 5px;
            word-break: break-word;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .settings-card {
                padding: 20px;
            }
            
            .stat-card .value {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ System Settings</h1>
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">✗ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- System Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Branches</h3>
                <div class="value"><?php echo $total_branches; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Items</h3>
                <div class="value"><?php echo $total_items; ?></div>
            </div>
            <div class="stat-card">
                <h3>Activity Logs</h3>
                <div class="value"><?php echo $total_logs; ?></div>
            </div>
        </div>
        
        <!-- General Settings -->
        <div class="settings-card">
            <h2>🏢 General Settings</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="setting_key" value="company_name" style="display:none;">
                    <input type="text" id="company_value" name="setting_value" placeholder="Penongs" value="<?php echo htmlspecialchars($settings['company_name'] ?? 'Penongs'); ?>">
                </div>
                <button type="submit" class="btn-save">💾 Save Settings</button>
            </form>
        </div>
        
        <!-- Email Settings -->
        <div class="settings-card">
            <h2>📧 Email Settings</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="admin_email">Admin Email Address</label>
                    <input type="email" name="setting_value" placeholder="admin@penongs.com" value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>">
                    <input type="hidden" name="setting_key" value="admin_email">
                </div>
                <button type="submit" class="btn-save">💾 Save Settings</button>
            </form>
        </div>
        
        <!-- System Settings -->
        <div class="settings-card">
            <h2>🔧 System Configuration</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="items_per_page">Items Per Page</label>
                    <input type="number" name="setting_value" placeholder="20" min="5" max="100" value="<?php echo htmlspecialchars($settings['items_per_page'] ?? '20'); ?>">
                    <input type="hidden" name="setting_key" value="items_per_page">
                </div>
                <button type="submit" class="btn-save">💾 Save Settings</button>
            </form>
        </div>
        
        <!-- Current Settings List -->
        <div class="settings-card">
            <h2>📋 Current System Settings</h2>
            <div class="settings-list">
                <?php foreach ($settings as $key => $value): ?>
                    <div class="setting-item">
                        <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?></strong>
                        <p><?php echo htmlspecialchars($value); ?></p>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($settings)): ?>
                    <p style="color: #7F8C8D; text-align: center; padding: 20px;">No settings configured yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>