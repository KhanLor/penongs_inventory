<?php
require_once 'config.php';
requireManager();

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_branch = $_SESSION['branch_id'];
$low_stock_only = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';

// Get branch name
$branch_query = $conn->prepare("SELECT branch_name FROM branches WHERE id = ?");
$branch_query->bind_param("i", $selected_branch);
$branch_query->execute();
$branch_name = $branch_query->get_result()->fetch_assoc()['branch_name'];

// Get inventory data for selected date, optionally filtered to low stock only
$inventory_sql = "
    SELECT di.*, i.item_name, i.unit, c.category_name,
           u1.full_name as prepared_by_name,
           u2.full_name as reviewed_by_name
    FROM daily_inventory di
    JOIN items i ON di.item_id = i.id
    JOIN categories c ON i.category_id = c.id
    LEFT JOIN users u1 ON di.prepared_by = u1.id
    LEFT JOIN users u2 ON di.reviewed_by = u2.id
    WHERE di.inventory_date = ? AND di.branch_id = ?
";

if ($low_stock_only) {
    $inventory_sql .= " AND di.ending_inventory < 10";
}

$inventory_sql .= " ORDER BY c.category_name, i.item_name";

$inventory_query = $conn->prepare($inventory_sql);
$inventory_query->bind_param("si", $selected_date, $selected_branch);
$inventory_query->execute();
$inventory_items = $inventory_query->get_result();

// Get available report dates
$dates_query = $conn->prepare("
    SELECT DISTINCT inventory_date 
    FROM daily_inventory 
    WHERE branch_id = ? 
    ORDER BY inventory_date DESC 
    LIMIT 30
");
$dates_query->bind_param("i", $selected_branch);
$dates_query->execute();
$available_dates = $dates_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Penongs Inventory</title>
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
        
        .date-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-selector select,
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
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
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
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-body {
            margin-bottom: 25px;
            color: #555;
            font-size: 15px;
            line-height: 1.6;
        }
        
        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-close {
            background: #95A5A6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-close:hover {
            background: #7F8C8D;
        }
        
        .btn-confirm {
            background: #27AE60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-confirm:hover {
            background: #229954;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
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
            margin-bottom: 15px;
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
        
        .info-item {
            display: flex;
            gap: 8px;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: #333;
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
        
        .total-row {
            background: #FADBD8;
            font-weight: 700;
            color: #C0392B;
        }
        
        .signatures {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            margin-top: 50px;
        }
        
        .signature-box {
            text-align: center;
        }
        
        .signature-line {
            border-top: 2px solid #333;
            margin: 60px 20px 10px 20px;
        }
        
        .signature-label {
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }
        
        .signature-name {
            color: #333;
            margin-top: 5px;
        }
        
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #7F8C8D;
        }
        
        .no-data-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        @media print {
            body {
                background: white;
            }
            
            .navbar,
            .controls,
            .btn {
                display: none !important;
            }
            
            .container {
                margin: 0;
                padding: 0;
            }
            
            .report-container {
                box-shadow: none;
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .report-container {
                padding: 20px;
            }
            
            .company-name {
                font-size: 24px;
            }
            
            .report-title {
                font-size: 18px;
            }
            
            .report-info {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 6px 4px;
            }
            
            .signatures {
                grid-template-columns: 1fr;
                gap: 30px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">📈 Daily Inventory Reports</div>
            <a href="manager_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="controls">
            <div class="date-selector">
                <label style="font-weight: 600;">Select Date:</label>
                <select onchange="window.location.href='?date=' + this.value + '<?php echo $low_stock_only ? '&low_stock=1' : ''; ?>'" style="min-width: 200px;">
                    <option value="<?php echo date('Y-m-d'); ?>">Today</option>
                    <?php while ($date_row = $available_dates->fetch_assoc()): ?>
                        <option value="<?php echo $date_row['inventory_date']; ?>" 
                                <?php echo $selected_date == $date_row['inventory_date'] ? 'selected' : ''; ?>>
                            <?php echo date('F d, Y', strtotime($date_row['inventory_date'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
                <button onclick="confirmExportExcel()" class="btn btn-success">📊 Export to Excel</button>
            </div>
        </div>

        <?php if ($low_stock_only): ?>
            <div style="margin-bottom: 15px; color: #C0392B; font-weight: 600;">
                ⚠️ Showing low stock products only (ending inventory below 10).
                <a href="reports.php?date=<?php echo htmlspecialchars($selected_date); ?>" style="margin-left: 10px; color: #E74C3C;">Show All</a>
            </div>
        <?php endif; ?>
        
        <div class="report-container" id="reportContent">
            <div class="report-header">
                <div class="company-name">Penongs</div>
                <div class="report-title">Daily Food Inventory Report</div>
            </div>
            
            <div class="report-info">
                <div class="info-item">
                    <span class="info-label">Branch:</span>
                    <span class="info-value"><?php echo htmlspecialchars($branch_name); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date:</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($selected_date)); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Report Generated:</span>
                    <span class="info-value"><?php echo date('F d, Y h:i A'); ?></span>
                </div>
            </div>
            
            <?php if ($inventory_items->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
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
                        $total_beginning = 0;
                        $total_added = 0;
                        $total_stock = 0;
                        $total_sales = 0;
                        $total_ending = 0;
                        
                        while ($item = $inventory_items->fetch_assoc()): 
                            if ($current_category != $item['category_name']): 
                                $current_category = $item['category_name'];
                        ?>
                                <tr class="category-row">
                                    <td colspan="8">🗂️ <?php echo htmlspecialchars($current_category); ?></td>
                                </tr>
                        <?php 
                            endif;
                            
                            $total_beginning += $item['beginning_inventory'];
                            $total_added += $item['added_stock'];
                            $total_stock += $item['total_stock'];
                            $total_sales += $item['daily_sales'];
                            $total_ending += $item['ending_inventory'];
                        ?>
                            <tr>
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
                        
                        <tr class="total-row">
                            <td colspan="2"><strong>GRAND TOTAL</strong></td>
                            <td><strong><?php echo number_format($total_beginning, 2); ?></strong></td>
                            <td><strong><?php echo number_format($total_added, 2); ?></strong></td>
                            <td><strong><?php echo number_format($total_stock, 2); ?></strong></td>
                            <td><strong><?php echo number_format($total_sales, 2); ?></strong></td>
                            <td><strong><?php echo number_format($total_ending, 2); ?></strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="signatures">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-label">Prepared by:</div>
                        <div class="signature-name">
                            <?php 
                            $inventory_items->data_seek(0);
                            $first_item = $inventory_items->fetch_assoc();
                            echo htmlspecialchars($first_item['prepared_by_name'] ?? $_SESSION['full_name']); 
                            ?>
                        </div>
                        <div style="color: #7F8C8D; font-size: 13px; margin-top: 5px;">Manager</div>
                    </div>
                    
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-label">Reviewed by:</div>
                        <div class="signature-name">
                            <?php echo htmlspecialchars($first_item['reviewed_by_name'] ?? '___________________'); ?>
                        </div>
                        <div style="color: #7F8C8D; font-size: 13px; margin-top: 5px;">Checked by:</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <div class="no-data-icon">📭</div>
                    <h3>No Inventory Data Found</h3>
                    <p>No inventory records for <?php echo date('F d, Y', strtotime($selected_date)); ?></p>
                    <p style="margin-top: 10px;">
                        <a href="daily_inventory.php?date=<?php echo $selected_date; ?>" class="btn btn-primary" style="margin-top: 20px;">
                            ➕ Create Inventory for this Date
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Export Excel Confirmation Modal -->
    <div id="exportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span style="font-size: 28px;">📊</span>
                Export to Excel
            </div>
            <div class="modal-body">
                <p>This will download the current inventory report as an Excel (.xlsx) file.</p>
                <p style="margin-top: 10px;"><strong>Date:</strong> <?php echo date('F d, Y', strtotime($selected_date)); ?></p>
                <p><strong>Branch:</strong> <?php echo htmlspecialchars($branch_name); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-close" onclick="closeExportModal()">Cancel</button>
                <button type="button" class="btn-confirm" onclick="proceedExport()">✓ Export Now</button>
            </div>
        </div>
    </div>
    
    <script>
        function confirmExportExcel() {
            const table = document.querySelector('table');
            if (!table) {
                alert('No data to export');
                return;
            }
            document.getElementById('exportModal').classList.add('active');
        }
        
        function closeExportModal() {
            document.getElementById('exportModal').classList.remove('active');
        }
        
        function proceedExport() {
            closeExportModal();
            window.location.href = 'reports_export_excel.php?date=<?php echo htmlspecialchars($selected_date); ?><?php echo $low_stock_only ? '&low_stock=1' : ''; ?>';
        }
        
        // Close modal when clicking outside
        document.getElementById('exportModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeExportModal();
            }
        });
    </script>
</body>
</html>
