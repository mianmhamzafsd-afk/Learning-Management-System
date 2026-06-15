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
$department = $_SESSION['department'] ?? 'Department';

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
$_SESSION['faculty_name'] = $db_faculty_name;
$_SESSION['department'] = $db_department;
$_SESSION['faculty_id'] = $db_faculty_id;

// Update local variables
$faculty_name = $db_faculty_name;
$department = $db_department;
$faculty_id = $db_faculty_id;

// Filter variables
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_venue = isset($_GET['venue']) ? $_GET['venue'] : '';
$filter_department = isset($_GET['dept']) ? $_GET['dept'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query - show all bookings (including rejected and cancelled)
$query = "SELECT br.*, 
                 f.name as faculty_name, 
                 f.department as faculty_dept, 
                 f.email as faculty_email,
                 f.phone as faculty_phone,
                 v.name as venue_name, 
                 v.type as venue_type, 
                 v.location as venue_location,
                 v.capacity as venue_capacity,
                 v.amenities as venue_amenities
          FROM booking_requests br
          JOIN faculty f ON br.faculty_id = f.faculty_id
          JOIN booking_venues v ON br.venue_id = v.venue_id
          WHERE 1=1";

// Add filters
if ($filter_date) {
    $query .= " AND br.booking_date = '$filter_date'";
}
if ($filter_venue) {
    $query .= " AND br.venue_id = '$filter_venue'";
}
if ($filter_department) {
    $query .= " AND f.department = '$filter_department'";
}
if ($filter_status && $filter_status != 'all') {
    $query .= " AND br.status = '$filter_status'";
} else {
    // Default: show all except cancelled (if you want to hide cancelled)
    // $query .= " AND br.status != 'cancelled'";
}

$query .= " ORDER BY br.booking_date DESC, br.start_time DESC";
$bookings_result = mysqli_query($conn, $query);

// Get counts for each status
$status_counts = [
    'all' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'cancelled' => 0
];

// Count total
$count_query = "SELECT COUNT(*) as total FROM booking_requests";
$count_result = mysqli_query($conn, $count_query);
$status_counts['all'] = mysqli_fetch_assoc($count_result)['total'];

// Count by status
$status_query = "SELECT status, COUNT(*) as count FROM booking_requests GROUP BY status";
$status_result = mysqli_query($conn, $status_query);
while ($row = mysqli_fetch_assoc($status_result)) {
    $status_counts[$row['status']] = $row['count'];
}

// Get venues for filter
$venues_query = "SELECT * FROM booking_venues WHERE status = 'active' ORDER BY name";
$venues_result = mysqli_query($conn, $venues_query);

// Get departments for filter
$departments_query = "SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_query);

// Get today's date for default filter
$today = date('Y-m-d');

// Reset result pointers
mysqli_data_seek($venues_result, 0);
mysqli_data_seek($departments_result, 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings - Focal Person Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7209b7;
            --primary-dark: #560bad;
            --secondary: #4361ee;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }
        
        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .header {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        /* Status badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .badge-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .badge-cancelled {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        /* Booking card */
        .booking-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            border-left: 5px solid;
            transition: all 0.3s;
        }
        
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .booking-card.pending {
            border-left-color: var(--warning);
        }
        
        .booking-card.approved {
            border-left-color: var(--success);
        }
        
        .booking-card.rejected {
            border-left-color: var(--danger);
        }
        
        .booking-card.cancelled {
            border-left-color: #6c757d;
        }
        
        .booking-card.today {
            background: #fff8e1;
            border: 2px dashed #ffc107;
        }
        
        /* Info badges */
        .info-badge {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 13px;
            margin-right: 8px;
            margin-bottom: 8px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .dept-badge {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .venue-badge {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .time-badge {
            background: #fff3e0;
            color: #e65100;
        }
        
        /* Details section */
        .details-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #495057;
            min-width: 120px;
            display: inline-block;
        }
        
        .detail-value {
            color: #212529;
        }
        
        /* Filter buttons */
        .status-filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            margin-right: 10px;
            margin-bottom: 10px;
            display: inline-block;
        }
        
        .status-filter-btn:hover {
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        .btn-all {
            background: #6c757d;
            color: white;
        }
        
        .btn-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .btn-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .btn-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-cancelled {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .status-filter-btn.active {
            box-shadow: 0 0 0 2px currentColor;
        }
        
        /* Venue type badge */
        .venue-type {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .conference_room {
            background: #bbdefb;
            color: #1565c0;
        }
        
        .auditorium {
            background: #c8e6c9;
            color: #2e7d32;
        }
        
        .seminar_hall {
            background: #e1bee7;
            color: #7b1fa2;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 0 10px;
            }
            
            .booking-card {
                padding: 15px;
            }
            
            .detail-label {
                min-width: 100px;
            }
        }
        
        /* Action buttons */
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 8px;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        /* Stats cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-all .stat-number { color: #6c757d; }
        .stat-pending .stat-number { color: #ffc107; }
        .stat-approved .stat-number { color: #28a745; }
        .stat-rejected .stat-number { color: #dc3545; }
        .stat-cancelled .stat-number { color: #6c757d; }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        /* Header user info */
        .user-avatar {
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
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="index.php" class="text-decoration-none d-flex align-items-center gap-3">
                    <i class="fas fa-calendar-alt fa-lg" style="color: var(--primary);"></i>
                    <div>
                        <h4 class="mb-0" style="color: var(--primary);">All Venue Bookings</h4>
                        <small class="text-muted">Complete booking history</small>
                    </div>
                </a>
                
                <div class="d-flex align-items-center gap-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($faculty_name, 0, 2)); ?>
                        </div>
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($faculty_name); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($department); ?></small>
                        </div>
                    </div>
                    <a href="index.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-2 col-6">
                <div class="stat-card stat-all">
                    <div class="stat-number"><?php echo $status_counts['all']; ?></div>
                    <div class="stat-label">Total</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card stat-pending">
                    <div class="stat-number"><?php echo $status_counts['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card stat-approved">
                    <div class="stat-number"><?php echo $status_counts['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card stat-rejected">
                    <div class="stat-number"><?php echo $status_counts['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card stat-cancelled">
                    <div class="stat-number"><?php echo $status_counts['cancelled']; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                    <div class="stat-number"><?php 
                        $today_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM booking_requests WHERE booking_date = CURDATE()");
                        echo mysqli_fetch_assoc($today_count)['count'];
                    ?></div>
                    <div class="stat-label">Today</div>
                </div>
            </div>
        </div>
        
        <!-- Status Filter -->
        <div class="d-flex flex-wrap mb-4">
            <a href="?status=all" class="status-filter-btn btn-all <?php echo (!$filter_status || $filter_status == 'all') ? 'active' : ''; ?>">
                All (<?php echo $status_counts['all']; ?>)
            </a>
            <a href="?status=pending" class="status-filter-btn btn-pending <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">
                Pending (<?php echo $status_counts['pending']; ?>)
            </a>
            <a href="?status=approved" class="status-filter-btn btn-approved <?php echo $filter_status == 'approved' ? 'active' : ''; ?>">
                Approved (<?php echo $status_counts['approved']; ?>)
            </a>
            <a href="?status=rejected" class="status-filter-btn btn-rejected <?php echo $filter_status == 'rejected' ? 'active' : ''; ?>">
                Rejected (<?php echo $status_counts['rejected']; ?>)
            </a>
            <a href="?status=cancelled" class="status-filter-btn btn-cancelled <?php echo $filter_status == 'cancelled' ? 'active' : ''; ?>">
                Cancelled (<?php echo $status_counts['cancelled']; ?>)
            </a>
        </div>
        
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Bookings</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
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
                        <select class="form-select" name="dept">
                            <option value="">All Departments</option>
                            <?php while ($dept = mysqli_fetch_assoc($departments_result)): ?>
                                <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                    <?php echo ($filter_department == $dept['department']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-search me-1"></i> Filter
                            </button>
                            <a href="all_bookings.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="d-flex gap-3 mb-4">
            <a href="book_venue.php" class="btn btn-success">
                <i class="fas fa-plus me-2"></i> New Booking
            </a>
            <a href="my_bookings.php" class="btn btn-outline-primary">
                <i class="fas fa-calendar me-2"></i> My Bookings
            </a>
            <button onclick="window.print()" class="btn btn-outline-secondary">
                <i class="fas fa-print me-2"></i> Print
            </button>
        </div>
        
        <!-- Bookings List -->
        <?php if (mysqli_num_rows($bookings_result) > 0): ?>
            <div class="row">
                <?php while ($booking = mysqli_fetch_assoc($bookings_result)): 
                    $is_today = (date('Y-m-d', strtotime($booking['booking_date'])) == date('Y-m-d'));
                    $is_my_dept = ($booking['faculty_dept'] == $department);
                    $is_my_booking = ($booking['faculty_id'] == $faculty_id);
                ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="booking-card <?php echo $booking['status']; ?> <?php echo $is_today ? 'today' : ''; ?>">
                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($booking['venue_name']); ?></h5>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="venue-type <?php echo $booking['venue_type']; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $booking['venue_type'])); ?>
                                    </span>
                                    <span class="badge <?php echo 'badge-' . $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">
                                    ID: <?php echo $booking['request_id']; ?>
                                </div>
                                <?php if ($is_my_booking): ?>
                                    <span class="badge bg-primary">Your Booking</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Basic Info -->
                        <div class="mb-3">
                            <span class="info-badge dept-badge">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($booking['faculty_dept']); ?>
                            </span>
                            <span class="info-badge">
                                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($booking['faculty_name']); ?>
                            </span>
                            <span class="info-badge time-badge">
                                <i class="fas fa-clock"></i> 
                                <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                            </span>
                            <span class="info-badge venue-badge">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($booking['venue_location']); ?>
                            </span>
                        </div>
                        
                        <!-- Date and Capacity -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong>Date:</strong> 
                                <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?>
                                <?php if ($is_today): ?>
                                    <span class="badge bg-warning text-dark ms-2">Today</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <strong>Capacity:</strong> 
                                <span class="badge bg-info"><?php echo $booking['attendees_count']; ?>/<?php echo $booking['venue_capacity']; ?></span>
                            </div>
                        </div>
                        
                        <!-- Details Section -->
                        <div class="details-section">
                            <div class="detail-item">
                                <span class="detail-label">Purpose:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['purpose']); ?></span>
                            </div>
                            
                            <?php if (!empty($booking['equipment_needed'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Equipment:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['equipment_needed']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($booking['additional_notes'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Notes:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['additional_notes']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="detail-item">
                                <span class="detail-label">Requested:</span>
                                <span class="detail-value"><?php echo date('M d, Y g:i A', strtotime($booking['requested_at'])); ?></span>
                            </div>
                            
                            <?php if ($booking['status'] == 'approved' && !empty($booking['responded_at'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Approved:</span>
                                <span class="detail-value"><?php echo date('M d, Y g:i A', strtotime($booking['responded_at'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($booking['status'] == 'rejected' && !empty($booking['admin_response'])): ?>
                            <div class="detail-item">
                                <span class="detail-label">Reason:</span>
                                <span class="detail-value text-danger"><?php echo htmlspecialchars($booking['admin_response']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Contact Info -->
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($booking['faculty_email']); ?> |
                                <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($booking['faculty_phone']); ?>
                            </small>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex justify-content-end mt-3">
                            <?php if ($is_my_booking && $booking['status'] == 'pending'): ?>
                                <a href="cancel_booking.php?id=<?php echo $booking['request_id']; ?>" 
                                   class="action-btn btn-danger"
                                   title="Cancel Booking"
                                   onclick="return confirm('Are you sure you want to cancel this booking?')">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h4>No bookings found</h4>
                <p class="text-muted mb-4">
                    <?php echo ($filter_date || $filter_venue || $filter_department || $filter_status) ? 
                        'Try changing your filters to see more results.' : 
                        'There are no booking requests yet.'; ?>
                </p>
                <a href="book_venue.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Create First Booking
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-set date filter to today if empty
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.querySelector('input[name="date"]');
            if (dateInput && !dateInput.value) {
                // Optional: Uncomment to auto-set to today
                // dateInput.value = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>