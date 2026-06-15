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

$query = "SELECT n.*, f.name as faculty_name, f.department as faculty_department
          FROM news_updates n
          LEFT JOIN faculty f ON n.faculty_id = f.faculty_id
          WHERE f.department = ?";  // ADDED department filter
$params = [$department];  // ADDED department parameter
$types = "s";  // CHANGED to string type

// Add search condition
if (!empty($search)) {
    $query .= " AND (n.title LIKE ? OR n.content LIKE ? OR f.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

// Add type filter
if (!empty($type_filter) && $type_filter !== 'all') {
    $query .= " AND n.type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

// Add status filter
if (!empty($status_filter)) {
    if ($status_filter === 'published') {
        $query .= " AND n.is_published = 1";
    } elseif ($status_filter === 'draft') {
        $query .= " AND n.is_published = 0";
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
$valid_sorts = ['created_at', 'title', 'type', 'updated_at'];
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
$count_query = str_replace("SELECT n.*, f.name as faculty_name, f.department as faculty_department", 
                          "SELECT COUNT(*) as total", $query);
$count_stmt = $conn->prepare($count_query);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_news = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Get my news count for statistics
$my_news = $conn->query("SELECT COUNT(*) as count FROM news_updates WHERE faculty_id = $faculty_id")->fetch_assoc()['count'];
$active_notices = $conn->query("SELECT COUNT(*) as count FROM notices WHERE faculty_id = $faculty_id AND is_active = 1")->fetch_assoc()['count'];
$upcoming_events = $conn->query("SELECT COUNT(*) as count FROM events WHERE faculty_id = $faculty_id AND event_date >= CURDATE()")->fetch_assoc()['count'];

// Pagination
$limit = 10;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_news / $limit);

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
$news_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View News - Faculty Portal</title>
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

        /* News Grid */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .news-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .news-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            position: relative;
        }

        .news-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .news-date {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .news-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            line-height: 1.4;
        }

        .news-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .news-content {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
            flex: 1;
        }

        .news-content p {
            margin: 0 0 10px 0;
        }

        .news-content p:last-child {
            margin-bottom: 0;
        }

        .news-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: auto;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .detail-item i {
            color: var(--primary);
            width: 20px;
        }

        .detail-label {
            font-weight: 500;
            color: var(--dark);
            min-width: 80px;
        }

        .detail-value {
            color: var(--gray);
        }

        .news-footer {
            padding: 15px 20px;
            background: var(--light);
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .news-author {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .author-avatar {
            width: 30px;
            height: 30px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .author-info {
            display: flex;
            flex-direction: column;
        }

        .author-name {
            font-weight: 500;
            color: var(--dark);
        }

        .author-dept {
            color: var(--gray);
            font-size: 11px;
        }

        .news-status {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 500;
        }

        .status-published {
            background: #e8f5e9;
            color: #388e3c;
        }

        .status-draft {
            background: #fff3e0;
            color: #f57c00;
        }

        /* No News */
        .no-news {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .no-news i {
            font-size: 48px;
            color: var(--gray-light);
            margin-bottom: 20px;
        }

        .no-news h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-news p {
            color: var(--gray);
            max-width: 400px;
            margin: 0 auto 20px;
        }

        /* Attachment */
        .news-attachment {
            margin-top: 15px;
            padding: 10px;
            background: var(--light);
            border-radius: 6px;
            border-left: 3px solid var(--primary);
        }

        .attachment-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 13px;
        }

        .attachment-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            transition: all 0.3s;
        }

        .attachment-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
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
            
            .news-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
            
            .news-grid {
                grid-template-columns: 1fr;
            }
            
            .news-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .news-status {
                align-self: flex-start;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                padding: 0 15px;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .news-card {
                margin-bottom: 20px;
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
            <h1><i class="fas fa-newspaper"></i> View All News & Updates</h1>
            <p>Browse through all news articles and updates from faculty members across the university.</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_news; ?></h3>
                    <p>Total News</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--success) 0%, #38b000 100%);">
                    <i class="fas fa-pen-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $my_news; ?></h3>
                    <p>My News Posts</p>
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
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--info) 0%, #00b4d8 100%);">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $upcoming_events; ?></h3>
                    <p>Upcoming Events</p>
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
                               placeholder="Search news by title, content, or author..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="type"><i class="fas fa-tag"></i> Type</label>
                        <select id="type" name="type" class="filter-control">
                            <option value="all">All Types</option>
                            <option value="news" <?php echo $type_filter === 'news' ? 'selected' : ''; ?>>News</option>
                            <option value="update" <?php echo $type_filter === 'update' ? 'selected' : ''; ?>>Updates</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="status" name="status" class="filter-control">
                            <option value="">All Status</option>
                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published Only</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft Only</option>
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
                            <option value="type" <?php echo $sort_by === 'type' ? 'selected' : ''; ?>>Type</option>
                            <option value="updated_at" <?php echo $sort_by === 'updated_at' ? 'selected' : ''; ?>>Last Updated</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="view_news.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                    <div style="margin-left: auto; font-size: 14px; color: var(--gray);">
                        <?php echo $total_news; ?> news articles found
                    </div>
                </div>
            </form>
        </div>

        <!-- News Grid -->
        <?php if ($total_news > 0): ?>
            <div class="news-grid">
                <?php foreach ($news_items as $news): 
                    $status_class = $news['is_published'] ? 'status-published' : 'status-draft';
                    $status_text = $news['is_published'] ? 'Published' : 'Draft';
                    $type_text = $news['type'] === 'news' ? 'News Article' : 'Update';
                ?>
                    <div class="news-card">
                        <div class="news-header">
                            <div class="news-date">
                                <i class="far fa-calendar"></i>
                                <?php echo date('F j, Y', strtotime($news['created_at'])); ?>
                            </div>
                            <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                            <div class="news-badge"><?php echo $type_text; ?></div>
                        </div>
                        
                        <div class="news-body">
                            <div class="news-content">
                                <?php 
                                $content = htmlspecialchars($news['content']);
                                if (strlen($content) > 200) {
                                    echo nl2br(substr($content, 0, 200) . '...');
                                } else {
                                    echo nl2br($content);
                                }
                                ?>
                            </div>
                            
                            <?php if (!empty($news['attachment'])): ?>
                                <div class="news-attachment">
                                    <div class="attachment-label">
                                        <i class="fas fa-paperclip"></i> Attachment:
                                    </div>
                                    <a href="<?php echo htmlspecialchars($news['attachment']); ?>" 
                                       class="attachment-link" 
                                       target="_blank">
                                        <i class="fas fa-download"></i>
                                        Download File
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <div class="news-details">
                                <?php if ($news['created_at'] != $news['updated_at']): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-sync-alt"></i>
                                        <span class="detail-label">Updated:</span>
                                        <span class="detail-value"><?php echo date('M j, Y', strtotime($news['updated_at'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="news-footer">
                            <div class="news-author">
                                <div class="author-avatar">
                                    <?php echo strtoupper(substr($news['faculty_name'] ?? 'A', 0, 1)); ?>
                                </div>
                                <div class="author-info">
                                    <div class="author-name"><?php echo htmlspecialchars($news['faculty_name'] ?? 'Anonymous'); ?></div>
                                    <div class="author-dept"><?php echo htmlspecialchars($news['faculty_department'] ?? 'Unknown Department'); ?></div>
                                </div>
                            </div>
                            
                            <div class="news-status <?php echo $status_class; ?>">
                                <i class="fas fa-circle"></i> <?php echo $status_text; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
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
            
        <?php else: ?>
            <div class="no-news">
                <i class="far fa-newspaper"></i>
                <h3>No News Found</h3>
                <p>No news articles match your search criteria. Try adjusting your filters or check back later.</p>
                <a href="view_news.php" class="btn btn-primary">
                    <i class="fas fa-redo"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="footer">
            <p>
                <i class="fas fa-info-circle"></i> 
                This is a view-only page. You can browse news but cannot edit or add new articles.
            </p>
            <p>&copy; <?php echo date('Y'); ?> University Portal - Faculty View | Showing <?php echo $total_news; ?> news articles</p>
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