<?php
session_start();

// Check if user is logged in as faculty and is focal person
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../../login.php");
    exit();
}

// Try different paths for database connection
$db_paths = [
    '../../db_connect.php',
    '../../../db_connect.php',
    '../db_connect.php',
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

$faculty_id = $_SESSION['faculty_id'] ?? $_SESSION['user_id'] ?? 0;
$faculty_name = $_SESSION['faculty_name'] ?? $_SESSION['user_name'] ?? 'Focal Person';
$department = $_SESSION['department'] ?? 'Department';

// Get faculty details and verify focal person status
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ? AND is_focal_person = 1";
$faculty_stmt = $conn->prepare($faculty_query);
$faculty_stmt->bind_param("i", $faculty_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
$faculty_data = $faculty_result->fetch_assoc();
$faculty_stmt->close();

// If not focal person, redirect to regular faculty dashboard
if (!$faculty_data) {
    header("Location: ../Regular/faculty_dashboard.php");
    exit();
}

// Get counts for dashboard
$total_events = $conn->query("SELECT COUNT(*) as count FROM events WHERE faculty_id = $faculty_id")->fetch_assoc()['count'];
$total_notices = $conn->query("SELECT COUNT(*) as count FROM notices WHERE faculty_id = $faculty_id")->fetch_assoc()['count'];
$total_news = $conn->query("SELECT COUNT(*) as count FROM news_updates WHERE faculty_id = $faculty_id")->fetch_assoc()['count'];
$total_notifications = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE faculty_id = $faculty_id")->fetch_assoc()['count'];

// Get recent activities for the logged-in faculty
$recent_events = $conn->query("SELECT title, event_date FROM events WHERE faculty_id = $faculty_id ORDER BY event_date DESC LIMIT 5");
$recent_notices = $conn->query("SELECT title, created_at FROM notices WHERE faculty_id = $faculty_id ORDER BY created_at DESC LIMIT 5");
$recent_news = $conn->query("SELECT title, created_at FROM news_updates WHERE faculty_id = $faculty_id ORDER BY created_at DESC LIMIT 5");
$recent_notifications = $conn->query("SELECT title, created_at, notification_type as type FROM notifications WHERE faculty_id = $faculty_id ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Focal Person Dashboard - Department Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7209b7;
            --primary-dark: #560bad;
            --secondary: #4361ee;
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
            --transition: all 0.3s ease;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e0c3fc 100%);
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
            width: 45px;
            height: 45px;
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

        .user-details small {
            font-size: 11px;
            color: var(--primary);
            font-weight: 500;
            background: #f3e5f5;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
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
            transition: var(--transition);
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

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .stat-info p {
            color: var(--gray);
            font-size: 14px;
        }

        /* Dashboard Sections */
        .dashboard-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        @media (max-width: 1100px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
        }

        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .view-all {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .view-all:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .card-content {
            padding: 20px;
            max-height: 350px;
            overflow-y: auto;
        }

        /* Lists */
        .events-list, .notices-list, .news-list, .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .event-item, .notice-item, .news-item, .notification-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: var(--light);
            border-radius: var(--border-radius);
            transition: var(--transition);
            cursor: pointer;
        }

        .event-item:hover, .notice-item:hover, .news-item:hover, .notification-item:hover {
            background: #f0f2f5;
            transform: translateX(5px);
        }

        .item-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            flex-shrink: 0;
        }

        .event-item .item-icon {
            background: var(--warning);
        }

        .notice-item .item-icon {
            background: var(--info);
        }

        .news-item .item-icon {
            background: var(--secondary);
        }

        .notification-item .item-icon {
            background: var(--danger);
        }

        .item-details {
            flex: 1;
        }

        .item-details h4 {
            font-size: 15px;
            color: var(--dark);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .item-details p {
            font-size: 13px;
            color: var(--gray);
            margin: 0;
        }

        .item-meta {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
        }

        .quick-actions h2 {
            color: var(--dark);
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .action-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 25px 20px;
            background: var(--light);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .action-card:hover {
            background: white;
            border-color: var(--primary);
            transform: translateY(-5px);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            color: white;
            font-size: 20px;
        }

        .action-card span {
            font-size: 14px;
            font-weight: 500;
            text-align: center;
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

        /* Scrollbar */
        .card-content::-webkit-scrollbar {
            width: 6px;
        }

        .card-content::-webkit-scrollbar-track {
            background: var(--gray-light);
            border-radius: 3px;
        }

        .card-content::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }

        .card-content::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                padding: 0 15px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header h1 {
                font-size: 20px;
            }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state p {
            font-size: 14px;
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <i class="fas fa-user-shield"></i>
                <span>Focal Person Portal</span>
            </a>
            
            <div class="user-info">
                <div class="avatar">
                    <?php echo strtoupper(substr($faculty_name, 0, 2)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($faculty_name); ?></h4>
                    <p><?php echo htmlspecialchars($department); ?> Department</p>
                    <small>Focal Person</small>
                </div>
                <a href="../../login.php?logout=true" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-tachometer-alt"></i> Focal Person Dashboard</h1>
            <p>Manage and oversee all department events, notices, news updates, and notifications.</p>
        </div>

        <!-- Statistics -->
        <div class="stats-container">
            <a href="view_events.php" style="text-decoration: none; color: inherit;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #ff9100 100%);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_events; ?></h3>
                        <p>Events Created</p>
                    </div>
                </div>
            </a>
            
            <a href="view_notices.php" style="text-decoration: none; color: inherit;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--info) 0%, #00b4d8 100%);">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_notices; ?></h3>
                        <p>Notices Posted</p>
                    </div>
                </div>
            </a>
            
            <a href="view_news.php" style="text-decoration: none; color: inherit;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--secondary) 0%, #3a56d4 100%);">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_news; ?></h3>
                        <p>News Updates</p>
                    </div>
                </div>
            </a>
            
            <a href="view_notifications.php" style="text-decoration: none; color: inherit;">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--danger) 0%, #ff006e 100%);">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_notifications; ?></h3>
                        <p>Notifications Sent</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Dashboard Sections -->
        <div class="dashboard-sections">
            <!-- Events Section -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Recent Events</h3>
                    <a href="view_events.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <?php if ($recent_events->num_rows > 0): ?>
                        <div class="events-list">
                            <?php while($event = $recent_events->fetch_assoc()): ?>
                                <a href="view_events.php" style="text-decoration: none; color: inherit;">
                                    <div class="event-item">
                                        <div class="item-icon">
                                            <i class="fas fa-calendar-day"></i>
                                        </div>
                                        <div class="item-details">
                                            <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                            <div class="item-meta">
                                                Date: <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <p>No events found. Create your first event!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notices Section -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-bullhorn"></i> Recent Notices</h3>
                    <a href="view_notices.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <?php if ($recent_notices->num_rows > 0): ?>
                        <div class="notices-list">
                            <?php while($notice = $recent_notices->fetch_assoc()): ?>
                                <a href="view_notices.php" style="text-decoration: none; color: inherit;">
                                    <div class="notice-item">
                                        <div class="item-icon">
                                            <i class="fas fa-clipboard"></i>
                                        </div>
                                        <div class="item-details">
                                            <h4><?php echo htmlspecialchars($notice['title']); ?></h4>
                                            <div class="item-meta">
                                                Posted: <?php echo date('M d, Y', strtotime($notice['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <p>No notices found. Post your first notice!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- News Section -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-newspaper"></i> Recent News</h3>
                    <a href="view_news.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <?php if ($recent_news->num_rows > 0): ?>
                        <div class="news-list">
                            <?php while($news = $recent_news->fetch_assoc()): ?>
                                <a href="view_news.php" style="text-decoration: none; color: inherit;">
                                    <div class="news-item">
                                        <div class="item-icon">
                                            <i class="fas fa-newspaper"></i>
                                        </div>
                                        <div class="item-details">
                                            <h4><?php echo htmlspecialchars($news['title']); ?></h4>
                                            <div class="item-meta">
                                                Posted: <?php echo date('M d, Y', strtotime($news['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-newspaper"></i>
                            <p>No news updates found. Add your first news!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications Section -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> Recent Notifications</h3>
                    <a href="view_notifications.php" class="view-all">View All</a>
                </div>
                <div class="card-content">
                    <?php if ($recent_notifications->num_rows > 0): ?>
                        <div class="notifications-list">
                            <?php while($notification = $recent_notifications->fetch_assoc()): ?>
                                <a href="view_notifications.php" style="text-decoration: none; color: inherit;">
                                    <div class="notification-item">
                                        <div class="item-icon">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div class="item-details">
                                            <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                            <p>Type: <?php echo htmlspecialchars($notification['type']); ?></p>
                                            <div class="item-meta">
                                                Sent: <?php echo date('M d, Y', strtotime($notification['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <p>No notifications found. Send your first notification!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="actions-grid">
                <a href="add_event.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <span>Add Event</span>
                </a>
                
                <a href="add_notice.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <span>Add Notice</span>
                </a>
                
                <a href="add_news.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <span>Add News</span>
                </a>
                
                <a href="add_notification.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <span>Add Notification</span>
                </a>
                
                <a href="view_events.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <span>View Events</span>
                </a>
                
                <a href="view_notices.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <span>View Notices</span>
                </a>
                
                <a href="view_news.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <span>View News</span>
                </a>
                
                <a href="view_notifications.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <span>View Notifications</span>
                </a>
                <a href="all_bookings.php" class="action-card">
                    <div class="action-icon">
                    <i class="fas fa-calendar-alt"></i>
                    </div>
                    <span>View All Bookings</span>
                </a>

                <a href="book_venue.php" class="action-card">
                    <div class="action-icon">
                    <i class="fas fa-calendar-plus"></i>
                    </div>
                    <span>Book Venue</span>
                </a>
                <a href="my_bookings.php" class="action-card">
                    <div class="action-icon">
                    <i class="fas fa-list-alt"></i>
                    </div>
                    <span>My Bookings</span>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>
                <i class="fas fa-info-circle"></i> 
                Focal Person Dashboard - You have administrative privileges for the <?php echo htmlspecialchars($department); ?> department.
            </p>
            <p>&copy; <?php echo date('Y'); ?> University Portal - Focal Person View</p>
        </footer>
    </div>

    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            const timeElement = document.querySelector('.time-display');
            if (timeElement) {
                timeElement.textContent = now.toLocaleDateString('en-US', options);
            }
        }
        
        // Update every minute
        updateTime();
        setInterval(updateTime, 60000);
        
        // Add time display to header if needed
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.querySelector('.header');
            if (header) {
                const timeDisplay = document.createElement('div');
                timeDisplay.className = 'time-display';
                timeDisplay.innerHTML = '<i class="fas fa-clock"></i> <span id="currentTime"></span>';
                header.appendChild(timeDisplay);
                updateTime();
            }
        });

        // Make stat cards clickable
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('click', function() {
                    const link = this.closest('a');
                    if (link) {
                        window.location.href = link.href;
                    }
                });
            });
        });
    </script>
</body>
</html>