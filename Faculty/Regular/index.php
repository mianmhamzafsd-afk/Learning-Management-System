<?php
session_start();

// Check if user is logged in and is faculty
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

// Verify this is a regular faculty (not focal person)
if (isset($_SESSION['is_focal_person']) && $_SESSION['is_focal_person'] == 1) {
    header("Location: ../Focal/index.php");
    exit();
}

// Database connection
$db_paths = [
    'db_connect.php',
    '../db_connect.php',
    '../../db_connect.php',
    '../../../db_connect.php'
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
$faculty_name = $_SESSION['name'] ?? 'Faculty Member';
$department = $_SESSION['department'] ?? 'Department';

// Get faculty details
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ?";
$faculty_stmt = $conn->prepare($faculty_query);
$faculty_stmt->bind_param("i", $faculty_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
$faculty_data = $faculty_result->fetch_assoc();
$faculty_stmt->close();

if (!$faculty_data) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Get counts for dashboard
$upcoming_events = $conn->query("SELECT COUNT(*) as count FROM events WHERE faculty_id = $faculty_id AND event_date >= CURDATE()")->fetch_assoc()['count'];
$active_notices = $conn->query("SELECT COUNT(*) as count FROM notices WHERE faculty_id = $faculty_id AND is_active = 1")->fetch_assoc()['count'];
$my_news = $conn->query("SELECT COUNT(*) as count FROM news_updates WHERE faculty_id = $faculty_id")->fetch_assoc()['count'];

// Get recent activities
$recent_events = $conn->query("SELECT title, event_date FROM events WHERE faculty_id = $faculty_id ORDER BY event_date DESC LIMIT 3");
$recent_notices = $conn->query("SELECT title, created_at FROM notices WHERE faculty_id = $faculty_id ORDER BY created_at DESC LIMIT 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard | University Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-purple: #8b5cf6;
            --primary-purple-dark: #7c3aed;
            --secondary-purple: #a78bfa;
            --light-purple: #ede9fe;
            --dark-purple: #5b21b6;
            --accent-purple: #c4b5fd;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f9fafb;
            --gray: #6b7280;
            --gray-light: #e5e7eb;
            --border-radius: 16px;
            --border-radius-sm: 12px;
            --shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.1);
            --shadow-lg: 0 20px 40px -10px rgba(139, 92, 246, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient: linear-gradient(135deg, var(--primary-purple) 0%, var(--dark-purple) 100%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        /* Header */
        .header {
            background: var(--gradient);
            padding: 1.2rem 2rem;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.5rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            color: white;
            transition: var(--transition);
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .avatar {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-purple);
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .user-details {
            text-align: right;
        }

        .user-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: white;
            margin: 0;
        }

        .user-details p {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.9);
            margin: 0.25rem 0 0 0;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary-purple);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.25rem;
            display: inline-block;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: var(--danger);
            transform: rotate(90deg);
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--primary-purple);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.5;
        }

        .welcome-section h1 {
            color: var(--dark);
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .welcome-section p {
            color: var(--gray);
            font-size: 1.1rem;
            line-height: 1.6;
            max-width: 600px;
        }

        /* Statistics */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--gray-light);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-purple);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.75rem;
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.2);
        }

        .stat-info h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark);
            line-height: 1;
        }

        .stat-info p {
            color: var(--gray);
            font-size: 1rem;
            margin: 0;
        }

        /* Profile Section */
        .profile-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .profile-section h2 {
            color: var(--dark);
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .profile-section h2 i {
            color: var(--primary-purple);
        }

        .profile-card {
            background: var(--light-purple);
            border-radius: var(--border-radius);
            padding: 2rem;
            border: 1px solid var(--accent-purple);
        }

        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(139, 92, 246, 0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .label {
            color: var(--dark);
            font-weight: 500;
            font-size: 0.95rem;
            min-width: 150px;
        }

        .value {
            color: var(--dark);
            font-weight: 500;
            text-align: right;
            max-width: 200px;
            word-break: break-word;
            flex: 1;
            margin-left: 1rem;
        }

        .value.dept {
            background: var(--primary-purple);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .value.desig {
            background: rgba(139, 92, 246, 0.1);
            color: var(--primary-purple);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid var(--accent-purple);
            display: inline-block;
        }

        /* View Sections */
        .view-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 992px) {
            .view-sections {
                grid-template-columns: 1fr;
            }
        }

        .view-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }

        .view-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .view-header {
            padding: 1.5rem;
            background: var(--gradient);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .view-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .view-header h3 i {
            font-size: 1.1rem;
        }

        .view-all {
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .view-all:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .view-content {
            padding: 1.5rem;
            flex: 1;
            max-height: 300px;
            overflow-y: auto;
        }

        /* Lists */
        .event-list, .notice-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .event-item, .notice-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            background: var(--light-purple);
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .event-item:hover, .notice-item:hover {
            background: white;
            border-color: var(--primary-purple);
            transform: translateX(5px);
        }

        .event-icon, .notice-icon {
            width: 50px;
            height: 50px;
            background: var(--gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .event-details h4, .notice-details h4 {
            font-size: 1.05rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .event-details p, .notice-details p {
            font-size: 0.9rem;
            color: var(--gray);
            margin: 0;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray);
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
            color: var(--primary-purple);
        }

        .no-data p {
            font-size: 1rem;
            margin: 0;
            font-style: italic;
        }

        /* Quick Links */
        .quick-links {
            background: white;
            border-radius: var(--border-radius);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .quick-links h2 {
            color: var(--dark);
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .quick-links h2 i {
            color: var(--primary-purple);
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
        }

        .link-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1.5rem;
            background: var(--light-purple);
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .link-card:hover {
            background: white;
            border-color: var(--primary-purple);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.1);
        }

        .link-icon {
            width: 60px;
            height: 60px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.2);
        }

        .link-card span {
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            line-height: 1.4;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 2.5rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            color: var(--gray);
            font-size: 0.95rem;
        }

        .footer p {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .footer i {
            color: var(--primary-purple);
        }

        .copyright {
            font-size: 0.85rem;
            opacity: 0.7;
            margin-top: 1rem;
        }

        /* Scrollbar */
        .view-content::-webkit-scrollbar {
            width: 6px;
        }

        .view-content::-webkit-scrollbar-track {
            background: var(--gray-light);
            border-radius: 3px;
        }

        .view-content::-webkit-scrollbar-thumb {
            background: var(--primary-purple);
            border-radius: 3px;
        }

        .view-content::-webkit-scrollbar-thumb:hover {
            background: var(--primary-purple-dark);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .container {
                padding: 1.5rem;
            }
            
            .profile-info {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .welcome-section h1 {
                font-size: 1.75rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .view-sections {
                grid-template-columns: 1fr;
            }
            
            .links-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .value {
                text-align: left;
                margin-left: 0;
                width: 100%;
            }
            
            .view-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .view-all {
                align-self: flex-end;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 1rem;
            }
            
            .welcome-section,
            .profile-section,
            .quick-links,
            .footer {
                padding: 1.5rem;
            }
            
            .links-grid {
                grid-template-columns: 1fr;
            }
            
            .logo {
                font-size: 1.25rem;
            }
            
            .logo-icon {
                width: 35px;
                height: 35px;
                font-size: 1.1rem;
            }
            
            .user-profile {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .avatar {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <span>Faculty Portal</span>
        </div>
        
        <div class="user-info">
            <div class="user-profile">
                <div class="avatar">
                    <?php echo strtoupper(substr($faculty_name, 0, 2)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($faculty_name); ?></h4>
                    <p><?php echo htmlspecialchars($department); ?></p>
                    <span class="user-badge">View Only Mode</span>
                </div>
            </div>
            <a href="../../login.php?logout=true" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section fade-in">
            <h1><i class="fas fa-home"></i> Welcome, <?php echo htmlspecialchars($faculty_name); ?></h1>
            <p>This is your view-only dashboard. You can view information but cannot edit or add new content.</p>
        </div>

        <!-- Statistics -->
        <div class="stats-container fade-in">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $upcoming_events; ?></h3>
                    <p>Upcoming Events</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $active_notices; ?></h3>
                    <p>Active Notices</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $my_news; ?></h3>
                    <p>My News Posts</p>
                </div>
            </div>
        </div>

        <!-- Profile Information -->
        <div class="profile-section fade-in">
            <h2><i class="fas fa-id-card"></i> My Profile</h2>
            <div class="profile-card">
                <div class="profile-info">
                    <div class="info-row">
                        <span class="label">Full Name:</span>
                        <span class="value"><?php echo htmlspecialchars($faculty_data['name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Username:</span>
                        <span class="value"><?php echo htmlspecialchars($faculty_data['username'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Email:</span>
                        <span class="value"><?php echo htmlspecialchars($faculty_data['email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Phone:</span>
                        <span class="value"><?php echo htmlspecialchars($faculty_data['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Department:</span>
                        <span class="value dept"><?php echo htmlspecialchars($faculty_data['department'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Designation:</span>
                        <span class="value desig"><?php echo htmlspecialchars($faculty_data['designation'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Specialization:</span>
                        <span class="value"><?php echo htmlspecialchars($faculty_data['specialization'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Office Location:</span>
                        <span class="value"><?php echo htmlspecialchars($faculty_data['office_location'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Office Hours:</span>
                        <span class="value"><?php echo htmlspecialchars($faculty_data['office_hours'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">Hire Date:</span>
                        <span class="value"><?php echo date('F j, Y', strtotime($faculty_data['hire_date'] ?? 'N/A')); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Only Sections -->
        <div class="view-sections fade-in">
            <!-- My Events -->
            <div class="view-card">
                <div class="view-header">
                    <h3><i class="fas fa-calendar"></i> My Events</h3>
                    <a href="view_events.php" class="view-all">View All</a>
                </div>
                <div class="view-content">
                    <?php if ($recent_events->num_rows > 0): ?>
                        <div class="event-list">
                            <?php while($event = $recent_events->fetch_assoc()): ?>
                                <div class="event-item">
                                    <div class="event-icon">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div class="event-details">
                                        <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                        <p><?php echo date('F j, Y', strtotime($event['event_date'])); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-calendar-alt"></i>
                            <p>No events found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Notices -->
            <div class="view-card">
                <div class="view-header">
                    <h3><i class="fas fa-clipboard"></i> My Notices</h3>
                    <a href="view_notices.php" class="view-all">View All</a>
                </div>
                <div class="view-content">
                    <?php if ($recent_notices->num_rows > 0): ?>
                        <div class="notice-list">
                            <?php while($notice = $recent_notices->fetch_assoc()): ?>
                                <div class="notice-item">
                                    <div class="notice-icon">
                                        <i class="fas fa-bullhorn"></i>
                                    </div>
                                    <div class="notice-details">
                                        <h4><?php echo htmlspecialchars($notice['title']); ?></h4>
                                        <p><?php echo date('M d, Y', strtotime($notice['created_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-bullhorn"></i>
                            <p>No notices found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="quick-links fade-in">
            <h2><i class="fas fa-link"></i> Quick View Links</h2>
            <div class="links-grid">
                <a href="view_events.php" class="link-card">
                    <div class="link-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <span>View Events</span>
                </a>
                
                <a href="view_notices.php" class="link-card">
                    <div class="link-icon">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <span>View Notices</span>
                </a>
                
                <a href="view_notifications.php" class="link-card">
                    <div class="link-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <span>View Notifications</span>
                </a>
                
                <a href="view_news.php" class="link-card">
                    <div class="link-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <span>View News</span>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer fade-in">
            <p><i class="fas fa-info-circle"></i> This is a view-only dashboard. Contact administrator for edit permissions.</p>
            <p class="copyright">&copy; <?php echo date('Y'); ?> University Portal - Faculty View</p>
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
            
            // Add fade-in animation to all cards
            const cards = document.querySelectorAll('.stat-card, .profile-section, .view-card, .link-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('fade-in');
            });
        });
    </script>
</body>
</html>