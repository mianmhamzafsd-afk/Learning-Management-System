<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

include('../db_connect.php');

// Get faculty details
$faculty_id = $_SESSION['faculty_id'];
$faculty_name = $_SESSION['name'];
$faculty_department = $_SESSION['department'];

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $venue_id = mysqli_real_escape_string($conn, $_POST['venue_id']);
    $booking_date = mysqli_real_escape_string($conn, $_POST['booking_date']);
    $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
    $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
    $attendees_count = mysqli_real_escape_string($conn, $_POST['attendees_count']);
    $equipment_needed = mysqli_real_escape_string($conn, $_POST['equipment_needed']);
    $additional_notes = mysqli_real_escape_string($conn, $_POST['additional_notes']);
    
    // Check if venue is available for the requested time
    $availability_query = "SELECT * FROM booking_requests 
                          WHERE venue_id = '$venue_id' 
                          AND booking_date = '$booking_date'
                          AND status = 'approved'
                          AND (
                            (start_time <= '$start_time' AND end_time > '$start_time')
                            OR (start_time < '$end_time' AND end_time >= '$end_time')
                            OR ('$start_time' <= start_time AND '$end_time' > start_time)
                          )";
    $availability_result = mysqli_query($conn, $availability_query);
    
    if (mysqli_num_rows($availability_result) > 0) {
        $message = "The selected venue is already booked for the requested time slot.";
        $message_type = "danger";
    } else {
        // Insert booking request
        $sql = "INSERT INTO booking_requests (
                venue_id, 
                faculty_id, 
                department, 
                booking_date, 
                start_time, 
                end_time, 
                purpose, 
                attendees_count, 
                equipment_needed, 
                additional_notes, 
                status, 
                requested_at
            ) VALUES (
                '$venue_id',
                '$faculty_id',
                '$faculty_department',
                '$booking_date',
                '$start_time',
                '$end_time',
                '$purpose',
                '$attendees_count',
                '$equipment_needed',
                '$additional_notes',
                'pending',
                NOW()
            )";
        
        if (mysqli_query($conn, $sql)) {
            $request_id = mysqli_insert_id($conn);
            $message = "Booking request submitted successfully! Your request ID is #" . str_pad($request_id, 6, '0', STR_PAD_LEFT);
            $message_type = "success";
            
            // Clear form
            $_POST = array();
        } else {
            $message = "Error submitting booking request: " . mysqli_error($conn);
            $message_type = "danger";
        }
    }
}

// Get all active venues
$venues_query = "SELECT * FROM booking_venues WHERE status = 'active' ORDER BY type, name";
$venues_result = mysqli_query($conn, $venues_query);

// Get venue types for filtering
$types_query = "SELECT DISTINCT type FROM booking_venues WHERE status = 'active'";
$types_result = mysqli_query($conn, $types_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Venue - Learing Management System</title>
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
            padding: 20px;
        }

        /* Header Styles */
        .dashboard-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
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

        /* Navigation */
        .dashboard-nav {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .nav-links a.active {
            background: rgba(255, 255, 255, 0.25);
            border-left: 4px solid #4fc3f7;
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
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

        /* Booking Container */
        .booking-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        @media (max-width: 1024px) {
            .booking-container {
                grid-template-columns: 1fr;
            }
        }

        /* Booking Form */
        .booking-form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            color: #1a237e;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e8f4ff;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #1a237e;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Venues Sidebar */
        .venues-sidebar {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        /* Type Filter */
        .type-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .type-btn {
            padding: 8px 16px;
            background: #f8f9fa;
            border: 2px solid #ddd;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .type-btn:hover {
            background: #e8f4ff;
            border-color: #1a237e;
        }

        .type-btn.active {
            background: #1a237e;
            color: white;
            border-color: #1a237e;
        }

        /* Venues List */
        .venues-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .venue-item {
            background: #f8f9fa;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .venue-item:hover {
            border-color: #1a237e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .venue-item.selected {
            border-color: #1a237e;
            background: #e8f4ff;
        }

        .venue-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .venue-name {
            font-size: 18px;
            color: #1a237e;
            font-weight: 600;
        }

        .venue-type {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .venue-type.conference {
            background: linear-gradient(135deg, #2196F3, #1565C0);
        }

        .venue-type.auditorium {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
        }

        .venue-type.seminar {
            background: linear-gradient(135deg, #FF9800, #EF6C00);
        }

        .venue-details {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }

        .venue-details span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .venue-details i {
            color: #1a237e;
        }

        /* Footer */
        .dashboard-footer {
            background: #1a237e;
            color: white;
            padding: 20px;
            text-align: center;
            margin-top: 50px;
            border-radius: 10px;
        }

        .dashboard-footer p {
            opacity: 0.9;
            font-size: 14px;
        }

        /* Required Field */
        .required::after {
            content: " *";
            color: #f44336;
        }

        /* Loading State */
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading i {
            font-size: 24px;
            color: #1a237e;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Availability Check */
        .availability-status {
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            display: none;
        }

        .available {
            background: #e8f6ef;
            color: #2E7D32;
            border: 1px solid #4CAF50;
        }

        .not-available {
            background: #ffeaea;
            color: #c62828;
            border: 1px solid #f44336;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .user-info {
                justify-content: center;
            }

            .nav-links {
                justify-content: center;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .dashboard-header {
                padding: 15px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .booking-form-section,
            .venues-sidebar {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="dashboard-header">
        <div class="header-top">
            <div class="logo-container">
                <h1><i class="fas fa-university"></i> MYPR System</h1>
                <p style="opacity: 0.9; font-size: 14px;">Faculty Portal - Venue Booking</p>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($faculty_name); ?></h3>
                    <p><i class="fas fa-user-tag"></i> Faculty | <?php echo htmlspecialchars($faculty_department); ?></p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="dashboard-nav">
            <div class="nav-links">
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="book_venue.php" class="active">
                    <i class="fas fa-calendar-plus"></i> Book Venue
                </a>
                <a href="my_bookings.php">
                    <i class="fas fa-calendar-alt"></i> My Bookings
                </a>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-calendar-plus"></i> Book a Venue</h1>
                <p style="color: #666; margin-top: 5px;">Fill out the form below to request a venue booking</p>
            </div>
            <div class="header-actions">
                <a href="my_bookings.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> My Bookings
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Booking Container -->
        <div class="booking-container">
            <!-- Booking Form -->
            <div class="booking-form-section">
                <h2 class="section-title"><i class="fas fa-edit"></i> Booking Request Form</h2>
                
                <form method="POST" action="" id="bookingForm">
                    <!-- Selected Venue (Hidden) -->
                    <input type="hidden" name="venue_id" id="selected_venue_id" required>
                    
                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-building"></i> Selected Venue</label>
                        <div id="selected_venue_display" style="padding: 15px; background: #f8f9fa; border-radius: 8px; border: 2px dashed #ddd; color: #666;">
                            <i class="fas fa-info-circle"></i> Please select a venue from the list on the right
                        </div>
                        <small class="text-muted" style="display: block; margin-top: 5px;">
                            The venue must be selected before submitting the form
                        </small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-calendar-day"></i> Booking Date</label>
                            <input type="date" class="form-control" name="booking_date" id="booking_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required 
                                   value="<?php echo isset($_POST['booking_date']) ? $_POST['booking_date'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-clock"></i> Start Time</label>
                            <input type="time" class="form-control" name="start_time" id="start_time" required 
                                   value="<?php echo isset($_POST['start_time']) ? $_POST['start_time'] : '09:00'; ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-clock"></i> End Time</label>
                            <input type="time" class="form-control" name="end_time" id="end_time" required 
                                   value="<?php echo isset($_POST['end_time']) ? $_POST['end_time'] : '10:00'; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required"><i class="fas fa-users"></i> Number of Attendees</label>
                            <input type="number" class="form-control" name="attendees_count" min="1" max="5000" required 
                                   value="<?php echo isset($_POST['attendees_count']) ? $_POST['attendees_count'] : '10'; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-file-alt"></i> Purpose of Booking</label>
                        <textarea class="form-control" name="purpose" required 
                                  placeholder="Describe the purpose of this booking (e.g., Department meeting, Workshop, Seminar, etc.)"><?php echo isset($_POST['purpose']) ? $_POST['purpose'] : ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-tools"></i> Equipment Needed</label>
                            <textarea class="form-control" name="equipment_needed" 
                                      placeholder="List any equipment requirements (e.g., Projector, Whiteboard, Sound System, etc.)"><?php echo isset($_POST['equipment_needed']) ? $_POST['equipment_needed'] : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-sticky-note"></i> Additional Notes</label>
                            <textarea class="form-control" name="additional_notes" 
                                      placeholder="Any additional information or special requests..."><?php echo isset($_POST['additional_notes']) ? $_POST['additional_notes'] : ''; ?></textarea>
                        </div>
                    </div>

                    <!-- Booking Information -->
                    <div class="form-group" style="background: #e8f4ff; padding: 20px; border-radius: 8px; margin-top: 30px;">
                        <h4 style="color: #1a237e; margin-bottom: 15px;"><i class="fas fa-info-circle"></i> Booking Information</h4>
                        <p><strong>Requested by:</strong> <?php echo htmlspecialchars($faculty_name); ?></p>
                        <p><strong>Department:</strong> <?php echo htmlspecialchars($faculty_department); ?></p>
                        <p><strong>Status:</strong> <span style="background: #fff3cd; color: #856404; padding: 3px 10px; border-radius: 20px; font-size: 12px;">Pending Approval</span></p>
                        <p style="margin-top: 10px; font-size: 14px; color: #666;">
                            <i class="fas fa-exclamation-circle"></i> Your booking request will be reviewed by the admin. You'll be notified once it's approved or rejected.
                        </p>
                    </div>

                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" class="btn-primary" style="width: 100%; padding: 15px;">
                            <i class="fas fa-paper-plane"></i> Submit Booking Request
                        </button>
                    </div>
                </form>
            </div>

            <!-- Venues Sidebar -->
            <div class="venues-sidebar">
                <h2 class="section-title"><i class="fas fa-building"></i> Available Venues</h2>
                
                <!-- Type Filter -->
                <div class="type-filter">
                    <button class="type-btn active" data-type="all">All</button>
                    <?php while ($type = mysqli_fetch_assoc($types_result)): 
                        $type_name = ucwords(str_replace('_', ' ', $type['type']));
                    ?>
                        <button class="type-btn" data-type="<?php echo $type['type']; ?>">
                            <?php echo $type_name; ?>
                        </button>
                    <?php endwhile; ?>
                </div>

                <!-- Venues List -->
                <div class="venues-list" id="venuesList">
                    <?php 
                    // Reset venues pointer
                    mysqli_data_seek($venues_result, 0);
                    while ($venue = mysqli_fetch_assoc($venues_result)): 
                        $type_class = str_replace('_', '', $venue['type']);
                        $type_name = ucwords(str_replace('_', ' ', $venue['type']));
                    ?>
                        <div class="venue-item" data-venue-id="<?php echo $venue['venue_id']; ?>" 
                             data-venue-type="<?php echo $venue['type']; ?>" 
                             data-venue-capacity="<?php echo $venue['capacity']; ?>">
                            <div class="venue-header">
                                <div class="venue-name"><?php echo htmlspecialchars($venue['name']); ?></div>
                                <span class="venue-type <?php echo $type_class; ?>"><?php echo $type_name; ?></span>
                            </div>
                            
                            <div class="venue-details">
                                <span title="Capacity">
                                    <i class="fas fa-users"></i> <?php echo $venue['capacity']; ?> seats
                                </span>
                                <?php if ($venue['location']): ?>
                                <span title="Location">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($venue['location']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($venue['amenities']): ?>
                            <div class="venue-details" style="margin-top: 5px;">
                                <span title="Amenities">
                                    <i class="fas fa-star"></i> <?php echo htmlspecialchars(substr($venue['amenities'], 0, 50)); ?>
                                    <?php echo strlen($venue['amenities']) > 50 ? '...' : ''; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                    
                    <?php if (mysqli_num_rows($venues_result) == 0): ?>
                        <div style="text-align: center; padding: 40px 20px; color: #666;">
                            <i class="fas fa-building fa-3x" style="margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>No venues available at the moment.</p>
                            <p style="font-size: 14px;">Please contact the administrator.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Selected Venue Info -->
                <div id="selectedVenueInfo" style="display: none; margin-top: 20px; padding: 20px; background: #e8f4ff; border-radius: 8px;">
                    <h4 style="color: #1a237e; margin-bottom: 10px;"><i class="fas fa-check-circle"></i> Selected Venue</h4>
                    <div id="venueInfoContent"></div>
                    <div id="availabilityCheck" style="margin-top: 15px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="dashboard-footer">
        <p><i class="fas fa-copyright"></i> <?php echo date('Y'); ?> MYPR System. All rights reserved.</p>
        <p>Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?> | Role: <?php echo ucfirst($_SESSION['role']); ?></p>
    </div>

    <script>
        // Venue Selection
        let selectedVenueId = null;
        let selectedVenueName = null;
        let selectedVenueCapacity = null;

        // Type Filtering
        document.querySelectorAll('.type-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const type = this.dataset.type;
                filterVenues(type);
            });
        });

        function filterVenues(type) {
            const venueItems = document.querySelectorAll('.venue-item');
            
            venueItems.forEach(item => {
                if (type === 'all' || item.dataset.venueType === type) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Venue Selection
        document.querySelectorAll('.venue-item').forEach(item => {
            item.addEventListener('click', function() {
                // Remove selection from all items
                document.querySelectorAll('.venue-item').forEach(i => {
                    i.classList.remove('selected');
                });
                
                // Add selection to clicked item
                this.classList.add('selected');
                
                // Update selected venue data
                selectedVenueId = this.dataset.venueId;
                selectedVenueCapacity = this.dataset.venueCapacity;
                selectedVenueName = this.querySelector('.venue-name').textContent;
                
                // Update hidden input
                document.getElementById('selected_venue_id').value = selectedVenueId;
                
                // Update display
                const displayDiv = document.getElementById('selected_venue_display');
                displayDiv.innerHTML = `
                    <strong style="color: #1a237e;">${selectedVenueName}</strong>
                    <br>
                    <small>Capacity: ${selectedVenueCapacity} seats</small>
                `;
                displayDiv.style.borderColor = '#1a237e';
                displayDiv.style.background = '#e8f4ff';
                
                // Show selected venue info
                showSelectedVenueInfo(this);
                
                // Check availability
                checkAvailability();
            });
        });

        function showSelectedVenueInfo(venueElement) {
            const infoDiv = document.getElementById('selectedVenueInfo');
            const contentDiv = document.getElementById('venueInfoContent');
            
            const venueName = venueElement.querySelector('.venue-name').textContent;
            const venueType = venueElement.querySelector('.venue-type').textContent;
            const venueDetails = venueElement.querySelectorAll('.venue-details span');
            
            let detailsHtml = '';
            venueDetails.forEach(span => {
                detailsHtml += `<p style="margin: 5px 0;">${span.innerHTML}</p>`;
            });
            
            contentDiv.innerHTML = `
                <p><strong>${venueName}</strong> <span class="venue-type" style="display: inline-block; margin-left: 10px;">${venueType}</span></p>
                ${detailsHtml}
            `;
            
            infoDiv.style.display = 'block';
        }

        // Availability Check
        function checkAvailability() {
            const bookingDate = document.getElementById('booking_date').value;
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            if (!selectedVenueId || !bookingDate || !startTime || !endTime) {
                return;
            }
            
            const availabilityDiv = document.getElementById('availabilityCheck');
            availabilityDiv.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Checking availability...</p>';
            
            // In a real application, you would make an AJAX call here
            // For now, we'll simulate it with a timeout
            setTimeout(() => {
                // Simulate random availability (for demo purposes)
                const isAvailable = Math.random() > 0.5;
                
                if (isAvailable) {
                    availabilityDiv.innerHTML = `
                        <div class="availability-status available">
                            <i class="fas fa-check-circle"></i> This venue is available for the selected time slot.
                        </div>
                    `;
                } else {
                    availabilityDiv.innerHTML = `
                        <div class="availability-status not-available">
                            <i class="fas fa-times-circle"></i> This venue may not be available for the selected time slot.
                            <p style="margin-top: 5px; font-size: 12px;">Please verify with the administrator.</p>
                        </div>
                    `;
                }
            }, 1000);
        }

        // Form Validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            if (!selectedVenueId) {
                e.preventDefault();
                alert('Please select a venue before submitting the form.');
                return false;
            }
            
            const bookingDate = document.getElementById('booking_date').value;
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            
            if (!bookingDate || !startTime || !endTime) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            if (startTime >= endTime) {
                e.preventDefault();
                alert('End time must be after start time.');
                return false;
            }
            
            // Check if booking date is in the past
            const today = new Date().toISOString().split('T')[0];
            if (bookingDate < today) {
                e.preventDefault();
                alert('Booking date cannot be in the past.');
                return false;
            }
            
            // Check attendees count against venue capacity
            const attendees = document.querySelector('input[name="attendees_count"]').value;
            if (parseInt(attendees) > parseInt(selectedVenueCapacity)) {
                e.preventDefault();
                alert(`Number of attendees (${attendees}) exceeds venue capacity (${selectedVenueCapacity}).`);
                return false;
            }
            
            // Show confirmation
            const confirmSubmit = confirm('Are you sure you want to submit this booking request?');
            if (!confirmSubmit) {
                e.preventDefault();
                return false;
            }
        });

        // Real-time availability check when date or time changes
        document.getElementById('booking_date').addEventListener('change', checkAvailability);
        document.getElementById('start_time').addEventListener('change', checkAvailability);
        document.getElementById('end_time').addEventListener('change', checkAvailability);

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

        // Set minimum time for today's bookings
        const today = new Date().toISOString().split('T')[0];
        const bookingDateInput = document.getElementById('booking_date');
        bookingDateInput.min = today;

        if (bookingDateInput.value === today) {
            const now = new Date();
            const currentHour = now.getHours().toString().padStart(2, '0');
            const currentMinute = now.getMinutes().toString().padStart(2, '0');
            const startTimeInput = document.getElementById('start_time');
            
            if (startTimeInput.value < `${currentHour}:${currentMinute}`) {
                startTimeInput.value = `${currentHour}:${currentMinute}`;
            }
        }
    </script>
</body>
</html>