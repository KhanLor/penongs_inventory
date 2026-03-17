<?php
require_once 'config.php';
requireManager();

$upload_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$branch_id = $_SESSION['branch_id'];
$success_count = 0;
$error_count = 0;
$errors = [];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading file. Please try again.';
    } elseif (!in_array($file['type'], ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])) {
        $errors[] = 'Invalid file type. Please upload an Excel file (.xlsx or .xls).';
    } else {
        // Process Excel file
        $processed_items = processExcelFile($file['tmp_name'], $upload_date, $branch_id, $conn);
        $success_count = $processed_items['success'];
        $error_count = $processed_items['errors'];
        $errors = $processed_items['error_messages'];
    }
}

function processExcelFile($file_path, $upload_date, $branch_id, $conn) {
    $success = 0;
    $errors = 0;
    $error_messages = [];
    
    // Read Excel file
    $file = fopen($file_path, 'r');
    if (!$file) {
        return [
            'success' => 0,
            'errors' => 1,
            'error_messages' => ['Could not open file']
        ];
    }
    
    $row = 0;
    $skip_rows = 0;
    $data_started = false;
    
    while (($line = fgetcsv($file, 10000, ",")) !== FALSE) {
        $row++;
        
        // Skip header rows (look for the data header row)
        if (!$data_started) {
            if (implode('', $line) && strpos(implode('|', $line), 'Item') !== false) {
                $data_started = true;
                continue; // Skip header row
            }
            continue;
        }
        
        // Skip empty rows
        if (empty($line[0])) {
            continue;
        }
        
        $item_code = trim($line[0]);
        $description = isset($line[1]) ? trim($line[1]) : '';
        $beg_qty = isset($line[2]) ? floatval($line[2]) : 0;
        $sales = isset($line[9]) ? abs(floatval($line[9])) : 0; // -Sales column
        $end_qty = isset($line[10]) ? floatval($line[10]) : 0;
        
        // Skip category headers or totals
        if (empty($item_code) || strtoupper($item_code) === 'TOTAL' || $item_code === 'Item') {
            continue;
        }
        
        // Find matching item
        $item_query = $conn->prepare("
            SELECT id, item_name, unit FROM items 
            WHERE (UPPER(item_code) = UPPER(?) OR UPPER(item_name) LIKE UPPER(?))
            LIMIT 1
        ");
        $search_pattern = '%' . $item_code . '%';
        $item_query->bind_param("ss", $item_code, $search_pattern);
        $item_query->execute();
        $item_result = $item_query->get_result();
        
        if ($item_result->num_rows === 0) {
            $errors++;
            $error_messages[] = "Row {$row}: Item '{$item_code}' not found in system";
            continue;
        }
        
        $item = $item_result->fetch_assoc();
        $item_id = $item['id'];
        
        // Calculate beginning inventory from previous day's ending inventory
        $prev_inventory = $conn->prepare("
            SELECT ending_inventory FROM daily_inventory 
            WHERE item_id = ? AND branch_id = ? AND inventory_date < ?
            ORDER BY inventory_date DESC
            LIMIT 1
        ");
        $prev_inventory->bind_param("iis", $item_id, $branch_id, $upload_date);
        $prev_inventory->execute();
        $prev_result = $prev_inventory->get_result();
        
        $beginning_inventory = 0;
        if ($prev_result->num_rows > 0) {
            $prev_row = $prev_result->fetch_assoc();
            $beginning_inventory = floatval($prev_row['ending_inventory']);
        }
        
        // Check if record exists for this date
        $check_query = $conn->prepare("
            SELECT id FROM daily_inventory 
            WHERE item_id = ? AND branch_id = ? AND inventory_date = ?
        ");
        $check_query->bind_param("iis", $item_id, $branch_id, $upload_date);
        $check_query->execute();
        $check_result = $check_query->get_result();
        
        $today = date('Y-m-d H:i:s');
        
        if ($check_result->num_rows > 0) {
            // Update existing record
            $update_query = $conn->prepare("
                UPDATE daily_inventory SET 
                    beginning_inventory = ?,
                    daily_sales = ?,
                    ending_inventory = ?,
                    total_stock = ?,
                    added_stock = 0,
                    updated_at = ?
                WHERE item_id = ? AND branch_id = ? AND inventory_date = ?
            ");
            $total_stock = $beginning_inventory + 0 - $sales; // beginning + added - sales
            $update_query->bind_param("dddddsiss", $beginning_inventory, $sales, $end_qty, $total_stock, $today, $item_id, $branch_id, $upload_date);
            
            if ($update_query->execute()) {
                $success++;
                logActivity($conn, $_SESSION['user_id'], "Updated inventory for {$item['item_name']} via Excel import");
            } else {
                $errors++;
                $error_messages[] = "Row {$row}: Failed to update {$item['item_name']}";
            }
        } else {
            // Insert new record
            $total_stock = $beginning_inventory + 0 - $sales;
            $remarks = 'Uploaded via POS Report';
            $insert_query = $conn->prepare("
                INSERT INTO daily_inventory (
                    item_id, branch_id, inventory_date, 
                    beginning_inventory, added_stock, total_stock, 
                    daily_sales, ending_inventory, remarks, 
                    prepared_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_query->bind_param(
                "iisdddssis",
                $item_id, $branch_id, $upload_date,
                $beginning_inventory, $total_stock,
                $sales, $end_qty, $remarks,
                $_SESSION['user_id'], $today, $today
            );
            
            if ($insert_query->execute()) {
                $success++;
                logActivity($conn, $_SESSION['user_id'], "Created inventory for {$item['item_name']} via Excel import");
            } else {
                $errors++;
                $error_messages[] = "Row {$row}: Failed to insert {$item['item_name']}";
            }
        }
    }
    
    fclose($file);
    
    return [
        'success' => $success,
        'errors' => $errors,
        'error_messages' => $error_messages
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Inventory - Penongs Inventory</title>
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
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            color: #7F8C8D;
            font-size: 14px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="date"],
        input[type="file"],
        input[type="text"],
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        input[type="date"]:focus,
        input[type="file"]:focus,
        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: #E74C3C;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }
        
        .upload-area {
            border: 2px dashed #E74C3C;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            background: #FADBD8;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .upload-area:hover {
            background: #F5B7B1;
            border-color: #C0392B;
        }
        
        .upload-area.dragover {
            background: #F5B7B1;
            border-color: #C0392B;
        }
        
        .upload-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        
        .upload-text {
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .upload-subtext {
            color: #7F8C8D;
            font-size: 13px;
        }
        
        #file-input {
            display: none;
        }
        
        .file-info {
            background: #EBF5FB;
            border-left: 4px solid #3498DB;
            padding: 12px 15px;
            border-radius: 5px;
            margin-top: 10px;
            display: none;
        }
        
        .file-info.show {
            display: block;
        }
        
        .file-name {
            color: #3498DB;
            font-weight: 600;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 25px;
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
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #95A5A6;
            color: white;
            flex: 1;
        }
        
        .btn-secondary:hover {
            background: #7F8C8D;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #D5F4E6;
            border-color: #27AE60;
            color: #1E8449;
        }
        
        .alert-error {
            background: #FADBD8;
            border-color: #E74C3C;
            color: #A93226;
        }
        
        .alert-info {
            background: #D6EAF8;
            border-color: #3498DB;
            color: #1A5276;
        }
        
        .error-list {
            margin-top: 10px;
            padding-left: 20px;
        }
        
        .error-list li {
            margin: 5px 0;
            font-size: 13px;
        }
        
        .instructions {
            background: #FEF5E7;
            border-left: 4px solid #F39C12;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .instructions h4 {
            color: #F39C12;
            margin-bottom: 10px;
        }
        
        .instructions ol {
            margin-left: 20px;
            color: #555;
            font-size: 14px;
        }
        
        .instructions li {
            margin: 5px 0;
        }
        
        .results-summary {
            background: #F0F0F0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .results-summary .stat {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 5px 0;
        }
        
        .results-summary .success {
            color: #27AE60;
            font-weight: 600;
        }
        
        .results-summary .error {
            color: #E74C3C;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .page-title {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="navbar-brand">📤 Upload Inventory</div>
            <a href="manager_dashboard.php" class="btn-back">← Back to Dashboard</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="card">
            <h1 class="page-title">Upload POS End-of-Day Report</h1>
            <p class="page-subtitle">Import inventory data from your POS system Excel report</p>
            
            <?php if ($success_count > 0 || $error_count > 0): ?>
                <div class="alert alert-success">
                    <strong>✓ Upload Complete!</strong>
                    <div class="results-summary">
                        <div class="stat">
                            <span style="font-size: 20px;">✓</span>
                            <span class="success"><?php echo $success_count; ?> items successfully imported</span>
                        </div>
                        <?php if ($error_count > 0): ?>
                            <div class="stat">
                                <span style="font-size: 20px;">⚠</span>
                                <span class="error"><?php echo $error_count; ?> items failed</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="error-list">
                            <strong>Errors:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="instructions">
                <h4>📋 Instructions:</h4>
                <ol>
                    <li>Select the inventory date</li>
                    <li>Upload your POS end-of-day inventory report (Excel format)</li>
                    <li>The system will automatically match items and update inventory</li>
                    <li>Beginning inventory is calculated from the previous day's ending inventory</li>
                </ol>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="inventory_date">📅 Inventory Date:</label>
                    <input type="date" id="inventory_date" name="inventory_date" value="<?php echo $upload_date; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>📁 Excel File:</label>
                    <div class="upload-area" id="upload-area">
                        <div class="upload-icon">📤</div>
                        <div class="upload-text">Click or drag Excel file here</div>
                        <div class="upload-subtext">Supports .xlsx and .xls files</div>
                        <input type="file" id="file-input" name="excel_file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
                    </div>
                    <div class="file-info" id="file-info">
                        <span class="file-name" id="file-name"></span> selected
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-primary" id="submit-btn" disabled>
                        📤 Upload & Import
                    </button>
                    <a href="manager_dashboard.php" class="btn btn-secondary" style="text-align: center; line-height: 1;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('file-name');
        const submitBtn = document.getElementById('submit-btn');
        
        // Click to upload
        uploadArea.addEventListener('click', () => fileInput.click());
        
        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect();
            }
        });
        
        // File selection
        fileInput.addEventListener('change', handleFileSelect);
        
        function handleFileSelect() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
                
                if (!validTypes.includes(file.type) && !file.name.endsWith('.xlsx') && !file.name.endsWith('.xls')) {
                    alert('Please select a valid Excel file');
                    fileInput.value = '';
                    fileInfo.classList.remove('show');
                    submitBtn.disabled = true;
                    return;
                }
                
                fileName.textContent = file.name;
                fileInfo.classList.add('show');
                submitBtn.disabled = false;
            } else {
                fileInfo.classList.remove('show');
                submitBtn.disabled = true;
            }
        }
    </script>
</body>
</html>
