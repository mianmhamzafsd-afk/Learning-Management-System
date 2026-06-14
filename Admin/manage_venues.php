<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../db_connect.php');

// Handle form submissions
$message = '';
$message_type = '';

// Add new venue
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_venue'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $capacity = mysqli_real_escape_string($conn, $_POST['capacity']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $amenities = mysqli_real_escape_string($conn, $_POST['amenities']);
    $status = 'active';
    $admin_id = $_SESSION['admin_id'];

    $sql = "INSERT INTO booking_venues (name, type, capacity, location, amenities, status, admin_id) 
            VALUES ('$name', '$type', '$capacity', '$location', '$amenities', '$status', '$admin_id')";
    
    if (mysqli_query($conn, $sql)) {
        $message = "Venue added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding venue: " . mysqli_error($conn);
        $message_type = "danger";
    }
}

// Update venue
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_venue'])) {
    $venue_id = mysqli_real_escape_string($conn, $_POST['venue_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $capacity = mysqli_real_escape_string($conn, $_POST['capacity']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $amenities = mysqli_real_escape_string($conn, $_POST['amenities']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $sql = "UPDATE booking_venues SET 
            name = '$name',
            type = '$type',
            capacity = '$capacity',
            location = '$location',
            amenities = '$amenities',
            status = '$status'
            WHERE venue_id = '$venue_id'";
    
    if (mysqli_query($conn, $sql)) {
        $message = "Venue updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating venue: " . mysqli_error($conn);
        $message_type = "danger";
    }
}

// Delete venue
if (isset($_GET['delete'])) {
    $venue_id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Check if venue has bookings
    $check_sql = "SELECT COUNT(*) as count FROM booking_requests WHERE venue_id = '$venue_id'";
    $check_result = mysqli_query($conn, $check_sql);
    $check_data = mysqli_fetch_assoc($check_result);
    
    if ($check_data['count'] > 0) {
        $message = "Cannot delete venue. It has existing bookings.";
        $message_type = "danger";
    } else {
        $sql = "DELETE FROM booking_venues WHERE venue_id = '$venue_id'";
        if (mysqli_query($conn, $sql)) {
            $message = "Venue deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting venue: " . mysqli_error($conn);
            $message_type = "danger";
        }
    }
}

// Get all venues
$venues_query = "SELECT * FROM booking_venues ORDER BY type, name";
$venues_result = mysqli_query($conn, $venues_query);

// Get venue counts by type
$count_query = "SELECT type, COUNT(*) as count, 
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
                FROM booking_venues 
                GROUP BY type";
$count_result = mysqli_query($conn, $count_query);
$venue_counts = [];
while ($row = mysqli_fetch_assoc($count_result)) {
    $venue_counts[$row['type']] = $row;
}

// Get venue for editing
$edit_venue = null;
if (isset($_GET['edit'])) {
    $edit_id = mysqli_real_escape_string($conn, $_GET['edit']);
    $edit_query = "SELECT * FROM booking_venues WHERE venue_id = '$edit_id'";
    $edit_result = mysqli_query($conn, $edit_query);
    $edit_venue = mysqli_fetch_assoc($edit_result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Venues - MYPR System Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CONSISTENT DASHBOARD STYLING */
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

        .dashboard-menu a.active {
            background: #e8f4ff;
            border-left: 4px solid #1a237e;
            color: #1a237e;
            font-weight: 600;
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
            overflow-y: auto;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h1 {
            color: #1a237e;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: #4fc3f7;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1a237e, #283593);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(26, 35, 126, 0.3);
            background: linear-gradient(135deg, #283593, #1a237e);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #1a237e;
            border: 2px solid #1a237e;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #1a237e;
            color: white;
            transform: translateY(-2px);
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: none;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f44336, #c62828);
            color: white;
        }

        /* Statistics Cards */
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

        .stat-icon.conference {
            background: linear-gradient(135deg, #2196F3, #1565C0);
        }

        .stat-icon.auditorium {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
        }

        .stat-icon.seminar {
            background: linear-gradient(135deg, #FF9800, #EF6C00);
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

        /* Venues List */
        .venues-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .venues-header {
            padding: 20px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .venues-header h2 {
            color: #1a237e;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .venues-body {
            padding: 30px;
        }

        /* Venue Cards Grid */
        .venues-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .venue-card {
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
        }

        .venue-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border-color: #1a237e;
        }

        .venue-type-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .venue-type-badge.conference {
            background: linear-gradient(135deg, #2196F3, #1565C0);
        }

        .venue-type-badge.auditorium {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
        }

        .venue-type-badge.seminar {
            background: linear-gradient(135deg, #FF9800, #EF6C00);
        }

        .venue-content {
            padding: 25px;
        }

        .venue-header {
            margin-bottom: 15px;
        }

        .venue-title {
            font-size: 20px;
            color: #1a237e;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .venue-location {
            color: #666;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .venue-details {
            margin-bottom: 20px;
        }

        .venue-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: #555;
            font-size: 14px;
        }

        .venue-detail i {
            width: 20px;
            color: #1a237e;
        }

        .venue-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #e8f4ff;
            color: #1a237e;
            border: 1px solid #1a237e;
        }

        .btn-edit:hover {
            background: #1a237e;
            color: white;
        }

        .btn-delete {
            background: #ffeaea;
            color: #dc3545;
            border: 1px solid #dc3545;
        }

        .btn-delete:hover {
            background: #dc3545;
            color: white;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-active {
            background: #e8f6ef;
            color: #2E7D32;
        }

        .status-inactive {
            background: #f5f5f5;
            color: #666;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 30px;
        }

        /* Modal Styling */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            color: #1a237e;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .btn-close:hover {
            color: #dc3545;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #1a237e;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #1a237e;
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: #1a237e;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
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
        @media (max-width: 1024px) {
            .venues-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }

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

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .venues-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .dashboard-main {
                padding: 15px;
            }

            .modal-body {
                padding: 20px;
            }

            .venue-actions {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="dashboard-header">
        <div class="header-top">
            <div class="logo-container">
                <h1><i class="fas fa-university"></i> MYPR Admin Dashboard</h1>
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
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="manage_venues.php" class="active">
                    <i class="fas fa-building"></i>
                    <span>Manage Venues</span>
                </a>
                <a href="manage_bookings.php">
                    <i class="fas fa-calendar-check"></i>
                    <span>Booking Requests</span>
                </a>
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
                <a href="../logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="dashboard-main">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-building"></i> Manage Venues</h1>
                    <p style="color: #666; margin-top: 5px;">Add, edit, or delete booking venues in the system</p>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button class="btn-primary" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New Venue
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?>">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon conference">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $venue_counts['conference_room']['count'] ?? 0; ?></h3>
                        <p>Conference Rooms</p>
                        <small>Active: <?php echo $venue_counts['conference_room']['active_count'] ?? 0; ?></small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon auditorium">
                        <i class="fas fa-theater-masks"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $venue_counts['auditorium']['count'] ?? 0; ?></h3>
                        <p>Auditoriums</p>
                        <small>Active: <?php echo $venue_counts['auditorium']['active_count'] ?? 0; ?></small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon seminar">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $venue_counts['seminar_hall']['count'] ?? 0; ?></h3>
                        <p>Seminar Halls</p>
                        <small>Active: <?php echo $venue_counts['seminar_hall']['active_count'] ?? 0; ?></small>
                    </div>
                </div>
            </div>

            <!-- Venues List -->
            <div class="venues-container">
                <div class="venues-header">
                    <h2><i class="fas fa-list"></i> All Venues</h2>
                    <span class="status-badge" style="background: #e8f4ff; color: #1a237e;">
                        Total: <?php echo mysqli_num_rows($venues_result); ?> venues
                    </span>
                </div>
                
                <div class="venues-body">
                    <?php if (mysqli_num_rows($venues_result) > 0): ?>
                        <div class="venues-grid">
                            <?php 
                            // Reset pointer to beginning
                            mysqli_data_seek($venues_result, 0);
                            while ($venue = mysqli_fetch_assoc($venues_result)): 
                                $type_class = '';
                                if ($venue['type'] == 'conference_room') $type_class = 'conference';
                                elseif ($venue['type'] == 'auditorium') $type_class = 'auditorium';
                                else $type_class = 'seminar';
                            ?>
                            <div class="venue-card">
                                <span class="venue-type-badge <?php echo $type_class; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $venue['type'])); ?>
                                </span>
                                
                                <div class="venue-content">
                                    <div class="venue-header">
                                        <h3 class="venue-title"><?php echo htmlspecialchars($venue['name']); ?></h3>
                                        <div class="venue-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($venue['location']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="venue-details">
                                        <div class="venue-detail">
                                            <i class="fas fa-users"></i>
                                            <span>Capacity: <strong><?php echo $venue['capacity']; ?></strong> persons</span>
                                        </div>
                                        
                                        <?php if ($venue['amenities']): ?>
                                        <div class="venue-detail">
                                            <i class="fas fa-star"></i>
                                            <span><?php echo htmlspecialchars($venue['amenities']); ?></span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="venue-detail">
                                            <i class="fas fa-circle"></i>
                                            <span>Status: 
                                                <span class="status-badge <?php echo ($venue['status'] == 'active') ? 'status-active' : 'status-inactive'; ?>">
                                                    <i class="fas fa-<?php echo ($venue['status'] == 'active') ? 'check' : 'pause'; ?>"></i>
                                                    <?php echo ucfirst($venue['status']); ?>
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="venue-actions">
                                        <a href="?edit=<?php echo $venue['venue_id']; ?>" class="btn-action btn-edit">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="?delete=<?php echo $venue['venue_id']; ?>" 
                                           class="btn-action btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this venue?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <h3>No venues found</h3>
                            <p>Add your first venue using the "Add New Venue" button above.</p>
                            <a href="index.php" class="btn-secondary">
                                <i class="fas fa-arrow-left"></i> Return to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Venue Modal -->
    <div class="modal-overlay <?php echo $edit_venue ? 'active' : ''; ?>" id="venueModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-<?php echo $edit_venue ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $edit_venue ? 'Edit Venue' : 'Add New Venue'; ?>
                </h2>
                <button type="button" class="btn-close" onclick="closeModal()">×</button>
            </div>
            
            <form method="POST" action="">
                <div class="modal-body">
                    <?php if ($edit_venue): ?>
                        <input type="hidden" name="venue_id" value="<?php echo $edit_venue['venue_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">Venue Name *</label>
                        <input type="text" class="form-control" name="name" required
                               value="<?php echo $edit_venue ? htmlspecialchars($edit_venue['name']) : ''; ?>"
                               placeholder="Enter venue name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Venue Type *</label>
                        <select class="form-select" name="type" required>
                            <option value="">Select Type</option>
                            <option value="conference_room" <?php echo ($edit_venue && $edit_venue['type'] == 'conference_room') ? 'selected' : ''; ?>>Conference Room</option>
                            <option value="auditorium" <?php echo ($edit_venue && $edit_venue['type'] == 'auditorium') ? 'selected' : ''; ?>>Auditorium</option>
                            <option value="seminar_hall" <?php echo ($edit_venue && $edit_venue['type'] == 'seminar_hall') ? 'selected' : ''; ?>>Seminar Hall</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Capacity *</label>
                            <input type="number" class="form-control" name="capacity" min="1" required
                                   value="<?php echo $edit_venue ? $edit_venue['capacity'] : ''; ?>"
                                   placeholder="Enter capacity">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" <?php echo $edit_venue ? '' : 'disabled'; ?>>
                                <option value="active" <?php echo ($edit_venue && $edit_venue['status'] == 'active') ? 'selected' : 'selected'; ?>>Active</option>
                                <option value="inactive" <?php echo ($edit_venue && $edit_venue['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                            <?php if (!$edit_venue): ?>
                                <input type="hidden" name="status" value="active">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location"
                               value="<?php echo $edit_venue ? htmlspecialchars($edit_venue['location']) : ''; ?>"
                               placeholder="e.g., Main Building, 2nd Floor">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Amenities</label>
                        <textarea class="form-control" name="amenities" rows="3" 
                                  placeholder="e.g., Projector, Whiteboard, WiFi, AC, Sound System"><?php 
                            echo $edit_venue ? htmlspecialchars($edit_venue['amenities']) : ''; 
                        ?></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="<?php echo $edit_venue ? 'update_venue' : 'add_venue'; ?>" 
                            class="btn-primary">
                        <i class="fas fa-<?php echo $edit_venue ? 'save' : 'plus'; ?>"></i>
                        <?php echo $edit_venue ? 'Update Venue' : 'Add Venue'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <div class="dashboard-footer">
        <p><i class="fas fa-copyright"></i> <?php echo date('Y'); ?> MYPR System. All rights reserved.</p>
        <p>Logged in as: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?> | Role: <?php echo ucfirst($_SESSION['role']); ?></p>
    </div>

    <script>
        // Modal Functions
        function openAddModal() {
            document.getElementById('venueModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('venueModal').classList.remove('active');
            // Remove edit parameter from URL if closing edit modal
            if(window.location.href.includes('edit=')) {
                window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/edit=[^&]*&?/, '').replace(/&$/, '').replace(/\?$/, ''));
            }
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('venueModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Auto-open modal if editing
        <?php if ($edit_venue): ?>
            document.addEventListener('DOMContentLoaded', function() {
                openAddModal();
            });
        <?php endif; ?>
        
        // Auto logout warning (consistent with dashboard)
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
                window.location.href = '../logout.php';
            }
        }, 60000); // 1 minute
        
        // Reset idle time on user activity
        document.addEventListener('mousemove', resetIdleTime);
        document.addEventListener('keypress', resetIdleTime);
        document.addEventListener('click', resetIdleTime);
    </script>
</body>
</html>