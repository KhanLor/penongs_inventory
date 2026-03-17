<?php
require_once 'config.php';
requireManager();

$error = '';
$success = '';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Handle Save Inventory
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_inventory'])) {
    $inventory_date = $_POST['inventory_date'];
    $items_data = $_POST['items'];
    
    $conn->begin_transaction();
    
    try {
        foreach ($items_data as $item_id => $data) {
            $beginning_inventory = floatval($data['beginning_inventory']);
            $added_stock = floatval($data['added_stock']);
            $daily_sales = floatval($data['daily_sales']);
            $remarks = sanitize($data['remarks']);
            
            // Calculate totals
            $total_stock = $beginning_inventory + $added_stock;
            $ending_inventory = $total_stock - $daily_sales;
            
            // Check if record exists
            $check = $conn->prepare("SELECT id FROM daily_inventory WHERE inventory_date = ? AND branch_id = ? AND item_id = ?");
            $check->bind_param("sii", $inventory_date, $_SESSION['branch_id'], $item_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $existing = $result->fetch_assoc();
                $stmt = $conn->prepare("
                    UPDATE daily_inventory 
                    SET beginning_inventory = ?, added_stock = ?, total_stock = ?, daily_sales = ?, 
                        ending_inventory = ?, remarks = ?, prepared_by = ? 
                    WHERE id = ?
                ");
                $stmt->bind_param("dddddsii", $beginning_inventory, $added_stock, $total_stock, $daily_sales, 
                                  $ending_inventory, $remarks, $_SESSION['user_id'], $existing['id']);
                $stmt->execute();
                
                logActivity($conn, $_SESSION['user_id'], "Updated inventory for item ID {$item_id} on {$inventory_date}", 
                           'daily_inventory', $existing['id']);
            } else {
                // Insert new record
                $stmt = $conn->prepare("
                    INSERT INTO daily_inventory 
                    (inventory_date, branch_id, item_id, beginning_inventory, added_stock, total_stock, 
                     daily_sales, ending_inventory, remarks, prepared_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
                ");
                $stmt->bind_param("siiiddddsi", $inventory_date, $_SESSION['branch_id'], $item_id, 
                                 $beginning_inventory, $added_stock, $total_stock, $daily_sales, 
                                 $ending_inventory, $remarks, $_SESSION['user_id']);
                $stmt->execute();
                
                logActivity($conn, $_SESSION['user_id'], "Added inventory for item ID {$item_id} on {$inventory_date}", 
                           'daily_inventory', $stmt->insert_id);
            }
        }
        
        $conn->commit();
        $success = 'Inventory saved successfully!';
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Error saving inventory: ' . $e->getMessage();
    }
}

// Get all items with existing inventory data for selected date
$items_query = $conn->prepare("
    SELECT i.*, c.category_name,
           di.id as inventory_id,
           di.beginning_inventory,
           di.added_stock,
           di.total_stock,
           di.daily_sales,
           di.ending_inventory,
           di.remarks,
           di.status
    FROM items i
    JOIN categories c ON i.category_id = c.id
    LEFT JOIN daily_inventory di ON i.id = di.item_id 
        AND di.inventory_date = ? 
        AND di.branch_id = ?
    WHERE i.status = 'active'
    ORDER BY c.category_name, i.item_name
");
$items_query->bind_param("si", $selected_date, $_SESSION['branch_id']);
$items_query->execute();
$items = $items_query->get_result();

// Get yesterday's ending inventory for auto-filling beginning inventory
$yesterday = date('Y-m-d', strtotime($selected_date . ' -1 day'));
$yesterday_inventory = $conn->prepare("
    SELECT item_id, ending_inventory 
    FROM daily_inventory 
    WHERE inventory_date = ? AND branch_id = ?
");
$yesterday_inventory->bind_param("si", $yesterday, $_SESSION['branch_id']);
$yesterday_inventory->execute();
$yesterday_result = $yesterday_inventory->get_result();
$yesterday_data = [];
while ($row = $yesterday_result->fetch_assoc()) {
    $yesterday_data[$row['item_id']] = $row['ending_inventory'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Inventory - Penongs Inventory</title>
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
            max-width: 1600px;
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
            max-width: 1600px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        
        .date-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-selector input {
            padding: 10px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
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
        
        .btn-success {
            background: #27AE60;
            color: white;
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
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #E8E8E8;
            font-size: 13px;
            white-space: nowrap;
        }
        
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #F0F0F0;
            vertical-align: middle;
        }
        
        .category-header {
            background: #FEF5E7 !important;
            font-weight: 700;
            color: #F39C12;
            font-size: 15px;
        }
        
        .input-sm {
            width: 100%;
            padding: 8px;
            border: 2px solid #E8E8E8;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .input-sm:focus {
            outline: none;
            border-color: #E74C3C;
        }
        
        .input-sm.calculated {
            background: #F0F0F0;
            font-weight: 600;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-category {
            background: #FEF5E7;
            color: #F39C12;
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
        
        .info-box {
            background: #EBF5FB;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #3498DB;
        }
        
        .info-box strong {
            color: #2C3E50;
        }
        
        .save-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding: 20px;
            margin-top: 20px;
            background: linear-gradient(135deg, #FEF5E7 0%, #FADBD8 100%);
            border: 2px solid #F39C12;
            border-radius: 12px;
        }
        
        .save-section-text {
            color: #333;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Success Modal */
        .success-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .success-modal.active {
            display: flex;
        }
        
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
        
        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: bounce 0.6s ease;
        }
        
        .success-title {
            font-size: 24px;
            font-weight: bold;
            color: #27AE60;
            margin-bottom: 15px;
        }
        
        .success-message {
            font-size: 16px;
            color: #555;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .modal-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #27AE60 0%, #229954 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                transform: translateY(50px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            60% {
                transform: translateY(-10px);
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }
            
            .card {
                padding: 15px;
            }
            
            th, td {
                padding: 8px 4px;
                font-size: 12px;
            }
            
            .input-sm {
                padding: 6px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">📦 Daily Inventory</div>
            <a href="manager_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Daily Inventory Entry</div>
                <div class="date-selector">
                    <label style="font-weight: 600;">Date:</label>
                    <input type="date" id="inventory_date" value="<?php echo $selected_date; ?>" 
                           onchange="window.location.href='?date=' + this.value">
                </div>
            </div>
            
            <div class="info-box">
                <strong>ℹ️ Instructions:</strong> Beginning inventory will auto-fill from yesterday's ending inventory. 
                Total Stock and Ending Inventory are automatically calculated. Enter Added Stock, Daily Sales, and Remarks as needed.
            </div>
            
            <form method="POST" action="" id="inventoryForm">
                <input type="hidden" name="inventory_date" value="<?php echo $selected_date; ?>">
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 20%;">Item Name</th>
                                <th style="width: 10%;">Unit</th>
                                <th style="width: 10%;">Beginning Inv.</th>
                                <th style="width: 10%;">Added Stock</th>
                                <th style="width: 10%;">Total Stock</th>
                                <th style="width: 10%;">Daily Sales</th>
                                <th style="width: 10%;">Ending Inv.</th>
                                <th style="width: 20%;">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_category = '';
                            if ($items->num_rows > 0):
                                while ($item = $items->fetch_assoc()): 
                                    // Add category header
                                    if ($current_category != $item['category_name']): 
                                        $current_category = $item['category_name'];
                            ?>
                                        <tr>
                                            <td colspan="8" class="category-header">
                                                🗂️ <?php echo htmlspecialchars($current_category); ?>
                                            </td>
                                        </tr>
                            <?php 
                                    endif;
                                    
                                    // Auto-fill beginning inventory from yesterday or existing data
                                    $beginning = $item['beginning_inventory'] ?? ($yesterday_data[$item['id']] ?? 0);
                                    $added = $item['added_stock'] ?? 0;
                                    $sales = $item['daily_sales'] ?? 0;
                                    $total = $beginning + $added;
                                    $ending = $total - $sales;
                            ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td>
                                            <input type="number" step="0.01" min="0" 
                                                   name="items[<?php echo $item['id']; ?>][beginning_inventory]"
                                                   class="input-sm beginning"
                                                   data-item="<?php echo $item['id']; ?>"
                                                   value="<?php echo $beginning; ?>"
                                                   onchange="calculate(<?php echo $item['id']; ?>)">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" 
                                                   name="items[<?php echo $item['id']; ?>][added_stock]"
                                                   class="input-sm added"
                                                   data-item="<?php echo $item['id']; ?>"
                                                   value="<?php echo $added; ?>"
                                                   onchange="calculate(<?php echo $item['id']; ?>)">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" 
                                                   name="items[<?php echo $item['id']; ?>][total_stock]"
                                                   class="input-sm calculated total"
                                                   id="total_<?php echo $item['id']; ?>"
                                                   value="<?php echo $total; ?>"
                                                   readonly>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" 
                                                   name="items[<?php echo $item['id']; ?>][daily_sales]"
                                                   class="input-sm sales"
                                                   data-item="<?php echo $item['id']; ?>"
                                                   value="<?php echo $sales; ?>"
                                                   onchange="calculate(<?php echo $item['id']; ?>)">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" 
                                                   name="items[<?php echo $item['id']; ?>][ending_inventory]"
                                                   class="input-sm calculated ending"
                                                   id="ending_<?php echo $item['id']; ?>"
                                                   value="<?php echo $ending; ?>"
                                                   readonly>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="items[<?php echo $item['id']; ?>][remarks]"
                                                   class="input-sm"
                                                   value="<?php echo htmlspecialchars($item['remarks'] ?? ''); ?>"
                                                   placeholder="Optional remarks">
                                        </td>
                                    </tr>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: #7F8C8D; padding: 40px;">
                                        No active items found. Please add items first.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($items->num_rows > 0): ?>
                <div class="save-section">
                    <div class="save-section-text">
                        💾 Save your inventory data regularly to avoid losing changes
                    </div>
                    <button type="submit" name="save_inventory" class="btn btn-primary">
                        💾 Save Inventory
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script>
        function calculate(itemId) {
            const beginning = parseFloat(document.querySelector(`.beginning[data-item="${itemId}"]`).value) || 0;
            const added = parseFloat(document.querySelector(`.added[data-item="${itemId}"]`).value) || 0;
            const sales = parseFloat(document.querySelector(`.sales[data-item="${itemId}"]`).value) || 0;
            
            const total = beginning + added;
            const ending = total - sales;
            
            document.getElementById(`total_${itemId}`).value = total.toFixed(2);
            document.getElementById(`ending_${itemId}`).value = ending.toFixed(2);
        }
        
        // Confirm before leaving if form has changes
        let formChanged = false;
        document.getElementById('inventoryForm').addEventListener('change', function() {
            formChanged = true;
        });
        
        document.getElementById('inventoryForm').addEventListener('submit', function() {
            formChanged = false;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        // Show success modal if save was successful
        <?php if ($success): ?>
        window.addEventListener('DOMContentLoaded', function() {
            showSuccessModal();
        });
        <?php endif; ?>
        
        function showSuccessModal() {
            document.getElementById('successModal').classList.add('active');
        }
        
        function closeSuccessModal() {
            document.getElementById('successModal').classList.remove('active');
        }
    </script>
    
    <!-- Success Modal -->
    <div id="successModal" class="success-modal">
        <div class="success-modal-content">
            <div class="success-icon">✅</div>
            <div class="success-title">Inventory Saved Successfully!</div>
            <div class="success-message">
                Your daily inventory data has been saved for <strong><?php echo date('F d, Y', strtotime($selected_date)); ?></strong>.
                All changes have been recorded in the system.
            </div>
            <button onclick="closeSuccessModal()" class="modal-btn">OK, Got it!</button>
        </div>
    </div>
</body>
</html>
