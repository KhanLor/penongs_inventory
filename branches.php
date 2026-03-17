<?php
require_once 'config.php';
requireAdmin();

$error = '';
$success = '';

// Handle Add Branch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_branch'])) {
    $branch_name = sanitize($_POST['branch_name']);
    $branch_code = strtoupper(sanitize($_POST['branch_code']));
    $address = sanitize($_POST['address']);
    $contact_number = sanitize($_POST['contact_number']);
    
    if (empty($branch_name) || empty($branch_code)) {
        $error = 'Branch name and code are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO branches (branch_name, branch_code, address, contact_number, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssss", $branch_name, $branch_code, $address, $contact_number);
        
        if ($stmt->execute()) {
            $success = 'Branch added successfully!';
            logActivity($conn, $_SESSION['user_id'], "Added new branch: {$branch_name}", 'branches', $stmt->insert_id);
        } else {
            $error = 'Error: Branch code already exists.';
        }
        $stmt->close();
    }
}

// Handle Edit Branch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_branch'])) {
    $branch_id = $_POST['branch_id'];
    $branch_name = sanitize($_POST['branch_name']);
    $address = sanitize($_POST['address']);
    $contact_number = sanitize($_POST['contact_number']);
    
    $stmt = $conn->prepare("UPDATE branches SET branch_name = ?, address = ?, contact_number = ? WHERE id = ?");
    $stmt->bind_param("sssi", $branch_name, $address, $contact_number, $branch_id);
    
    if ($stmt->execute()) {
        $success = 'Branch updated successfully!';
        logActivity($conn, $_SESSION['user_id'], "Updated branch: {$branch_name}", 'branches', $branch_id);
    } else {
        $error = 'Error updating branch.';
    }
    $stmt->close();
}

// Handle Toggle Status
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $branch_id = $_GET['id'];
    $new_status = $_GET['status'] === 'active' ? 'inactive' : 'active';
    
    $stmt = $conn->prepare("UPDATE branches SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $branch_id);
    
    if ($stmt->execute()) {
        $action = $new_status === 'active' ? 'Activated' : 'Deactivated';
        $success = "Branch {$action} successfully!";
        logActivity($conn, $_SESSION['user_id'], "{$action} branch ID: {$branch_id}", 'branches', $branch_id);
    }
    $stmt->close();
    header('Location: branches.php');
    exit();
}

// Get all branches
$branches = $conn->query("SELECT * FROM branches ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Branches - Penongs Inventory</title>
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
        }
        
        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .card-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #F4D03F;
            box-shadow: 0 0 0 3px rgba(244, 208, 63, 0.1);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 208, 63, 0.3);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #F8F9FA;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #E8E8E8;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #F0F0F0;
        }
        
        tr:hover {
            background: #FAFAFA;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #D5F4E6;
            color: #27AE60;
        }
        
        .badge-danger {
            background: #FADBD8;
            color: #C0392B;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            margin-right: 5px;
        }
        
        .btn-warning {
            background: #F39C12;
            color: white;
        }
        
        .btn-danger {
            background: #E74C3C;
            color: white;
        }
        
        .btn-success {
            background: #27AE60;
            color: white;
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
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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
            margin-left: 10px;
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
        
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">🏢 Manage Branches</div>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                ➕ Add New Branch
            </div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Branch Name *</label>
                        <input type="text" name="branch_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Branch Code *</label>
                        <input type="text" name="branch_code" required placeholder="e.g., BR001">
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address"></textarea>
                </div>
                
                <button type="submit" name="add_branch" class="btn btn-primary">➕ Add Branch</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                📋 All Branches
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Branch Code</th>
                            <th>Branch Name</th>
                            <th>Address</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($branches->num_rows > 0): ?>
                            <?php while ($branch = $branches->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($branch['branch_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                                    <td><?php echo htmlspecialchars($branch['address']); ?></td>
                                    <td><?php echo htmlspecialchars($branch['contact_number']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $branch['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($branch['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($branch['created_at'])); ?></td>
                                    <td>
                                        <button onclick="editBranch(<?php echo htmlspecialchars(json_encode($branch)); ?>)" class="btn btn-sm btn-warning">✏️ Edit</button>
                                        <button onclick="confirmToggle(<?php echo $branch['id']; ?>, '<?php echo addslashes($branch['branch_name']); ?>', '<?php echo $branch['status']; ?>')" 
                                                class="btn btn-sm <?php echo $branch['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                            <?php echo $branch['status'] === 'active' ? '🚫 Deactivate' : '✅ Activate'; ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #7F8C8D;">No branches found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header">✏️ Edit Branch</h2>
            <form method="POST" action="">
                <input type="hidden" name="branch_id" id="edit_branch_id">
                
                <div class="form-group">
                    <label>Branch Name *</label>
                    <input type="text" name="branch_name" id="edit_branch_name" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" id="edit_contact_number">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_address"></textarea>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="edit_branch" class="btn btn-primary">💾 Save Changes</button>
                    <button type="button" class="btn btn-close" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toggle Status Modal -->
    <div id="toggleModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" id="toggleModalTitle"></h2>
            <p style="margin-bottom: 20px; color: #555; font-size: 15px;" id="toggleModalMessage"></p>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-close" onclick="closeToggleModal()">Cancel</button>
                <a id="confirmToggleBtn" href="#" class="btn" style="text-decoration: none;"></a>
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
        function editBranch(branch) {
            document.getElementById('edit_branch_id').value = branch.id;
            document.getElementById('edit_branch_name').value = branch.branch_name;
            document.getElementById('edit_contact_number').value = branch.contact_number || '';
            document.getElementById('edit_address').value = branch.address || '';
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function confirmToggle(branchId, branchName, currentStatus) {
            const action = currentStatus === 'active' ? 'deactivate' : 'activate';
            const title = currentStatus === 'active' ? '🚫 Deactivate Branch' : '✅ Activate Branch';
            const message = currentStatus === 'active' 
                ? `Are you sure you want to deactivate <strong>${branchName}</strong>?`
                : `Are you sure you want to activate <strong>${branchName}</strong>?`;
            
            document.getElementById('toggleModalTitle').innerHTML = title;
            document.getElementById('toggleModalMessage').innerHTML = message;
            document.getElementById('confirmToggleBtn').href = '?toggle_status=1&id=' + branchId + '&status=' + currentStatus;
            document.getElementById('confirmToggleBtn').textContent = currentStatus === 'active' ? 'Yes, Deactivate' : 'Yes, Activate';
            document.getElementById('confirmToggleBtn').className = 'btn ' + (currentStatus === 'active' ? 'btn-danger' : 'btn-success');
            document.getElementById('toggleModal').classList.add('active');
        }
        
        function closeToggleModal() {
            document.getElementById('toggleModal').classList.remove('active');
        }
        
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }
        
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        document.getElementById('toggleModal').addEventListener('click', function(e) {
            if (e.target === this) closeToggleModal();
        });
        
        <?php if ($success): ?>
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('successModal').classList.add('active');
        });
        <?php endif; ?>
    </script>
</body>
</html>
