<?php
session_start();

// Check if user is logged in as faculty
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../../login.php");
    exit();
}

// Try different paths for database connection
$db_paths = [
    '../../db_connect.php',
    '../../../db_connect.php',
    'db_connect.php'
];

$conn = null;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        include($path);
        if (isset($conn) && $conn !== null) {
            break;
        }
    }
}

if ($conn === null) {
    $conn = new mysqli('localhost', 'root', '', 'my project');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

$faculty_id = $_SESSION['user_id'];
$faculty_name = $_SESSION['user_name'] ?? 'Faculty Member';
$department = $_SESSION['department'] ?? 'Department';

// Get faculty details
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ?";
$faculty_stmt = $conn->prepare($faculty_query);
$faculty_stmt->bind_param("i", $faculty_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
$faculty_data = $faculty_result->fetch_assoc();
$faculty_stmt->close();

// If no faculty data found, redirect to login
if (!$faculty_data) {
    session_destroy();
    header("Location: ../../login.php");
    exit();
}

// Search and filter functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';


$query = "SELECT n.*, f.name as faculty_name, f.department 
          FROM notices n 
          LEFT JOIN faculty f ON n.faculty_id = f.faculty_id 
          WHERE f.department = ?";  // ADDED department filter
$params = [$department];  // ADDED department parameter
$types = "s";  // CHANGED to string type

// Add search condition
if (!empty($search)) {
    $query .= " AND (n.title LIKE ? OR n.content LIKE ? OR n.category LIKE ? OR f.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

// Add status filter
if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $query .= " AND n.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $query .= " AND n.is_active = 0";
    }
}

// Add priority filter
if (!empty($priority_filter) && $priority_filter !== 'all') {
    $query .= " AND n.priority = ?";
    $params[] = $priority_filter;
    $types .= "s";
}

// Add date range filter
if (!empty($date_from)) {
    $query .= " AND DATE(n.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(n.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Add sorting
$valid_sorts = ['created_at', 'title', 'priority', 'expiry_date', 'faculty_name'];
$sort_order = "DESC";
if (in_array($sort_by, $valid_sorts)) {
    if ($sort_by === 'title') {
        $sort_order = "ASC";
    }
    $query .= " ORDER BY n.$sort_by $sort_order";
} else {
    $query .= " ORDER BY n.created_at DESC";
}

// Get total count for pagination
$count_query = str_replace("SELECT n.*, f.name as faculty_name, f.department", "SELECT COUNT(*) as total", $query);
$count_stmt = $conn->prepare($count_query);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_notices = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Pagination
$limit = 10;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_notices / $limit);

$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Prepare and execute main query
$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$notices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get counts for statistics
$active_notices = $conn->query("SELECT COUNT(*) as count FROM notices WHERE faculty_id = $faculty_id AND is_active = 1")->fetch_assoc()['count'];
$upcoming_events = $conn->query("SELECT COUNT(*) as count FROM events WHERE faculty_id = $faculty_id AND event_date >= CURDATE()")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Notices - Faculty Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #ff9e00;
            --info: #4cc9f0;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border-radius: 12px;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        /* Header */
        .header {
            background: white;
            padding: 15px 30px;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
        }

        .logo i {
            font-size: 24px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .user-details {
            text-align: right;
        }

        .user-details h4 {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
        }

        .user-details p {
            font-size: 12px;
            color: var(--gray);
            margin: 2px 0 0 0;
        }

        .logout-btn {
            background: var(--gray-light);
            color: var(--dark);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: var(--danger);
            color: white;
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary);
        }

        .page-header h1 {
            color: var(--dark);
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header p {
            color: var(--gray);
            margin: 0;
        }

        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: var(--primary-dark);
            transform: translateX(-3px);
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
        }

        .filter-control {
            padding: 10px 15px;
            border: 1px solid var(--gray-light);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background: white;
        }

        .filter-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--gray-light);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--gray);
            color: white;
        }

        /* Notices List */
        .notices-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
        }

        .notices-header {
            padding: 20px;
            background: var(--light);
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notices-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notices-count {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .notices-list {
            padding: 0;
        }

        .notice-item {
            padding: 25px;
            border-bottom: 1px solid var(--gray-light);
            transition: all 0.3s;
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .notice-item:last-child {
            border-bottom: none;
        }

        .notice-item:hover {
            background: var(--light);
        }

        .notice-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
        }

        .notice-content {
            flex: 1;
        }

        .notice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .notice-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            line-height: 1.4;
        }

        .notice-meta {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-priority {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-high {
            background: #ffebee;
            color: #d32f2f;
        }

        .badge-medium {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge-low {
            background: #e8f5e9;
            color: #388e3c;
        }

        .badge-status {
            background: var(--light);
            color: var(--gray);
        }

        .badge-active {
            background: #e8f5e9;
            color: #388e3c;
        }

        .badge-inactive {
            background: #ffebee;
            color: #d32f2f;
        }

        .badge-category {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .notice-description {
            color: var(--gray);
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .notice-description p {
            margin: 0 0 10px 0;
        }

        .notice-details {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
        }

        .detail-item i {
            color: var(--primary);
        }

        .notice-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px dashed var(--gray-light);
        }

        .notice-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .author-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .author-info {
            font-size: 13px;
        }

        .author-name {
            font-weight: 500;
            color: var(--dark);
        }

        .author-dept {
            color: var(--gray);
            font-size: 12px;
        }

        .notice-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .action-view {
            background: var(--light);
            color: var(--dark);
        }

        .action-view:hover {
            background: var(--primary);
            color: white;
        }

        /* No Notices */
        .no-notices {
            text-align: center;
            padding: 60px 20px;
        }

        .no-notices i {
            font-size: 48px;
            color: var(--gray-light);
            margin-bottom: 20px;
        }

        .no-notices h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-notices p {
            color: var(--gray);
            max-width: 400px;
            margin: 0 auto 20px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }

        .pagination a, .pagination span {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .pagination a {
            background: white;
            color: var(--dark);
            border: 1px solid var(--gray-light);
        }

        .pagination a:hover, .pagination a.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination span {
            background: var(--gray-light);
            color: var(--gray);
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 25px;
            background: white;
            margin-top: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            color: var(--gray);
            font-size: 14px;
        }

        .footer p {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .footer i {
            color: var(--primary);
        }

        /* Expiry Warning */
        .expiry-warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 10px 15px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .expiry-warning i {
            color: #ff9800;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .main-container {
                padding: 0 20px;
            }
            
            .notice-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .notice-meta {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .notice-item {
                flex-direction: column;
                gap: 15px;
            }
            
            .notice-icon {
                align-self: flex-start;
            }
            
            .notice-footer {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .notice-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                padding: 0 15px;
            }
            
            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .notices-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .notice-details {
                flex-direction: column;
                gap: 10px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="faculty_dashboard.php" class="logo">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Faculty Portal</span>
            </a>
            
            <div class="user-info">
                <div class="avatar">
                    <?php echo strtoupper(substr($faculty_name, 0, 2)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($faculty_name); ?></h4>
                    <p><?php echo htmlspecialchars($department); ?></p>
                </div>
                <a href="../../login.php?logout=true" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Back Button -->
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-bullhorn"></i> View All Notices</h1>
            <p>Browse through all notices in the system. You can filter by status, priority, and search for specific notices.</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="search"><i class="fas fa-search"></i> Search</label>
                        <input type="text" id="search" name="search" class="filter-control" 
                               placeholder="Search by title, content, category, or faculty..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="status" name="status" class="filter-control">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="priority"><i class="fas fa-exclamation-circle"></i> Priority</label>
                        <select id="priority" name="priority" class="filter-control">
                            <option value="all">All Priorities</option>
                            <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from"><i class="fas fa-calendar"></i> From Date</label>
                        <input type="date" id="date_from" name="date_from" class="filter-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to"><i class="fas fa-calendar"></i> To Date</label>
                        <input type="date" id="date_to" name="date_to" class="filter-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort"><i class="fas fa-sort"></i> Sort By</label>
                        <select id="sort" name="sort" class="filter-control">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Created Date (Newest First)</option>
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title (A-Z)</option>
                            <option value="priority" <?php echo $sort_by === 'priority' ? 'selected' : ''; ?>>Priority</option>
                            <option value="expiry_date" <?php echo $sort_by === 'expiry_date' ? 'selected' : ''; ?>>Expiry Date</option>
                            <option value="faculty_name" <?php echo $sort_by === 'faculty_name' ? 'selected' : ''; ?>>Faculty Name</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="view_notices.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                    <div style="margin-left: auto; font-size: 14px; color: var(--gray);">
                        <?php echo $total_notices; ?> notices found
                    </div>
                </div>
            </form>
        </div>

        <!-- Notices List -->
        <div class="notices-container">
            <div class="notices-header">
                <h2><i class="fas fa-clipboard-list"></i> All Notices</h2>
                <div class="notices-count"><?php echo $total_notices; ?> notices</div>
            </div>
            
            <?php if ($total_notices > 0): ?>
                <div class="notices-list">
                    <?php foreach ($notices as $notice): 
                        $priority_class = '';
                        $priority_icon = '';
                        switch(strtolower($notice['priority'])) {
                            case 'high':
                                $priority_class = 'badge-high';
                                $priority_icon = 'fas fa-exclamation-circle';
                                break;
                            case 'medium':
                                $priority_class = 'badge-medium';
                                $priority_icon = 'fas fa-exclamation-triangle';
                                break;
                            case 'low':
                                $priority_class = 'badge-low';
                                $priority_icon = 'fas fa-info-circle';
                                break;
                            default:
                                $priority_class = 'badge-priority';
                                $priority_icon = 'fas fa-flag';
                        }
                        
                        $status_class = $notice['is_active'] ? 'badge-active' : 'badge-inactive';
                        $status_text = $notice['is_active'] ? 'Active' : 'Inactive';
                        $status_icon = $notice['is_active'] ? 'fas fa-check-circle' : 'fas fa-times-circle';
                        
                        // Check if notice is expired or near expiry
                        $expiry_warning = '';
                        if (!empty($notice['expiry_date'])) {
                            $expiry_date = new DateTime($notice['expiry_date']);
                            $current_date = new DateTime();
                            $days_remaining = $current_date->diff($expiry_date)->days;
                            
                            if ($expiry_date < $current_date) {
                                $expiry_warning = '<div class="expiry-warning"><i class="fas fa-exclamation-triangle"></i> This notice expired on ' . date('F j, Y', strtotime($notice['expiry_date'])) . '</div>';
                            } elseif ($days_remaining <= 3) {
                                $expiry_warning = '<div class="expiry-warning"><i class="fas fa-clock"></i> This notice expires in ' . $days_remaining . ' day' . ($days_remaining != 1 ? 's' : '') . ' (on ' . date('F j, Y', strtotime($notice['expiry_date'])) . ')</div>';
                            }
                        }
                    ?>
                        <div class="notice-item">
                            <div class="notice-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            
                            <div class="notice-content">
                                <div class="notice-header">
                                    <h3 class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></h3>
                                    <div class="notice-meta">
                                        <span class="badge <?php echo $priority_class; ?>">
                                            <i class="<?php echo $priority_icon; ?>"></i>
                                            <?php echo htmlspecialchars($notice['priority'] ?? 'Normal'); ?>
                                        </span>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <i class="<?php echo $status_icon; ?>"></i>
                                            <?php echo $status_text; ?>
                                        </span>
                                        <?php if (!empty($notice['category'])): ?>
                                            <span class="badge badge-category">
                                                <i class="fas fa-tag"></i>
                                                <?php echo htmlspecialchars($notice['category']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($notice['content'])): ?>
                                    <div class="notice-description">
                                        <?php echo nl2br(htmlspecialchars(substr($notice['content'], 0, 300))); ?>
                                        <?php if (strlen($notice['content']) > 300): ?>...<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="notice-details">
                                    <?php if (!empty($notice['created_at'])): ?>
                                        <div class="detail-item">
                                            <i class="far fa-calendar-plus"></i>
                                            <span>Posted: <?php echo date('F j, Y', strtotime($notice['created_at'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($notice['expiry_date'])): ?>
                                        <div class="detail-item">
                                            <i class="far fa-calendar-times"></i>
                                            <span>Expires: <?php echo date('F j, Y', strtotime($notice['expiry_date'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($notice['target_audience'])): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-users"></i>
                                            <span>Audience: <?php echo htmlspecialchars($notice['target_audience']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php echo $expiry_warning; ?>
                                
                                <div class="notice-footer">
                                    <div class="notice-author">
                                        <div class="author-avatar">
                                            <?php echo strtoupper(substr($notice['faculty_name'] ?? 'F', 0, 1)); ?>
                                        </div>
                                        <div class="author-info">
                                            <div class="author-name"><?php echo htmlspecialchars($notice['faculty_name'] ?? 'Unknown Faculty'); ?></div>
                                            <div class="author-dept"><?php echo htmlspecialchars($notice['department'] ?? 'Unknown Department'); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="notice-actions">
                                        <a href="#" class="action-btn action-view" title="View Full Notice">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-notices">
                    <i class="far fa-clipboard"></i>
                    <h3>No Notices Found</h3>
                    <p>No notices match your search criteria. Try adjusting your filters or check back later.</p>
                    <a href="view_notices.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1 && $total_notices > 0): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php else: ?>
                    <span><i class="fas fa-chevron-left"></i> Previous</span>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="active">
                            <?php echo $i; ?>
                        </a>
                    <?php elseif ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span>Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="footer">
            <p>
                <i class="fas fa-info-circle"></i> 
                This is a view-only page. You can browse notices but cannot edit or add new ones.
            </p>
            <p>&copy; <?php echo date('Y'); ?> University Portal - Faculty View | Showing <?php echo $total_notices; ?> notices</p>
        </footer>
    </div>

    <script>
        // Set today's date as max for date inputs
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            if (dateFrom) {
                dateFrom.max = today;
            }
            if (dateTo) {
                dateTo.max = today;
            }
            
            // Validate date range
            if (dateFrom && dateTo) {
                dateFrom.addEventListener('change', function() {
                    dateTo.min = this.value;
                });
                
                dateTo.addEventListener('change', function() {
                    dateFrom.max = this.value;
                });
            }
        });
    </script>
</body>
</html>