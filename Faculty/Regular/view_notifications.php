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
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';

// Mark notifications as read when viewing
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $mark_read_query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND faculty_id = ?";
    $mark_stmt = $conn->prepare($mark_read_query);
    $mark_stmt->bind_param("ii", $notification_id, $faculty_id);
    $mark_stmt->execute();
    $mark_stmt->close();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $mark_all_query = "UPDATE notifications SET is_read = 1 WHERE faculty_id = ?";
    $mark_all_stmt = $conn->prepare($mark_all_query);
    $mark_all_stmt->bind_param("i", $faculty_id);
    $mark_all_stmt->execute();
    $mark_all_stmt->close();
    
    // Redirect to avoid resubmission
    header("Location: view_notifications.php");
    exit();
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notification_id = $_GET['delete'];
    $delete_query = "DELETE FROM notifications WHERE id = ? AND faculty_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $notification_id, $faculty_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    // Redirect to avoid resubmission
    header("Location: view_notifications.php");
    exit();
}

$query = "SELECT n.*, f.name as faculty_name, f.department as faculty_department
          FROM notifications n
          LEFT JOIN faculty f ON n.faculty_id = f.faculty_id
          WHERE n.faculty_id IN (SELECT faculty_id FROM faculty WHERE department = ?)";
$params = [$department];  // ADDED department parameter
$types = "s";  // CHANGED to string type

// Add search condition
if (!empty($search)) {
    $query .= " AND (n.title LIKE ? OR n.message LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Add type filter
if (!empty($type_filter) && $type_filter !== 'all') {
    $query .= " AND n.notification_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

// Add status filter
if (!empty($status_filter)) {
    if ($status_filter === 'read') {
        $query .= " AND n.is_read = 1";
    } elseif ($status_filter === 'unread') {
        $query .= " AND n.is_read = 0";
    }
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
$valid_sorts = ['created_at', 'title', 'notification_type', 'is_read'];
$sort_order = "DESC";
if (in_array($sort_by, $valid_sorts)) {
    if ($sort_by === 'title') {
        $sort_order = "ASC";
    }
    $query .= " ORDER BY n.$sort_by $sort_order";
} else {
    $query .= " ORDER BY n.created_at DESC, n.is_read ASC";
}

// Get total count for pagination
$count_query = str_replace("SELECT n.*, f.name as faculty_name, f.department as faculty_department", 
                          "SELECT COUNT(*) as total", $query);
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_notifications = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Get unread count
$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE faculty_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $faculty_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['count'];
$unread_stmt->close();

// Pagination
$limit = 15;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_notifications / $limit);

$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Prepare and execute main query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$notifications = $result->fetch_all(MYSQLI_ASSOC);
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
    <title>View Notifications - Faculty Portal</title>
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

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }

        .stat-info p {
            color: var(--gray);
            margin: 5px 0 0 0;
            font-size: 14px;
        }

        .unread-badge {
            background: var(--danger);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 5px;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            flex-wrap: wrap;
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #38b000;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #d90429;
            transform: translateY(-2px);
        }

        /* Notifications Container */
        .notifications-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
        }

        .notifications-header {
            padding: 20px;
            background: var(--light);
            border-bottom: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .notifications-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        /* Notifications List */
        .notifications-list {
            padding: 0;
        }

        .notification-item {
            padding: 20px;
            border-bottom: 1px solid var(--gray-light);
            transition: all 0.3s;
            display: flex;
            gap: 15px;
            align-items: flex-start;
            position: relative;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item.unread {
            background: #f8f9ff;
            border-left: 4px solid var(--primary);
        }

        .notification-item:hover {
            background: var(--light);
        }

        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .icon-info { background: var(--info); }
        .icon-success { background: var(--success); }
        .icon-warning { background: var(--warning); }
        .icon-danger { background: var(--danger); }
        .icon-primary { background: var(--primary); }
        .icon-secondary { background: var(--secondary); }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .notification-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            line-height: 1.4;
        }

        .notification-meta {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-type {
            background: #e3f2fd;
            color: #1976d2;
        }

        .badge-read {
            background: #e8f5e9;
            color: #388e3c;
        }

        .badge-unread {
            background: #f3e5f5;
            color: #7b1fa2;
            font-weight: 600;
        }

        .notification-message {
            color: var(--gray);
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .notification-message p {
            margin: 0 0 10px 0;
        }

        .notification-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--gray);
        }

        .detail-item i {
            color: var(--primary);
            font-size: 12px;
        }

        .notification-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px dashed var(--gray-light);
        }

        .notification-sender {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .sender-avatar {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            font-weight: bold;
        }

        .sender-info {
            color: var(--gray);
        }

        .sender-name {
            font-weight: 500;
            color: var(--dark);
        }

        .notification-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
        }

        .action-read {
            background: var(--light);
            color: var(--dark);
        }

        .action-read:hover {
            background: var(--success);
            color: white;
        }

        .action-delete {
            background: var(--light);
            color: var(--dark);
        }

        .action-delete:hover {
            background: var(--danger);
            color: white;
        }

        /* No Notifications */
        .no-notifications {
            text-align: center;
            padding: 60px 20px;
        }

        .no-notifications i {
            font-size: 48px;
            color: var(--gray-light);
            margin-bottom: 20px;
        }

        .no-notifications h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-notifications p {
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

        /* Responsive */
        @media (max-width: 992px) {
            .main-container {
                padding: 0 20px;
            }
            
            .notification-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .notification-item {
                flex-direction: column;
                gap: 15px;
            }
            
            .notification-icon {
                align-self: flex-start;
            }
            
            .notification-footer {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .notification-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .header-actions {
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
            
            .notification-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .notification-details {
                flex-direction: column;
                gap: 8px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
            
            .page-header h1 {
                font-size: 20px;
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
            <h1><i class="fas fa-bell"></i> My Notifications</h1>
            <p>Stay updated with important announcements, alerts, and system messages.</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_notifications; ?></h3>
                    <p>Total Notifications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--danger) 0%, #d90429 100%);">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $unread_count; ?> <span class="unread-badge">New</span></h3>
                    <p>Unread Notifications</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--success) 0%, #38b000 100%);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $upcoming_events; ?></h3>
                    <p>Upcoming Events</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #ff9100 100%);">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $active_notices; ?></h3>
                    <p>Active Notices</p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="search"><i class="fas fa-search"></i> Search</label>
                        <input type="text" id="search" name="search" class="filter-control" 
                               placeholder="Search notifications..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="type"><i class="fas fa-tag"></i> Type</label>
                        <select id="type" name="type" class="filter-control">
                            <option value="all">All Types</option>
                            <option value="info" <?php echo $type_filter === 'info' ? 'selected' : ''; ?>>Information</option>
                            <option value="warning" <?php echo $type_filter === 'warning' ? 'selected' : ''; ?>>Warning</option>
                            <option value="success" <?php echo $type_filter === 'success' ? 'selected' : ''; ?>>Success</option>
                            <option value="error" <?php echo $type_filter === 'error' ? 'selected' : ''; ?>>Error</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status"><i class="fas fa-envelope"></i> Status</label>
                        <select id="status" name="status" class="filter-control">
                            <option value="">All Status</option>
                            <option value="unread" <?php echo $status_filter === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                            <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read Only</option>
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
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date (Newest First)</option>
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title (A-Z)</option>
                            <option value="notification_type" <?php echo $sort_by === 'notification_type' ? 'selected' : ''; ?>>Type</option>
                            <option value="is_read" <?php echo $sort_by === 'is_read' ? 'selected' : ''; ?>>Read Status</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="view_notifications.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                    <?php if ($unread_count > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-success" onclick="return confirm('Mark all notifications as read?')">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </a>
                    <?php endif; ?>
                    <div style="margin-left: auto; font-size: 14px; color: var(--gray);">
                        Showing <?php echo $total_notifications; ?> notifications
                    </div>
                </div>
            </form>
        </div>

        <!-- Notifications Container -->
        <div class="notifications-container">
            <div class="notifications-header">
                <h2><i class="fas fa-list"></i> Notifications List</h2>
                <div class="header-actions">
                    <?php if ($unread_count > 0): ?>
                        <span class="badge badge-unread">
                            <i class="fas fa-envelope"></i> <?php echo $unread_count; ?> unread
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($total_notifications > 0): ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): 
                        $is_unread = !$notification['is_read'];
                        $item_class = $is_unread ? 'notification-item unread' : 'notification-item';
                        
                        // Determine icon based on notification type
                        $icon_class = 'icon-primary';
                        $icon_type = 'fas fa-bell';
                        switch(strtolower($notification['notification_type'])) {
                            case 'info':
                                $icon_class = 'icon-info';
                                $icon_type = 'fas fa-info-circle';
                                break;
                            case 'warning':
                                $icon_class = 'icon-warning';
                                $icon_type = 'fas fa-exclamation-triangle';
                                break;
                            case 'success':
                                $icon_class = 'icon-success';
                                $icon_type = 'fas fa-check-circle';
                                break;
                            case 'error':
                                $icon_class = 'icon-danger';
                                $icon_type = 'fas fa-times-circle';
                                break;
                        }
                        
                        // Format date
                        $created_date = new DateTime($notification['created_at']);
                        $current_date = new DateTime();
                        $interval = $current_date->diff($created_date);
                        
                        if ($interval->days == 0) {
                            $time_ago = 'Today';
                        } elseif ($interval->days == 1) {
                            $time_ago = 'Yesterday';
                        } elseif ($interval->days < 7) {
                            $time_ago = $interval->days . ' days ago';
                        } else {
                            $time_ago = date('M j, Y', strtotime($notification['created_at']));
                        }
                        
                        // Handle sender name
                        $sender_name = $notification['faculty_name'] ?? 'System';
                        if (empty($sender_name) || $sender_name === 'System') {
                            $sender_name = 'System Notification';
                        }
                    ?>
                        <div class="<?php echo $item_class; ?>">
                            <div class="notification-icon <?php echo $icon_class; ?>">
                                <i class="<?php echo $icon_type; ?>"></i>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-header">
                                    <h3 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                    <div class="notification-meta">
                                        <span class="badge badge-type">
                                            <i class="fas fa-tag"></i>
                                            <?php echo htmlspecialchars(ucfirst($notification['notification_type'] ?? 'info')); ?>
                                        </span>
                                        <span class="badge <?php echo $is_unread ? 'badge-unread' : 'badge-read'; ?>">
                                            <i class="fas fa-envelope<?php echo $is_unread ? '' : '-open'; ?>"></i>
                                            <?php echo $is_unread ? 'Unread' : 'Read'; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="notification-message">
                                    <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                </div>
                                
                                <div class="notification-details">
                                    <div class="detail-item">
                                        <i class="far fa-clock"></i>
                                        <span><?php echo $time_ago; ?> at <?php echo date('h:i A', strtotime($notification['created_at'])); ?></span>
                                    </div>
                                    
                                    <?php if (!empty($notification['target_url'])): ?>
                                        <div class="detail-item">
                                            <i class="fas fa-link"></i>
                                            <a href="<?php echo htmlspecialchars($notification['target_url']); ?>" style="color: var(--primary); text-decoration: none;">
                                                View Related Content
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="notification-footer">
                                    <div class="notification-sender">
                                        <div class="sender-avatar">
                                            <?php echo strtoupper(substr($sender_name, 0, 1)); ?>
                                        </div>
                                        <div class="sender-info">
                                            <span class="sender-name">From: <?php echo htmlspecialchars($sender_name); ?></span>
                                            <?php if (!empty($notification['faculty_department'])): ?>
                                                <span> • <?php echo htmlspecialchars($notification['faculty_department']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="notification-actions">
                                        <?php if ($is_unread): ?>
                                            <a href="?mark_read=<?php echo $notification['id']; ?>" 
                                               class="action-btn action-read" 
                                               title="Mark as Read">
                                                <i class="fas fa-check"></i> Mark Read
                                            </a>
                                        <?php endif; ?>
                                        <a href="?delete=<?php echo $notification['id']; ?>" 
                                           class="action-btn action-delete" 
                                           title="Delete Notification"
                                           onclick="return confirm('Are you sure you want to delete this notification?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-notifications">
                    <i class="far fa-bell-slash"></i>
                    <h3>No Notifications Found</h3>
                    <p>You're all caught up! No notifications match your search criteria.</p>
                    <a href="view_notifications.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1 && $total_notifications > 0): ?>
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
                Notifications help you stay updated with important system activities and announcements.
            </p>
            <p>&copy; <?php echo date('Y'); ?> University Portal - Faculty View</p>
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