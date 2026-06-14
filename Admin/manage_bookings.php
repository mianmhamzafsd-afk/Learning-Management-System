<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
include('../db_connect.php');

// Handle booking actions
$message = '';
$message_type = '';

// Approve booking
if (isset($_POST['approve_booking'])) {
    $request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
    $admin_response = mysqli_real_escape_string($conn, $_POST['admin_response']);
    
    $sql = "UPDATE booking_requests SET 
            status = 'approved',
            admin_response = '$admin_response',
            responded_at = NOW(),
            admin_id = '{$_SESSION['admin_id']}'
            WHERE request_id = '$request_id'";
    
    if (mysqli_query($conn, $sql)) {
        // Get booking details for notification
        $booking_query = "SELECT b.*, f.name as faculty_name, f.email, v.name as venue_name 
                         FROM booking_requests b
                         JOIN faculty f ON b.faculty_id = f.faculty_id
                         JOIN booking_venues v ON b.venue_id = v.venue_id
                         WHERE b.request_id = '$request_id'";
        $booking_result = mysqli_query($conn, $booking_query);
        $booking_data = mysqli_fetch_assoc($booking_result);
        
        $message = "Booking approved successfully!";
        $message_type = "success";
    } else {
        $message = "Error approving booking: " . mysqli_error($conn);
        $message_type = "danger";
    }
}

// Reject booking
if (isset($_POST['reject_booking'])) {
    $request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
    $admin_response = mysqli_real_escape_string($conn, $_POST['admin_response']);
    
    $sql = "UPDATE booking_requests SET 
            status = 'rejected',
            admin_response = '$admin_response',
            responded_at = NOW(),
            admin_id = '{$_SESSION['admin_id']}'
            WHERE request_id = '$request_id'";
    
    if (mysqli_query($conn, $sql)) {
        $message = "Booking rejected successfully!";
        $message_type = "success";
    } else {
        $message = "Error rejecting booking: " . mysqli_error($conn);
        $message_type = "danger";
    }
}

// Cancel booking (admin can cancel any booking)
if (isset($_POST['cancel_booking'])) {
    $request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
    $admin_response = mysqli_real_escape_string($conn, $_POST['admin_response']);
    
    $sql = "UPDATE booking_requests SET 
            status = 'cancelled',
            admin_response = '$admin_response',
            responded_at = NOW(),
            admin_id = '{$_SESSION['admin_id']}'
            WHERE request_id = '$request_id'";
    
    if (mysqli_query($conn, $sql)) {
        $message = "Booking cancelled successfully!";
        $message_type = "success";
    } else {
        $message = "Error cancelling booking: " . mysqli_error($conn);
        $message_type = "danger";
    }
}

// Filter variables
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_venue = isset($_GET['venue']) ? $_GET['venue'] : '';
$filter_department = isset($_GET['department']) ? $_GET['department'] : '';

// Build query with filters
$query = "SELECT br.*, f.name as faculty_name, f.department, f.email, v.name as venue_name, v.type as venue_type, v.location as venue_location
          FROM booking_requests br
          JOIN faculty f ON br.faculty_id = f.faculty_id
          JOIN booking_venues v ON br.venue_id = v.venue_id
          WHERE 1=1";

if ($filter_status != 'all') {
    $query .= " AND br.status = '$filter_status'";
}
if ($filter_date) {
    $query .= " AND br.booking_date = '$filter_date'";
}
if ($filter_venue) {
    $query .= " AND br.venue_id = '$filter_venue'";
}
if ($filter_department) {
    $query .= " AND f.department = '$filter_department'";
}

$query .= " ORDER BY br.requested_at DESC";
$bookings_result = mysqli_query($conn, $query);

// Get venues for filter
$venues_query = "SELECT * FROM booking_venues WHERE status = 'active' ORDER BY name";
$venues_result = mysqli_query($conn, $venues_query);

// Get departments for filter
$departments_query = "SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_query);

// Get stats
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM booking_requests";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a237e;
            --primary-dark: #0d1552;
            --secondary: #283593;
            --success: #4CAF50;
            --danger: #f44336;
            --warning: #ff9800;
            --info: #2196F3;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
            font-weight: 600;
            color: white;
            text-decoration: none;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header-custom {
            background: white;
            padding: 25px 30px;
            border-bottom: 2px solid #f0f2f5;
        }
        
        .card-header-custom h1 {
            color: var(--primary);
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Status badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-cancelled { background-color: #e2e3e5; color: #383d41; }
        
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .stat-icon.total { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
        .stat-icon.pending { background: linear-gradient(135deg, var(--warning) 0%, #ff9100 100%); }
        .stat-icon.approved { background: linear-gradient(135deg, var(--success) 0%, #2E7D32 100%); }
        .stat-icon.rejected { background: linear-gradient(135deg, var(--danger) 0%, #c62828 100%); }
        
        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #1a237e;
        }
        
        .stat-info p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        
        /* Filter section */
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        .filter-section h5 {
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Booking cards */
        .booking-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .booking-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .booking-card.pending {
            border-left-color: var(--warning);
            background: linear-gradient(to right, #fff9e6 0%, white 5%);
        }
        
        .booking-card.approved {
            border-left-color: var(--success);
            background: linear-gradient(to right, #e8f5e9 0%, white 5%);
        }
        
        .booking-card.rejected {
            border-left-color: var(--danger);
            background: linear-gradient(to right, #ffebee 0%, white 5%);
        }
        
        .booking-card.cancelled {
            border-left-color: #6c757d;
            background: linear-gradient(to right, #f8f9fa 0%, white 5%);
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .booking-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .meta-item i {
            color: var(--primary);
            width: 16px;
        }
        
        .time-slot {
            background: #e3f2fd;
            color: #1565c0;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .venue-type-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .conference_room {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .auditorium {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .seminar_hall {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 8px;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        /* Modal styles */
        .modal-header-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .detail-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 15px;
        }
        
        .detail-value {
            color: #555;
            line-height: 1.6;
        }
        
        .response-textarea {
            min-height: 120px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .booking-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .booking-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .action-buttons {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .main-container {
                padding: 0 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header-custom h1 {
                font-size: 20px;
            }
            
            .btn-sm-icon {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="admin-header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <i class="fas fa-calendar-check"></i>
                <span>Manage Booking Requests</span>
            </a>
            
            <div class="user-info">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 45px; height: 45px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: bold;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></div>
                        <div style="font-size: 12px; opacity: 0.9;">Administrator</div>
                    </div>
                </div>
                <a href="index.php" class="btn btn-sm btn-light ms-3">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <a href="?status=all" class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Bookings</p>
                </div>
            </a>
            
            <a href="?status=pending" class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending Approval</p>
                </div>
            </a>
            
            <a href="?status=approved" class="stat-card">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved']; ?></h3>
                    <p>Approved</p>
                </div>
            </a>
            
            <a href="?status=rejected" class="stat-card">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['rejected']; ?></h3>
                    <p>Rejected</p>
                </div>
            </a>
        </div>
        
        <!-- Dashboard Card -->
        <div class="dashboard-card">
            <div class="card-header-custom">
                <h1><i class="fas fa-tasks"></i> Booking Request Management</h1>
                <p class="mb-0 text-muted">Approve, reject, or manage venue booking requests from faculty</p>
            </div>
            
            <div class="card-body p-4">
                <!-- Filter Section -->
                <div class="filter-section">
                    <h5><i class="fas fa-filter"></i> Filter Requests</h5>
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo ($filter_status == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo ($filter_status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Booking Date</label>
                            <input type="date" class="form-control" name="date" value="<?php echo $filter_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Venue</label>
                            <select class="form-select" name="venue">
                                <option value="">All Venues</option>
                                <?php while ($venue = mysqli_fetch_assoc($venues_result)): ?>
                                    <option value="<?php echo $venue['venue_id']; ?>" 
                                        <?php echo ($filter_venue == $venue['venue_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($venue['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php while ($dept = mysqli_fetch_assoc($departments_result)): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                        <?php echo ($filter_department == $dept['department']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                </button>
                                <a href="manage_bookings.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Clear Filters
                                </a>
                                <div class="ms-auto">
                                    <span class="badge bg-info">
                                        <i class="fas fa-database me-1"></i>
                                        <?php echo mysqli_num_rows($bookings_result); ?> requests found
                                    </span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Bookings List -->
                <div class="bookings-list">
                    <?php if (mysqli_num_rows($bookings_result) > 0): ?>
                        <?php while ($booking = mysqli_fetch_assoc($bookings_result)): 
                            $status_class = strtolower($booking['status']);
                            $type_class = str_replace('_', '-', $booking['venue_type']);
                        ?>
                        <div class="booking-card <?php echo $status_class; ?>">
                            <div class="booking-header">
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($booking['venue_name']); ?></h5>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="venue-type-badge <?php echo $booking['venue_type']; ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $booking['venue_type'])); ?>
                                        </span>
                                        <span class="status-badge status-<?php echo $status_class; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <h6 class="mb-1">Request #<?php echo str_pad($booking['request_id'], 6, '0', STR_PAD_LEFT); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($booking['requested_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="booking-meta">
                                <div class="meta-item">
                                    <i class="fas fa-user-tie"></i>
                                    <span><?php echo htmlspecialchars($booking['faculty_name']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($booking['department']); ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-day"></i>
                                    <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span class="time-slot">
                                        <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-users"></i>
                                    <?php echo $booking['attendees_count']; ?> attendees
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <p class="mb-2">
                                        <strong>Purpose:</strong>
                                        <?php echo htmlspecialchars(substr($booking['purpose'], 0, 150)); ?>
                                        <?php echo strlen($booking['purpose']) > 150 ? '...' : ''; ?>
                                    </p>
                                    
                                    <?php if ($booking['equipment_needed']): ?>
                                        <p class="mb-2">
                                            <strong>Equipment:</strong>
                                            <?php echo htmlspecialchars($booking['equipment_needed']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['admin_response'] && $booking['status'] != 'pending'): ?>
                                        <div class="alert alert-<?php echo $booking['status'] == 'approved' ? 'success' : ($booking['status'] == 'rejected' ? 'danger' : 'secondary'); ?> py-2 px-3 mb-2">
                                            <small>
                                                <strong>Your Response:</strong>
                                                <?php echo htmlspecialchars(substr($booking['admin_response'], 0, 100)); ?>
                                                <?php echo strlen($booking['admin_response']) > 100 ? '...' : ''; ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="action-buttons justify-content-end">
                                        <button type="button" class="btn btn-info btn-sm-icon" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#viewModal<?php echo $booking['request_id']; ?>"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($booking['status'] == 'pending'): ?>
                                            <button type="button" class="btn btn-success btn-sm-icon" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#approveModal<?php echo $booking['request_id']; ?>"
                                                    title="Approve">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm-icon" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#rejectModal<?php echo $booking['request_id']; ?>"
                                                    title="Reject">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php elseif ($booking['status'] == 'approved'): ?>
                                            <button type="button" class="btn btn-warning btn-sm-icon" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#cancelModal<?php echo $booking['request_id']; ?>"
                                                    title="Cancel">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($booking['status'] == 'approved'): ?>
                                            <a href="../Faculty/Focal/booking_pdf.php?id=<?php echo $booking['request_id']; ?>" 
                                               class="btn btn-primary btn-sm-icon"
                                               target="_blank"
                                               title="View PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- View Details Modal -->
                        <div class="modal fade" id="viewModal<?php echo $booking['request_id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header modal-header-custom">
                                        <h5 class="modal-title">Booking Details - #<?php echo str_pad($booking['request_id'], 6, '0', STR_PAD_LEFT); ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="detail-section">
                                                    <div class="detail-label">Venue Information</div>
                                                    <div class="detail-value">
                                                        <strong><?php echo htmlspecialchars($booking['venue_name']); ?></strong><br>
                                                        <span class="venue-type-badge <?php echo $booking['venue_type']; ?>">
                                                            <?php echo ucwords(str_replace('_', ' ', $booking['venue_type'])); ?>
                                                        </span><br>
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($booking['venue_location']); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="detail-section">
                                                    <div class="detail-label">Date & Time</div>
                                                    <div class="detail-value">
                                                        <i class="fas fa-calendar-day me-1"></i>
                                                        <?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?><br>
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                                        <?php echo date('g:i A', strtotime($booking['end_time'])); ?><br>
                                                        <i class="fas fa-hourglass-half me-1"></i>
                                                        <?php 
                                                            $start = strtotime($booking['start_time']);
                                                            $end = strtotime($booking['end_time']);
                                                            $duration = round(($end - $start) / 3600, 1);
                                                            echo $duration . ' hours';
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="detail-section">
                                                    <div class="detail-label">Requester Information</div>
                                                    <div class="detail-value">
                                                        <strong><?php echo htmlspecialchars($booking['faculty_name']); ?></strong><br>
                                                        <i class="fas fa-building me-1"></i>
                                                        <?php echo htmlspecialchars($booking['department']); ?><br>
                                                        <i class="fas fa-envelope me-1"></i>
                                                        <?php echo htmlspecialchars($booking['email']); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="detail-section">
                                                    <div class="detail-label">Booking Status</div>
                                                    <div class="detail-value">
                                                        <span class="status-badge status-<?php echo $status_class; ?>">
                                                            <?php echo ucfirst($booking['status']); ?>
                                                        </span><br>
                                                        <strong>Requested on:</strong>
                                                        <?php echo date('F j, Y g:i A', strtotime($booking['requested_at'])); ?><br>
                                                        <?php if ($booking['responded_at']): ?>
                                                            <strong>Responded on:</strong>
                                                            <?php echo date('F j, Y g:i A', strtotime($booking['responded_at'])); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-section">
                                            <div class="detail-label">Purpose & Requirements</div>
                                            <div class="detail-value">
                                                <strong>Purpose:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($booking['purpose'])); ?>
                                                
                                                <?php if ($booking['equipment_needed']): ?>
                                                    <br><br><strong>Equipment Needed:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($booking['equipment_needed'])); ?>
                                                <?php endif; ?>
                                                
                                                <?php if ($booking['additional_notes']): ?>
                                                    <br><br><strong>Additional Notes:</strong><br>
                                                    <?php echo nl2br(htmlspecialchars($booking['additional_notes'])); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-section">
                                            <div class="detail-label">Attendees</div>
                                            <div class="detail-value">
                                                <i class="fas fa-users me-1"></i>
                                                <?php echo $booking['attendees_count']; ?> people expected
                                            </div>
                                        </div>
                                        
                                        <?php if ($booking['admin_response']): ?>
                                            <div class="detail-section">
                                                <div class="detail-label">Admin Response</div>
                                                <div class="detail-value">
                                                    <div class="alert alert-<?php echo $booking['status'] == 'approved' ? 'success' : ($booking['status'] == 'rejected' ? 'danger' : 'secondary'); ?>">
                                                        <?php echo nl2br(htmlspecialchars($booking['admin_response'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <?php if ($booking['status'] == 'pending'): ?>
                                            <button type="button" class="btn btn-success" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#approveModal<?php echo $booking['request_id']; ?>">
                                                <i class="fas fa-check me-1"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#rejectModal<?php echo $booking['request_id']; ?>">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        <?php elseif ($booking['status'] == 'approved'): ?>
                                            <button type="button" class="btn btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#cancelModal<?php echo $booking['request_id']; ?>">
                                                <i class="fas fa-ban me-1"></i> Cancel Booking
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Approve Modal -->
                        <div class="modal fade" id="approveModal<?php echo $booking['request_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="">
                                        <div class="modal-header modal-header-custom">
                                            <h5 class="modal-title">Approve Booking Request</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-success">
                                                <i class="fas fa-info-circle me-2"></i>
                                                You are about to approve this booking request.
                                            </div>
                                            
                                            <p><strong>Venue:</strong> <?php echo htmlspecialchars($booking['venue_name']); ?></p>
                                            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></p>
                                            <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></p>
                                            <p><strong>Requester:</strong> <?php echo htmlspecialchars($booking['faculty_name']); ?></p>
                                            
                                            <div class="mb-3 mt-4">
                                                <label class="form-label">Optional Message to Requester:</label>
                                                <textarea class="form-control response-textarea" name="admin_response" 
                                                          placeholder="Add any notes, instructions, or congratulations..."></textarea>
                                            </div>
                                            
                                            <input type="hidden" name="request_id" value="<?php echo $booking['request_id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="approve_booking" class="btn btn-success">
                                                <i class="fas fa-check me-1"></i> Approve Booking
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal<?php echo $booking['request_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="">
                                        <div class="modal-header modal-header-custom">
                                            <h5 class="modal-title">Reject Booking Request</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-danger">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                You are about to reject this booking request.
                                            </div>
                                            
                                            <p><strong>Venue:</strong> <?php echo htmlspecialchars($booking['venue_name']); ?></p>
                                            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></p>
                                            <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></p>
                                            <p><strong>Requester:</strong> <?php echo htmlspecialchars($booking['faculty_name']); ?></p>
                                            
                                            <div class="mb-3 mt-4">
                                                <label class="form-label">Reason for Rejection *</label>
                                                <textarea class="form-control response-textarea" name="admin_response" required
                                                          placeholder="Please explain why this booking cannot be approved..."></textarea>
                                                <div class="form-text">This message will be sent to the requester.</div>
                                            </div>
                                            
                                            <input type="hidden" name="request_id" value="<?php echo $booking['request_id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="reject_booking" class="btn btn-danger">
                                                <i class="fas fa-times me-1"></i> Reject Booking
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cancel Modal (for approved bookings) -->
                        <div class="modal fade" id="cancelModal<?php echo $booking['request_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="">
                                        <div class="modal-header modal-header-custom">
                                            <h5 class="modal-title">Cancel Approved Booking</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                You are about to cancel an approved booking. This will free up the time slot for others.
                                            </div>
                                            
                                            <p><strong>Venue:</strong> <?php echo htmlspecialchars($booking['venue_name']); ?></p>
                                            <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></p>
                                            <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></p>
                                            <p><strong>Requester:</strong> <?php echo htmlspecialchars($booking['faculty_name']); ?></p>
                                            
                                            <div class="mb-3 mt-4">
                                                <label class="form-label">Reason for Cancellation *</label>
                                                <textarea class="form-control response-textarea" name="admin_response" required
                                                          placeholder="Please explain why this booking needs to be cancelled..."></textarea>
                                                <div class="form-text">This message will be sent to the requester.</div>
                                            </div>
                                            
                                            <input type="hidden" name="request_id" value="<?php echo $booking['request_id']; ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="cancel_booking" class="btn btn-warning">
                                                <i class="fas fa-ban me-1"></i> Cancel Booking
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4>No booking requests found</h4>
                            <p class="mb-4">
                                <?php echo ($filter_status != 'all' || $filter_date || $filter_venue || $filter_department) ? 
                                    'Try changing your filters.' : 
                                    'No booking requests have been submitted yet.'; ?>
                            </p>
                            <a href="manage_venues.php" class="btn btn-primary">
                                <i class="fas fa-building me-2"></i> Manage Venues
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-focus on modal textareas
        document.addEventListener('DOMContentLoaded', function() {
            var modals = document.querySelectorAll('.modal');
            modals.forEach(function(modal) {
                modal.addEventListener('shown.bs.modal', function() {
                    var textarea = this.querySelector('textarea');
                    if (textarea) {
                        textarea.focus();
                    }
                });
            });
            
            // Set date input max to today
            const dateInput = document.querySelector('input[name="date"]');
            if (dateInput) {
                dateInput.max = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>