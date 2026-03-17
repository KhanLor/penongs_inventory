<?php
require_once 'config.php';
requireAdmin();

// Get filter parameters
$filter_user = isset($_GET['user_id']) ? sanitize($_GET['user_id']) : '';
$filter_action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$filter_date = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT al.*, u.full_name, u.username FROM activity_logs al JOIN users u ON al.user_id = u.id WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM activity_logs al JOIN users u ON al.user_id = u.id WHERE 1=1";

if ($filter_user) {
    $query .= " AND al.user_id = '$filter_user'";
    $count_query .= " AND al.user_id = '$filter_user'";
}

if ($filter_action) {
    $query .= " AND al.action LIKE '%$filter_action%'";
    $count_query .= " AND al.action LIKE '%$filter_action%'";
}

if ($filter_date) {
    $query .= " AND DATE(al.created_at) = '$filter_date'";
    $count_query .= " AND DATE(al.created_at) = '$filter_date'";
}

$query .= " ORDER BY al.created_at DESC LIMIT $limit OFFSET $offset";

$activities = $conn->query($query);
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get all users for filter dropdown
$users_result = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Penongs Inventory</title>
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
            max-width: 1200px;
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
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #F4D03F;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn-filter {
            padding: 10px 25px;
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(244, 208, 63, 0.3);
        }
        
        .btn-clear {
            padding: 10px 25px;
            background: #E8E8E8;
            color: #333;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-clear:hover {
            background: #D0D0D0;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #E8E8E8;
            font-size: 14px;
        }
        
        tr:hover {
            background: #F8F9FA;
        }
        
        .action-badge {
            display: inline-block;
            padding: 5px 10px;
            background: #E3F2FD;
            color: #1976D2;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px 0;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            background: white;
            border: 2px solid #E8E8E8;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            border-color: #F4D03F;
            color: #F4D03F;
        }
        
        .pagination .active {
            background: linear-gradient(135deg, #F4D03F 0%, #F39C12 100%);
            color: white;
            border-color: #F4D03F;
        }
        
        .no-data {
            padding: 40px;
            text-align: center;
            color: #7F8C8D;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .filters {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Activity Logs</h1>
            <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <!-- Filters -->
        <form method="GET" class="filters">
            <div class="filter-group">
                <label for="user_id">User</label>
                <select name="user_id" id="user_id">
                    <option value="">All Users</option>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo $user['full_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="action">Action</label>
                <input type="text" name="action" id="action" placeholder="Search action..." value="<?php echo $filter_action; ?>">
            </div>
            
            <div class="filter-group">
                <label for="date">Date</label>
                <input type="date" name="date" id="date" value="<?php echo $filter_date; ?>">
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="btn-filter">🔍 Filter</button>
                <a href="activity_logs.php" class="btn-clear">Clear</a>
            </div>
        </form>
        
        <!-- Activity Table -->
        <div class="table-container">
            <?php if ($activities->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Action</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($activity = $activities->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($activity['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                <td><span class="action-badge"><?php echo htmlspecialchars($activity['action']); ?></span></td>
                                <td><?php echo date('M d, Y H:i A', strtotime($activity['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&user_id=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&user_id=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>" 
                               class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&user_id=<?php echo $filter_user; ?>&action=<?php echo $filter_action; ?>&date=<?php echo $filter_date; ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>No activity logs found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>