<?php
session_start();

// Check if user is logged in as faculty and is focal person
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../../login.php");
    exit();
}

// Database connection
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
$faculty_name = $_SESSION['faculty_name'] ?? $_SESSION['user_name'] ?? 'Faculty';

// Verify focal person status
$faculty_query = "SELECT faculty_id, name, department, is_focal_person FROM faculty WHERE faculty_id = ? AND is_focal_person = 1";
$faculty_stmt = $conn->prepare($faculty_query);
$faculty_stmt->bind_param("i", $faculty_id);
$faculty_stmt->execute();
$faculty_stmt->store_result();

if ($faculty_stmt->num_rows == 0) {
    $faculty_stmt->close();
    header("Location: ../Regular/faculty_dashboard.php");
    exit();
}

// Bind result variables
$faculty_stmt->bind_result($db_faculty_id, $db_faculty_name, $db_department, $db_is_focal_person);
$faculty_stmt->fetch();
$faculty_stmt->close();

// Update session variables with actual database values
if (!isset($_SESSION['faculty_name']) || $_SESSION['faculty_name'] != $db_faculty_name) {
    $_SESSION['faculty_name'] = $db_faculty_name;
}
if (!isset($_SESSION['department']) || $_SESSION['department'] != $db_department) {
    $_SESSION['department'] = $db_department;
}
if (!isset($_SESSION['faculty_id']) || $_SESSION['faculty_id'] != $db_faculty_id) {
    $_SESSION['faculty_id'] = $db_faculty_id;
}

// Update local variables
$faculty_name = $db_faculty_name;
$department = $db_department;

// Handle actions
$message = '';
$message_type = '';

// Cancel booking request
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $request_id = mysqli_real_escape_string($conn, $_GET['cancel']);
    
    // Check if booking belongs to this faculty and is still pending
    $check_query = "SELECT status FROM booking_requests WHERE request_id = '$request_id' AND faculty_id = '$faculty_id'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $booking = mysqli_fetch_assoc($check_result);
        
        if ($booking['status'] == 'pending') {
            $cancel_query = "UPDATE booking_requests SET status = 'cancelled' WHERE request_id = '$request_id'";
            if (mysqli_query($conn, $cancel_query)) {
                $message = "Booking request cancelled successfully!";
                $message_type = "success";
            } else {
                $message = "Error cancelling booking: " . mysqli_error($conn);
                $message_type = "danger";
            }
        } else {
            $message = "Only pending bookings can be cancelled!";
            $message_type = "warning";
        }
    } else {
        $message = "Booking not found or you don't have permission to cancel it!";
        $message_type = "danger";
    }
}

// Filter variables
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_venue = isset($_GET['venue']) ? $_GET['venue'] : '';

// Build query with filters
$query = "SELECT br.*, v.name as venue_name, v.type as venue_type, v.location as venue_location
          FROM booking_requests br
          JOIN booking_venues v ON br.venue_id = v.venue_id
          WHERE br.faculty_id = '$faculty_id'";

if ($filter_status != 'all') {
    $query .= " AND br.status = '$filter_status'";
}
if ($filter_date_from) {
    $query .= " AND br.booking_date >= '$filter_date_from'";
}
if ($filter_date_to) {
    $query .= " AND br.booking_date <= '$filter_date_to'";
}
if ($filter_venue) {
    $query .= " AND br.venue_id = '$filter_venue'";
}

$query .= " ORDER BY br.booking_date DESC, br.start_time DESC";
$bookings_result = mysqli_query($conn, $query);

// Get venues for filter
$venues_query = "SELECT * FROM booking_venues WHERE status = 'active' ORDER BY name";
$venues_result = mysqli_query($conn, $venues_query);

// Get stats for this faculty
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM booking_requests 
                WHERE faculty_id = '$faculty_id'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Focal Person Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7209b7;
            --primary-dark: #560bad;
            --secondary: #4361ee;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #ff9e00;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e0c3fc 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .card-header-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 25px 30px;
            border-bottom: none;
        }
        
        .card-header-custom h2 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Status badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-approved {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-cancelled {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        /* Booking cards */
        .booking-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }
        
        .booking-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .booking-item.pending {
            border-left-color: var(--warning);
        }
        
        .booking-item.approved {
            border-left-color: var(--success);
        }
        
        .booking-item.rejected {
            border-left-color: var(--danger);
        }
        
        .booking-item.cancelled {
            border-left-color: #6c757d;
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
        
        .time-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .venue-type-badge {
            padding: 3px 10px;
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
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-sm-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        
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
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stat-info p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        
        /* Filter section */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .filter-section h5 {
            margin-bottom: 15px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
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
        .booking-details-modal .detail-row {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .booking-details-modal .detail-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .booking-details-modal .detail-value {
            color: #666;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .booking-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
        }
        
        @media (max-width: 576px) {
            .main-container {
                padding: 0 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header-custom h2 {
                font-size: 20px;
            }
        }
        
        .btn-custom {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s;
            color: white;
            border-radius: 8px;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(114, 9, 183, 0.3);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <i class="fas fa-calendar-check"></i>
                <span>My Bookings</span>
            </a>
            
            <div class="user-info">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                        <?php echo strtoupper(substr($faculty_name, 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($faculty_name); ?></div>
                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($department); ?></div>
                    </div>
                </div>
                <a href="index.php" class="btn btn-sm btn-outline-primary ms-3">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="dashboard-card">
            <div class="card-header-custom">
                <h2><i class="fas fa-calendar-alt"></i> My Booking Requests</h2>
                <p class="mb-0 opacity-75">View and manage all your venue booking requests</p>
            </div>
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <a href="?status=all" class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-list-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total'] ?? 0; ?></h3>
                            <p>Total Bookings</p>
                        </div>
                    </a>
                    
                    <a href="?status=pending" class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #ff9100 100%);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                            <p>Pending</p>
                        </div>
                    </a>
                    
                    <a href="?status=approved" class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--success) 0%, #00b4d8 100%);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['approved'] ?? 0; ?></h3>
                            <p>Approved</p>
                        </div>
                    </a>
                    
                    <a href="?status=rejected" class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--danger) 0%, #ff006e 100%);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['rejected'] ?? 0; ?></h3>
                            <p>Rejected</p>
                        </div>
                    </a>
                </div>
                
                <!-- Quick Actions -->
                <div class="d-flex gap-3 mb-4">
                    <a href="book_venue.php" class="btn btn-custom">
                        <i class="fas fa-plus me-2"></i> New Booking Request
                    </a>
                    
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <h5><i class="fas fa-filter"></i> Filter Bookings</h5>
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
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo $filter_date_from; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo $filter_date_to; ?>">
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
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Apply Filters
                                </button>
                                <a href="my_bookings.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Clear Filters
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Bookings List -->
                <div class="bookings-list">
                    <?php if (mysqli_num_rows($bookings_result) > 0): ?>
                        <div class="row">
                            <?php while ($booking = mysqli_fetch_assoc($bookings_result)): 
                                $status_class = strtolower($booking['status']);
                                $type_class = str_replace('_', '-', $booking['venue_type']);
                            ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="booking-item <?php echo $status_class; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($booking['venue_name']); ?></h5>
                                            <span class="venue-type-badge <?php echo $booking['venue_type']; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $booking['venue_type'])); ?>
                                            </span>
                                        </div>
                                        <span class="status-badge status-<?php echo $status_class; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="booking-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-calendar-day"></i>
                                            <?php echo date('M d, Y', strtotime($booking['booking_date'])); ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-clock"></i>
                                            <span class="time-badge">
                                                <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                            </span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-users"></i>
                                            <?php echo $booking['attendees_count']; ?> attendees
                                        </div>
                                    </div>
                                    
                                    <p class="mb-3">
                                        <strong>Purpose:</strong><br>
                                        <?php echo htmlspecialchars(substr($booking['purpose'], 0, 100)); ?>
                                        <?php echo strlen($booking['purpose']) > 100 ? '...' : ''; ?>
                                    </p>
                                    
                                    <?php if ($booking['admin_response'] && $booking['status'] != 'pending'): ?>
                                        <div class="alert alert-<?php echo $booking['status'] == 'approved' ? 'success' : 'danger'; ?> py-2 px-3 mb-3">
                                            <small>
                                                <strong>Admin Response:</strong><br>
                                                <?php echo htmlspecialchars($booking['admin_response']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Requested: <?php echo date('M d, Y', strtotime($booking['requested_at'])); ?>
                                        </small>
                                        
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-info btn-sm-icon" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal<?php echo $booking['request_id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($booking['status'] == 'pending'): ?>
                                                <a href="?cancel=<?php echo $booking['request_id']; ?>" 
                                                   class="btn btn-sm btn-danger btn-sm-icon"
                                                   onclick="return confirm('Are you sure you want to cancel this booking request?')"
                                                   title="Cancel Request">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['status'] == 'approved'): ?>
                                                <a href="booking_pdf.php?id=<?php echo $booking['request_id']; ?>" 
                                                   class="btn btn-sm btn-success btn-sm-icon"
                                                   target="_blank"
                                                   title="Download PDF">
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
                                        <div class="modal-header">
                                            <h5 class="modal-title">Booking Details - #<?php echo str_pad($booking['request_id'], 6, '0', STR_PAD_LEFT); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body booking-details-modal">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="detail-row">
                                                        <div class="detail-label">Venue Information</div>
                                                        <div class="detail-value">
                                                            <strong><?php echo htmlspecialchars($booking['venue_name']); ?></strong><br>
                                                            <span class="venue-type-badge <?php echo $booking['venue_type']; ?>">
                                                                <?php echo ucwords(str_replace('_', ' ', $booking['venue_type'])); ?>
                                                            </span><br>
                                                            <?php echo htmlspecialchars($booking['venue_location']); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="detail-row">
                                                        <div class="detail-label">Date & Time</div>
                                                        <div class="detail-value">
                                                            <i class="fas fa-calendar-day me-1"></i>
                                                            <?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?><br>
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                                            <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="detail-row">
                                                        <div class="detail-label">Attendees & Equipment</div>
                                                        <div class="detail-value">
                                                            <i class="fas fa-users me-1"></i>
                                                            <?php echo $booking['attendees_count']; ?> attendees<br>
                                                            <?php if ($booking['equipment_needed']): ?>
                                                                <i class="fas fa-tools me-1"></i>
                                                                <?php echo htmlspecialchars($booking['equipment_needed']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-md-6">
                                                    <div class="detail-row">
                                                        <div class="detail-label">Request Details</div>
                                                        <div class="detail-value">
                                                            <strong>Status:</strong>
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
                                                    
                                                    <div class="detail-row">
                                                        <div class="detail-label">Purpose</div>
                                                        <div class="detail-value">
                                                            <?php echo nl2br(htmlspecialchars($booking['purpose'])); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($booking['additional_notes']): ?>
                                                        <div class="detail-row">
                                                            <div class="detail-label">Additional Notes</div>
                                                            <div class="detail-value">
                                                                <?php echo nl2br(htmlspecialchars($booking['additional_notes'])); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($booking['admin_response']): ?>
                                                        <div class="detail-row">
                                                            <div class="detail-label">Admin Response</div>
                                                            <div class="detail-value">
                                                                <div class="alert alert-<?php echo $booking['status'] == 'approved' ? 'success' : 'danger'; ?> mb-0">
                                                                    <?php echo nl2br(htmlspecialchars($booking['admin_response'])); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <?php if ($booking['status'] == 'pending'): ?>
                                                <a href="?cancel=<?php echo $booking['request_id']; ?>" 
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Are you sure you want to cancel this booking request?')">
                                                    <i class="fas fa-times me-1"></i> Cancel Request
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($booking['status'] == 'approved'): ?>
                                                <a href="booking_pdf.php?id=<?php echo $booking['request_id']; ?>" 
                                                   class="btn btn-success"
                                                   target="_blank">
                                                    <i class="fas fa-file-pdf me-1"></i> Download PDF
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4>No booking requests found</h4>
                            <p class="mb-4">
                                <?php echo ($filter_status != 'all' || $filter_date_from || $filter_date_to || $filter_venue) ? 
                                    'Try changing your filters.' : 
                                    'You haven\'t made any booking requests yet.'; ?>
                            </p>
                            <a href="book_venue.php" class="btn btn-custom">
                                <i class="fas fa-plus me-2"></i> Make Your First Booking
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
        // Auto-focus on modal inputs
        document.addEventListener('DOMContentLoaded', function() {
            // Set date inputs max to today
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="date_from"]').max = today;
            document.querySelector('input[name="date_to"]').max = today;
            
            // Set date_to min to date_from
            const dateFrom = document.querySelector('input[name="date_from"]');
            const dateTo = document.querySelector('input[name="date_to"]');
            
            dateFrom.addEventListener('change', function() {
                dateTo.min = this.value;
            });
        });
    </script>
</body>
</html>