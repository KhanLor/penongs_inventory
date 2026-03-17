<?php
require_once 'config.php';
requireManager();

$error = '';
$success = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = sanitize($_POST['category_name']);
    $description = sanitize($_POST['description']);
    
    if (empty($category_name)) {
        $error = 'Category name is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $category_name, $description);
        
        if ($stmt->execute()) {
            $success = 'Category added successfully!';
            logActivity($conn, $_SESSION['user_id'], "Added category: {$category_name}", 'categories', $stmt->insert_id);
        } else {
            $error = 'Error adding category.';
        }
        $stmt->close();
    }
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    $category_id = $_POST['category_id'];
    $category_name = sanitize($_POST['category_name']);
    $description = sanitize($_POST['description']);
    
    $stmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $category_name, $description, $category_id);
    
    if ($stmt->execute()) {
        $success = 'Category updated successfully!';
        logActivity($conn, $_SESSION['user_id'], "Updated category: {$category_name}", 'categories', $category_id);
    } else {
        $error = 'Error updating category.';
    }
    $stmt->close();
}

// Handle Delete Category
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $category_id = $_GET['id'];
    
    // Check if category has items
    $check = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE category_id = ?");
    $check->bind_param("i", $category_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $error = 'Cannot delete category with existing items.';
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        
        if ($stmt->execute()) {
            $success = 'Category deleted successfully!';
            logActivity($conn, $_SESSION['user_id'], "Deleted category ID: {$category_id}", 'categories', $category_id);
        }
        $stmt->close();
    }
    header('Location: categories.php');
    exit();
}

// Get all categories with item count
$categories = $conn->query("
    SELECT c.*, COUNT(i.id) as item_count 
    FROM categories c 
    LEFT JOIN items i ON c.id = i.category_id 
    GROUP BY c.id 
    ORDER BY c.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Penongs Inventory</title>
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #E74C3C 0%, #C0392B 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .category-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #F4D03F;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .category-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .category-description {
            color: #7F8C8D;
            font-size: 14px;
            margin-bottom: 15px;
            min-height: 40px;
        }
        
        .category-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #F0F0F0;
        }
        
        .item-count {
            background: #FEF5E7;
            color: #F39C12;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .category-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .btn-warning {
            background: #F39C12;
            color: white;
        }
        
        .btn-danger {
            background: #E74C3C;
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
            
            .categories-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">🗂️ Manage Categories</div>
            <a href="manager_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                ➕ Add New Category
            </div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" required placeholder="e.g., Chicken">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Category description..."></textarea>
                </div>
                
                <button type="submit" name="add_category" class="btn btn-primary">➕ Add Category</button>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                📋 All Categories
            </div>
            
            <div class="categories-grid">
                <?php if ($categories->num_rows > 0): ?>
                    <?php while ($category = $categories->fetch_assoc()): ?>
                        <div class="category-card">
                            <div class="category-name"><?php echo htmlspecialchars($category['category_name']); ?></div>
                            <div class="category-description">
                                <?php echo htmlspecialchars($category['description']) ?: 'No description'; ?>
                            </div>
                            <div class="category-footer">
                                <span class="item-count">📦 <?php echo $category['item_count']; ?> items</span>
                                <div class="category-actions">
                                    <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                            class="btn btn-sm btn-warning">✏️</button>
                                    <?php if ($category['item_count'] == 0): ?>
                                        <button onclick="confirmDelete(<?php echo $category['id']; ?>, '<?php echo addslashes($category['category_name']); ?>')" 
                                                class="btn btn-sm btn-danger">🗑️</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #7F8C8D;">No categories found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header">✏️ Edit Category</h2>
            <form method="POST" action="">
                <input type="hidden" name="category_id" id="edit_category_id">
                
                <div class="form-group">
                    <label>Category Name *</label>
                    <input type="text" name="category_name" id="edit_category_name" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="edit_category" class="btn btn-primary">💾 Save Changes</button>
                    <button type="button" class="btn btn-close" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" style="color: #E74C3C;">🗑️ Delete Category</h2>
            <p style="margin-bottom: 20px; color: #555; font-size: 15px;">
                Are you sure you want to delete <strong id="delete_category_name"></strong>?
            </p>
            <p style="margin-bottom: 20px; color: #C0392B; font-size: 14px; background: #FADBD8; padding: 12px; border-radius: 8px;">
                ⚠️ <strong>Warning:</strong> This action cannot be undone.
            </p>
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn btn-close" onclick="closeDeleteModal()">Cancel</button>
                <a id="confirmDeleteBtn" href="#" class="btn btn-danger" style="text-decoration: none;">
                    🗑️ Yes, Delete Category
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
        function editCategory(category) {
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_category_name').value = category.category_name;
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function confirmDelete(categoryId, categoryName) {
            document.getElementById('delete_category_name').textContent = categoryName;
            document.getElementById('confirmDeleteBtn').href = '?delete=1&id=' + categoryId;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }
        
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
        
        <?php if ($success): ?>
        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('successModal').classList.add('active');
        });
        <?php endif; ?>
    </script>
</body>
</html>
