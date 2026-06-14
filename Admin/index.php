<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include database connection
include('../db_connect.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Learning Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS STYLES FOR ADMIN DASHBOARD */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
        }

        /* Header Styles */
        .dashboard-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .logo-container h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-container h1 i {
            color: #4fc3f7;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, #4fc3f7, #2979ff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .user-details h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .user-details p {
            font-size: 14px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Dashboard Content */
        .dashboard-content {
            display: flex;
            min-height: calc(100vh - 150px);
        }

        /* Sidebar Styles */
        .dashboard-sidebar {
            width: 250px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px 0;
        }

        .sidebar-title {
            padding: 0 20px 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .sidebar-title h3 {
            color: #1a237e;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-menu {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 0 20px;
        }

        .dashboard-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .dashboard-menu a i {
            width: 20px;
            text-align: center;
            color: #1a237e;
        }

        .dashboard-menu a:hover {
            background: #e8f4ff;
            transform: translateX(5px);
            border-left: 4px solid #1a237e;
        }

        .dashboard-menu a.logout {
            color: #dc3545;
            margin-top: 20px;
            background: #ffeaea;
        }

        .dashboard-menu a.logout i {
            color: #dc3545;
        }

        .dashboard-menu a.logout:hover {
            background: #ffdada;
            border-left: 4px solid #dc3545;
        }

        /* Main Content Area */
        .dashboard-main {
            flex: 1;
            padding: 30px;
        }

        .welcome-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .welcome-section h2 {
            color: #1a237e;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-section p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.students {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
        }

        .stat-icon.faculty {
            background: linear-gradient(135deg, #2196F3, #1565C0);
        }

        .stat-icon.admin {
            background: linear-gradient(135deg, #FF9800, #EF6C00);
        }

        .stat-icon.system {
            background: linear-gradient(135deg, #9C27B0, #6A1B9A);
        }

        .stat-info h3 {
            font-size: 32px;
            color: #1a237e;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #666;
            font-size: 14px;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            color: #1a237e;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e8f4ff;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 25px;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }

        .action-btn i {
            font-size: 36px;
            margin-bottom: 15px;
            color: #1a237e;
        }

        .action-btn span {
            font-weight: 500;
            text-align: center;
        }

        .action-btn:hover {
            background: #e8f4ff;
            border-color: #1a237e;
            transform: scale(1.05);
        }

        /* Footer */
        .dashboard-footer {
            background: #1a237e;
            color: white;
            padding: 20px;
            text-align: center;
            margin-top: 30px;
        }

        .dashboard-footer p {
            opacity: 0.9;
            font-size: 14px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-content {
                flex-direction: column;
            }

            .dashboard-sidebar {
                width: 100%;
                margin-bottom: 20px;
            }

            .dashboard-menu {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .dashboard-menu a {
                flex: 1;
                min-width: 150px;
                justify-content: center;
                text-align: center;
            }

            .header-top {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .user-info {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .dashboard-main {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="dashboard-header">
        <div class="header-top">
            <div class="logo-container">
                <h1><i class="fas fa-university"></i> Admin Dashboard</h1>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></h3>
                    <p><i class="fas fa-user-tag"></i> <?php echo ucfirst($_SESSION['role']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <!-- Sidebar Menu -->
        <div class="dashboard-sidebar">
            <div class="sidebar-title">
                <h3><i class="fas fa-bars"></i> Navigation Menu</h3>
            </div>
            <div class="dashboard-menu">
                <a href="add_student.php">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Student</span>
                </a>
                <a href="add_faculty.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Add Faculty</span>
                </a>
                <a href="view_students.php">
                    <i class="fas fa-users"></i>
                    <span>View Students</span>
                </a>
                <a href="view_faculty.php">
                    <i class="fas fa-user-tie"></i>
                    <span>View Faculty</span>
                </a>
                <a href="logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="dashboard-main">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h2><i class="fas fa-graduation-cap"></i> Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?>!</h2>
                <p>Welcome to the Admin Dashboard. Here you can manage students, faculty, and all system operations.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <?php
                // Get statistics
                $student_count = 0;
                $faculty_count = 0;
                
                // Count students
                $sql_students = "SELECT COUNT(*) as count FROM students";
                $result_students = mysqli_query($conn, $sql_students);
                if ($result_students) {
                    $row = mysqli_fetch_assoc($result_students);
                    $student_count = $row['count'];
                }
                
                // Count faculty
                $sql_faculty = "SELECT COUNT(*) as count FROM faculty";
                $result_faculty = mysqli_query($conn, $sql_faculty);
                if ($result_faculty) {
                    $row = mysqli_fetch_assoc($result_faculty);
                    $faculty_count = $row['count'];
                }
                ?>
                
                <div class="stat-card">
                    <div class="stat-icon students">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $student_count; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon faculty">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $faculty_count; ?></h3>
                        <p>Total Faculty</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon admin">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-info">
                        <h3>1</h3>
                        <p>Active Admin</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon system">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $student_count + $faculty_count + 1; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="action-buttons">
                    <a href="add_student.php" class="action-btn">
                        <i class="fas fa-user-plus"></i>
                        <span>Add New Student</span>
                    </a>
                    <a href="add_faculty.php" class="action-btn">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Add New Faculty</span>
                    </a>
                    <a href="view_students.php" class="action-btn">
                        <i class="fas fa-users"></i>
                        <span>Manage Students</span>
                    </a>
                    <a href="view_faculty.php" class="action-btn">
                        <i class="fas fa-user-tie"></i>
                        <span>Manage Faculty</span>
                    </a>
                    <a href="manage_venues.php" class="action-btn">
                        <i class="fas fa-building"></i>
                        <span>Manage Venues</span>
                    </a>
                    <a href="manage_bookings.php" class="action-btn">
                        <i class="fas fa-calendar-check"></i>
                        <span>Booking Requests</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="dashboard-footer">
        <p><i class="fas fa-copyright"></i> <?php echo date('Y'); ?> Learning Management System. All rights reserved.</p>
        <p>Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?> | Role: <?php echo ucfirst($_SESSION['role']); ?></p>
    </div>

    <script>
        // Auto logout warning
        let idleTime = 0;
        
        function resetIdleTime() {
            idleTime = 0;
        }
        
        // Increment idle time every minute
        setInterval(function() {
            idleTime++;
            if (idleTime > 29) { // 30 minutes
                alert('You will be logged out due to inactivity. Please save your work.');
            }
            if (idleTime > 30) { // 31 minutes
                window.location.href = 'logout.php';
            }
        }, 60000); // 1 minute
        
        // Reset idle time on user activity
        document.addEventListener('mousemove', resetIdleTime);
        document.addEventListener('keypress', resetIdleTime);
        document.addEventListener('click', resetIdleTime);
        
        // Confirm logout
        document.querySelector('a.logout').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>