<?php
require_once 'config.php';
requireManager();

$error = '';
$success = '';

// Handle Add Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $item_name = sanitize($_POST['item_name']);
    $category_id = $_POST['category_id'];
    $unit = sanitize($_POST['unit']);
    $description = sanitize($_POST['description']);
    
    if (empty($item_name) || empty($category_id) || empty($unit)) {
        $error = 'Item name, category, and unit are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO items (item_name, category_id, unit, description, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("siss", $item_name, $category_id, $unit, $description);
        
        if ($stmt->execute()) {
            $success = 'Item added successfully!';
            logActivity($conn, $_SESSION['user_id'], "Added item: {$item_name}", 'items', $stmt->insert_id);
        } else {
            $error = 'Error adding item.';
        }
        $stmt->close();
    }
}

// Handle Edit Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    $item_id = $_POST['item_id'];
    $item_name = sanitize($_POST['item_name']);
    $category_id = $_POST['category_id'];
    $unit = sanitize($_POST['unit']);
    $description = sanitize($_POST['description']);
    
    $stmt = $conn->prepare("UPDATE items SET item_name = ?, category_id = ?, unit = ?, description = ? WHERE id = ?");
    $stmt->bind_param("sissi", $item_name, $category_id, $unit, $description, $item_id);
    
    if ($stmt->execute()) {
        $success = 'Item updated successfully!';
        logActivity($conn, $_SESSION['user_id'], "Updated item: {$item_name}", 'items', $item_id);
    } else {
        $error = 'Error updating item.';
    }
    $stmt->close();
}

// Handle Toggle Status
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $item_id = $_GET['id'];
    $new_status = $_GET['status'] === 'active' ? 'inactive' : 'active';
    
    $stmt = $conn->prepare("UPDATE items SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $item_id);
    
    if ($stmt->execute()) {
        $action = $new_status === 'active' ? 'Activated' : 'Deactivated';
        $success = "Item {$action} successfully!";
        logActivity($conn, $_SESSION['user_id'], "{$action} item ID: {$item_id}", 'items', $item_id);
    }
    $stmt->close();
    header('Location: items.php');
    exit();
}

// Handle Delete Item
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $item_id = $_GET['id'];
    
    // Check if item has inventory records
    $check = $conn->prepare("SELECT COUNT(*) as count FROM daily_inventory WHERE item_id = ?");
    $check->bind_param("i", $item_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $error = 'Cannot delete item with existing inventory records.';
    } else {
        $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        
        if ($stmt->execute()) {
            $success = 'Item deleted successfully!';
            logActivity($conn, $_SESSION['user_id'], "Deleted item ID: {$item_id}", 'items', $item_id);
        }
        $stmt->close();
    }
    header('Location: items.php');
    exit();
}

// Get filter
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';

// Pagination settings
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total items count
$count_query = "SELECT COUNT(*) as total FROM items i";
if ($filter_category) {
    $count_query .= " WHERE i.category_id = " . intval($filter_category);
}
$count_result = $conn->query($count_query);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Make sure current page is not greater than total pages
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}

// Get all items with pagination
$query = "SELECT i.*, c.category_name FROM items i JOIN categories c ON i.category_id = c.id";
if ($filter_category) {
    $query .= " WHERE i.category_id = " . intval($filter_category);
}
$query .= " ORDER BY i.created_at DESC LIMIT " . $items_per_page . " OFFSET " . $offset;
$items = $conn->query($query);

// Get all categories
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items - Penongs Inventory</title>
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
            max-width: 1400px;
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
            justify-content: space-between;
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
        .form-group select,
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
        .form-group select:focus,
        .form-group textarea:focus {
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
            white-space: nowrap;
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
        
        .badge-category {
            background: #FEF5E7;
            color: #F39C12;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            flex-wrap: wrap;
        }
        
        .pagination a,
        .pagination span,
        .pagination button {
            padding: 10px 12px;
            border: 1px solid #E8E8E8;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: white;
        }
        
        .pagination a:hover,
        .pagination button:hover {
            background: #F4D03F;
            border-color: #F39C12;
            color: white;
            transform: translateY(-2px);
        }
        
        .pagination .active {
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            border-color: #F39C12;
            color: white;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination .disabled:hover {
            background: white;
            border-color: #E8E8E8;
            color: #999;
            transform: none;
        }
        
        .pagination-info {
            text-align: center;
            font-size: 13px;
            color: #7F8C8D;
            margin-bottom: 10px;
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
        
        .filter-bar {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">📋 Manage Items</div>
            <a href="manager_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <span>➕ Add New Item</span>
            </div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Item Name *</label>
                        <input type="text" name="item_name" required placeholder="e.g., Chicken Wings">
                    </div>
                    
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category_id" required>
                            <option value="">Select Category</option>
                            <?php 
                            $categories->data_seek(0);
                            while ($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Unit *</label>
                        <input type="text" name="unit" required placeholder="e.g., kg, pcs, liters">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Item description..."></textarea>
                </div>
                
                <button type="submit" name="add_item" class="btn btn-primary">➕ Add Item</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <span>📋 All Items</span>
                <form method="GET" action="" style="margin: 0;">
                    <select name="category" onchange="this.form.submit()" style="padding: 8px 15px; border-radius: 8px; border: 2px solid #E8E8E8;">
                        <option value="">All Categories</option>
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items->num_rows > 0): ?>
                            <?php while ($item = $items->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                    <td>
                                        <span class="badge badge-category">
                                            <?php echo htmlspecialchars($item['category_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $item['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                    <td>
                                        <button onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)" 
                                                class="btn btn-sm btn-warning">✏️ Edit</button>
                                        <button onclick="confirmToggle(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', '<?php echo $item['status']; ?>')" 
                                                class="btn btn-sm <?php echo $item['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>">
                                            <?php echo $item['status'] === 'active' ? '🚫' : '✅'; ?>
                                        </button>
                                        <button onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>')" 
                                                class="btn btn-sm btn-danger">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #7F8C8D;">No items found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_items > 0): ?>
                <div class="pagination-info">
                    Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> items
                </div>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1<?php echo $filter_category ? '&category=' . $filter_category : ''; ?>">«</a>
                        <a href="?page=<?php echo ($current_page - 1); ?><?php echo $filter_category ? '&category=' . $filter_category : ''; ?>">‹ Previous</a>
                    <?php else: ?>
                        <span class="disabled">«</span>
                        <span class="disabled">‹ Previous</span>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i === $current_page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $filter_category ? '&category=' . $filter_category : ''; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <span>...</span>
                    <?php endif; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo ($current_page + 1); ?><?php echo $filter_category ? '&category=' . $filter_category : ''; ?>">Next ›</a>
                        <a href="?page=<?php echo $total_pages; ?><?php echo $filter_category ? '&category=' . $filter_category : ''; ?>">»</a>
                    <?php else: ?>
                        <span class="disabled">Next ›</span>
                        <span class="disabled">»</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header">✏️ Edit Item</h2>
            <form method="POST" action="">
                <input type="hidden" name="item_id" id="edit_item_id">
                
                <div class="form-group">
                    <label>Item Name *</label>
                    <input type="text" name="item_name" id="edit_item_name" required>
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" id="edit_category_id" required>
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Unit *</label>
                    <input type="text" name="unit" id="edit_unit" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="edit_item" class="btn btn-primary">💾 Save Changes</button>
                    <button type="button" class="btn btn-close" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toggle Status Modal -->
    <div id="toggleModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" id="toggle_header">Change Status</h2>
            <p style="margin-bottom: 20px; color: #555; font-size: 15px;">
                Are you sure you want to <strong id="toggle_action"></strong> <strong id="toggle_item_name"></strong>?
            </p>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-close" onclick="closeToggleModal()">Cancel</button>
                <a id="confirmToggleBtn" href="#" class="btn btn-primary" style="text-decoration: none;">
                    Yes, Proceed
                </a>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" style="color: #E74C3C;">🗑️ Delete Item</h2>
            <p style="margin-bottom: 20px; color: #555; font-size: 15px;">
                Are you sure you want to delete <strong id="delete_item_name"></strong>?
            </p>
            <p style="margin-bottom: 20px; color: #C0392B; font-size: 14px; background: #FADBD8; padding: 12px; border-radius: 8px;">
                ⚠️ <strong>Warning:</strong> This action cannot be undone. The item will be permanently removed from the system.
            </p>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-close" onclick="closeDeleteModal()">Cancel</button>
                <a id="confirmDeleteBtn" href="#" class="btn btn-danger" style="text-decoration: none;">
                    🗑️ Yes, Delete Item
                </a>
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
        function editItem(item) {
            document.getElementById('edit_item_id').value = item.id;
            document.getElementById('edit_item_name').value = item.item_name;
            document.getElementById('edit_category_id').value = item.category_id;
            document.getElementById('edit_unit').value = item.unit;
            document.getElementById('edit_description').value = item.description || '';
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function confirmDelete(itemId, itemName) {
            document.getElementById('delete_item_name').textContent = itemName;
            document.getElementById('confirmDeleteBtn').href = '?delete=1&id=' + itemId;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        function confirmToggle(itemId, itemName, currentStatus) {
            const action = currentStatus === 'active' ? 'deactivate' : 'activate';
            const actionText = currentStatus === 'active' ? 'Deactivate' : 'Activate';
            document.getElementById('toggle_item_name').textContent = itemName;
            document.getElementById('toggle_action').textContent = action;
            document.getElementById('toggle_header').textContent = actionText + ' Item';
            document.getElementById('confirmToggleBtn').href = '?toggle_status=1&id=' + itemId + '&status=' + currentStatus;
            document.getElementById('toggleModal').classList.add('active');
        }
        
        function closeToggleModal() {
            document.getElementById('toggleModal').classList.remove('active');
        }
        
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }
        
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
        
        document.getElementById('toggleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeToggleModal();
            }
        });
        
        <?php if ($success): ?>
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('successModal').classList.add('active');
        });
        <?php endif; ?>
    </script>
</body>
</html>
