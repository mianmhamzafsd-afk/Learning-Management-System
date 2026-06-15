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
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'event_date';


// Base query for events - MODIFIED to only show department events
$query = "SELECT e.*, f.name as faculty_name, f.department 
          FROM events e 
          LEFT JOIN faculty f ON e.faculty_id = f.faculty_id 
          WHERE f.department = ?";  // ADDED department filter
$params = [$department];  // ADDED department parameter
$types = "s";  // CHANGED to string type

// Add search condition
if (!empty($search)) {
    $query .= " AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ? OR f.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

// Add status filter
if (!empty($status_filter)) {
    $current_date = date('Y-m-d');
    if ($status_filter === 'upcoming') {
        $query .= " AND e.event_date >= ?";
        $params[] = $current_date;
        $types .= "s";
    } elseif ($status_filter === 'past') {
        $query .= " AND e.event_date < ?";
        $params[] = $current_date;
        $types .= "s";
    } elseif ($status_filter === 'today') {
        $query .= " AND e.event_date = ?";
        $params[] = $current_date;
        $types .= "s";
    }
}

// Add date range filter
if (!empty($date_from)) {
    $query .= " AND e.event_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND e.event_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Add sorting
$valid_sorts = ['event_date', 'title', 'created_at', 'faculty_name'];
if (in_array($sort_by, $valid_sorts)) {
    $query .= " ORDER BY e.$sort_by";
    if ($sort_by === 'event_date') {
        $query .= " DESC";
    } else {
        $query .= " ASC";
    }
} else {
    $query .= " ORDER BY e.event_date DESC";
}

// Get total count for pagination
$count_query = str_replace("SELECT e.*, f.name as faculty_name, f.department", "SELECT COUNT(*) as total", $query);
$count_stmt = $conn->prepare($count_query);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_events = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Pagination
$limit = 10;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_events / $limit);

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
$events = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get upcoming events count for statistics
$upcoming_events = $conn->query("SELECT COUNT(*) as count FROM events WHERE faculty_id = $faculty_id AND event_date >= CURDATE()")->fetch_assoc()['count'];
$active_notices = $conn->query("SELECT COUNT(*) as count FROM notices WHERE faculty_id = $faculty_id AND is_active = 1")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Events - Faculty Portal</title>
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

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .event-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            border-top: 4px solid var(--primary);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .event-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            position: relative;
        }

        .event-status {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .event-date {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .event-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            line-height: 1.4;
        }

        .event-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .event-description {
            color: var(--gray);
            font-size: 14px;
            line-height: 1.6;
            flex: 1;
        }

        .event-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
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
            min-width: 100px;
        }

        .detail-value {
            color: var(--gray);
        }

        .event-footer {
            padding: 15px 20px;
            background: var(--light);
            border-top: 1px solid var(--gray-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .event-author {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--gray);
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

        /* No Events */
        .no-events {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .no-events i {
            font-size: 48px;
            color: var(--gray-light);
            margin-bottom: 20px;
        }

        .no-events h3 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-events p {
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
            
            .events-grid {
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
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                padding: 0 15px;
            }
            
            .event-card {
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
            <h1><i class="fas fa-calendar-alt"></i> View All Events</h1>
            <p>Browse through all events in the system. You can filter and search for specific events.</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="search"><i class="fas fa-search"></i> Search</label>
                        <input type="text" id="search" name="search" class="filter-control" 
                               placeholder="Search by title, description, location, or faculty..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status"><i class="fas fa-filter"></i> Status</label>
                        <select id="status" name="status" class="filter-control">
                            <option value="">All Events</option>
                            <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming Events</option>
                            <option value="past" <?php echo $status_filter === 'past' ? 'selected' : ''; ?>>Past Events</option>
                            <option value="today" <?php echo $status_filter === 'today' ? 'selected' : ''; ?>>Today's Events</option>
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
                            <option value="event_date" <?php echo $sort_by === 'event_date' ? 'selected' : ''; ?>>Date (Newest First)</option>
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title (A-Z)</option>
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Created Date</option>
                            <option value="faculty_name" <?php echo $sort_by === 'faculty_name' ? 'selected' : ''; ?>>Faculty Name</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="view_events.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Clear Filters
                    </a>
                    <div style="margin-left: auto; font-size: 14px; color: var(--gray);">
                        <?php echo $total_events; ?> events found
                    </div>
                </div>
            </form>
        </div>

        <!-- Events Grid -->
        <?php if ($total_events > 0): ?>
            <div class="events-grid">
                <?php foreach ($events as $event): 
                    $event_date = new DateTime($event['event_date']);
                    $current_date = new DateTime();
                    $is_upcoming = $event_date >= $current_date;
                    $is_today = $event_date->format('Y-m-d') === $current_date->format('Y-m-d');
                    
                    $status_text = $is_today ? 'Today' : ($is_upcoming ? 'Upcoming' : 'Past');
                    $status_color = $is_today ? 'var(--warning)' : ($is_upcoming ? 'var(--success)' : 'var(--gray)');
                ?>
                    <div class="event-card">
                        <div class="event-header">
                            <div class="event-date">
                                <i class="far fa-calendar"></i>
                                <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                            </div>
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <div class="event-status" style="background: <?php echo $status_color; ?>;">
                                <?php echo $status_text; ?>
                            </div>
                        </div>
                        
                        <div class="event-body">
                            <?php if (!empty($event['description'])): ?>
                                <div class="event-description">
                                    <?php echo nl2br(htmlspecialchars(substr($event['description'], 0, 150))); ?>
                                    <?php if (strlen($event['description']) > 150): ?>...<?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="event-details">
                                <?php if (!empty($event['location'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="detail-label">Location:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($event['start_time']) && !empty($event['end_time'])): ?>
                                    <div class="detail-item">
                                        <i class="far fa-clock"></i>
                                        <span class="detail-label">Time:</span>
                                        <span class="detail-value">
                                            <?php echo date('h:i A', strtotime($event['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($event['end_time'])); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($event['event_type'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-tag"></i>
                                        <span class="detail-label">Type:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($event['event_type']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($event['target_audience'])): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-users"></i>
                                        <span class="detail-label">Audience:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($event['target_audience']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="event-footer">
                            <div class="event-author">
                                <div class="author-avatar">
                                    <?php echo strtoupper(substr($event['faculty_name'] ?? 'F', 0, 1)); ?>
                                </div>
                                <div>
                                    <div><?php echo htmlspecialchars($event['faculty_name'] ?? 'Unknown Faculty'); ?></div>
                                    <small><?php echo htmlspecialchars($event['department'] ?? 'Unknown Department'); ?></small>
                                </div>
                            </div>
                            <div style="font-size: 12px; color: var(--gray);">
                                Created: <?php echo date('M d, Y', strtotime($event['created_at'])); ?>
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
            <div class="no-events">
                <i class="far fa-calendar-times"></i>
                <h3>No Events Found</h3>
                <p>No events match your search criteria. Try adjusting your filters or check back later.</p>
                <a href="view_events.php" class="btn btn-primary">
                    <i class="fas fa-redo"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="footer">
            <p>
                <i class="fas fa-info-circle"></i> 
                This is a view-only page. You can browse events but cannot edit or add new ones.
            </p>
            <p>&copy; <?php echo date('Y'); ?> University Portal - Faculty View | Showing <?php echo $total_events; ?> events</p>
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