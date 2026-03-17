<?php
require_once 'config.php';
requireAdmin();

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_branch = isset($_GET['branch']) ? $_GET['branch'] : '';

// Get all branches for dropdown
$branches = $conn->query("SELECT * FROM branches WHERE status = 'active' ORDER BY branch_name");

// Get branch name if selected
$branch_name = 'All Branches';
if ($selected_branch) {
    $branch_query = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
    $branch_query->bind_param("i", $selected_branch);
    $branch_query->execute();
    $result = $branch_query->get_result();
    if ($result->num_rows > 0) {
        $branch_name = $result->fetch_assoc()['branch_name'];
    }
}

// Get inventory data
if ($selected_branch) {
    $inventory_query = $conn->prepare("
        SELECT di.*, i.item_name, i.unit, c.category_name, b.branch_name,
               u1.full_name as prepared_by_name,
               u2.full_name as reviewed_by_name
        FROM daily_inventory di
        JOIN items i ON di.item_id = i.id
        JOIN categories c ON i.category_id = c.id
        JOIN branches b ON di.branch_id = b.id
        LEFT JOIN users u1 ON di.prepared_by = u1.id
        LEFT JOIN users u2 ON di.reviewed_by = u2.id
        WHERE di.inventory_date = ? AND di.branch_id = ?
        ORDER BY c.category_name, i.item_name
    ");
    $inventory_query->bind_param("si", $selected_date, $selected_branch);
} else {
    $inventory_query = $conn->prepare("
        SELECT di.*, i.item_name, i.unit, c.category_name, b.branch_name,
               u1.full_name as prepared_by_name,
               u2.full_name as reviewed_by_name
        FROM daily_inventory di
        JOIN items i ON di.item_id = i.id
        JOIN categories c ON i.category_id = c.id
        JOIN branches b ON di.branch_id = b.id
        LEFT JOIN users u1 ON di.prepared_by = u1.id
        LEFT JOIN users u2 ON di.reviewed_by = u2.id
        WHERE di.inventory_date = ?
        ORDER BY b.branch_name, c.category_name, i.item_name
    ");
    $inventory_query->bind_param("s", $selected_date);
}

$inventory_query->execute();
$inventory_items = $inventory_query->get_result();

// Get available dates
$dates_query = $conn->query("
    SELECT DISTINCT inventory_date 
    FROM daily_inventory 
    ORDER BY inventory_date DESC 
    LIMIT 30
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports - Penongs Inventory</title>
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
        
        .container {
            max-width: 1600px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .controls {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters select {
            padding: 10px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 8px;
            font-size: 15px;
            min-width: 200px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
        }
        
        .report-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #F4D03F;
        }
        
        .company-name {
            font-size: 48px;
            font-weight: bold;
            font-style: italic;
            color: #E74C3C;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        
        .company-tagline {
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 3px;
            color: #E74C3C;
            margin-bottom: 15px;
        }
        
        .report-title {
            font-size: 24px;
            color: #333;
            margin: 10px 0;
        }
        
        .report-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
            padding: 15px;
            background: #F8F9FA;
            border-radius: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        th {
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            border: 1px solid #E8B208;
        }
        
        td {
            padding: 10px;
            border: 1px solid #E8E8E8;
            font-size: 14px;
        }
        
        .category-row {
            background: #FEF5E7;
            font-weight: 700;
            color: #F39C12;
        }
        
        .branch-row {
            background: #EBF5FB;
            font-weight: 700;
            color: #3498DB;
        }
        
        .total-row {
            background: #FADBD8;
            font-weight: 700;
            color: #C0392B;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #7F8C8D;
        }
        
        @media print {
            .navbar, .controls { display: none !important; }
            body { background: white; }
            .report-container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">📈 View Reports (Admin)</div>
            <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="controls">
            <div class="filters">
                <select onchange="updateFilters()" id="dateSelect">
                    <option value="<?php echo date('Y-m-d'); ?>">Today</option>
                    <?php while ($date_row = $dates_query->fetch_assoc()): ?>
                        <option value="<?php echo $date_row['inventory_date']; ?>" 
                                <?php echo $selected_date == $date_row['inventory_date'] ? 'selected' : ''; ?>>
                            <?php echo date('F d, Y', strtotime($date_row['inventory_date'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <select onchange="updateFilters()" id="branchSelect">
                    <option value="">All Branches</option>
                    <?php while ($branch = $branches->fetch_assoc()): ?>
                        <option value="<?php echo $branch['id']; ?>" 
                                <?php echo $selected_branch == $branch['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
        </div>
        
        <div class="report-container">
            <div class="report-header">
                <div class="company-name">Penongs</div>
                <div class="report-title">Daily Food Inventory Report</div>
                <div style="color: #7F8C8D; font-size: 14px;">Administrator View - Read Only</div>
            </div>
            
            <div class="report-info">
                <div><strong>Branch:</strong> <?php echo htmlspecialchars($branch_name); ?></div>
                <div><strong>Date:</strong> <?php echo date('F d, Y', strtotime($selected_date)); ?></div>
                <div><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></div>
            </div>
            
            <?php if ($inventory_items->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <?php if (!$selected_branch): ?><th>Branch</th><?php endif; ?>
                            <th>Item Name</th>
                            <th>Unit</th>
                            <th>Beginning</th>
                            <th>Added</th>
                            <th>Total</th>
                            <th>Sales</th>
                            <th>Ending</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_category = '';
                        $current_branch = '';
                        
                        while ($item = $inventory_items->fetch_assoc()): 
                            if (!$selected_branch && $current_branch != $item['branch_name']): 
                                $current_branch = $item['branch_name'];
                        ?>
                                <tr class="branch-row">
                                    <td colspan="9">🏢 <?php echo htmlspecialchars($current_branch); ?></td>
                                </tr>
                        <?php 
                            endif;
                            
                            if ($current_category != $item['category_name']): 
                                $current_category = $item['category_name'];
                        ?>
                                <tr class="category-row">
                                    <td colspan="<?php echo $selected_branch ? '8' : '9'; ?>">
                                        🗂️ <?php echo htmlspecialchars($current_category); ?>
                                    </td>
                                </tr>
                        <?php endif; ?>
                            <tr>
                                <?php if (!$selected_branch): ?>
                                    <td><?php echo htmlspecialchars($item['branch_name']); ?></td>
                                <?php endif; ?>
                                <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td><?php echo number_format($item['beginning_inventory'], 2); ?></td>
                                <td><?php echo number_format($item['added_stock'], 2); ?></td>
                                <td><?php echo number_format($item['total_stock'], 2); ?></td>
                                <td><?php echo number_format($item['daily_sales'], 2); ?></td>
                                <td><?php echo number_format($item['ending_inventory'], 2); ?></td>
                                <td><?php echo htmlspecialchars($item['remarks']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <div style="font-size: 64px; margin-bottom: 20px;">📭</div>
                    <h3>No Inventory Data Found</h3>
                    <p>No inventory records for the selected date and branch</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function updateFilters() {
            const date = document.getElementById('dateSelect').value;
            const branch = document.getElementById('branchSelect').value;
            window.location.href = '?date=' + date + '&branch=' + branch;
        }
    </script>
</body>
</html>
