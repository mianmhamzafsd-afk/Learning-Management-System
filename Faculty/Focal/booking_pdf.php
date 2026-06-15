<?php
// Save this as booking_pdf.php
session_start();

// Check if user is logged in as faculty
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

// Get booking ID
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$download = isset($_GET['download']) ? true : false;

if ($booking_id == 0) {
    die("Invalid booking ID.");
}

// Get booking details
$query = "SELECT br.*, 
                 f.name as faculty_name, 
                 f.department as faculty_dept,
                 f.designation as faculty_designation,
                 f.email as faculty_email,
                 f.phone as faculty_phone,
                 f.office_location as faculty_office,
                 v.name as venue_name, 
                 v.type as venue_type, 
                 v.location as venue_location,
                 v.capacity as venue_capacity,
                 v.amenities as venue_amenities,
                 a.admin_name as approved_by
          FROM booking_requests br
          JOIN faculty f ON br.faculty_id = f.faculty_id
          JOIN booking_venues v ON br.venue_id = v.venue_id
          LEFT JOIN admin a ON br.admin_id = a.admin_id
          WHERE br.request_id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    die("Booking not found.");
}

// Get current user info
$current_faculty_id = $_SESSION['faculty_id'] ?? 0;
$current_faculty_name = $_SESSION['faculty_name'] ?? 'Faculty';
$current_department = $_SESSION['department'] ?? 'Department';

// Check if user is authorized
$is_my_booking = ($booking['faculty_id'] == $current_faculty_id);
$is_same_dept = ($booking['faculty_dept'] == $current_department);
$is_focal_person = $_SESSION['is_focal_person'] ?? 0;

if (!$is_my_booking && !$is_focal_person) {
    die("You are not authorized to view this booking confirmation.");
}

// Generate unique reference number
$reference_number = 'BOOK-' . strtoupper(substr($booking['faculty_dept'], 0, 3)) . '-' . 
                    date('Ymd', strtotime($booking['booking_date'])) . '-' . 
                    str_pad($booking['request_id'], 4, '0', STR_PAD_LEFT);

// Calculate duration
$duration = (strtotime($booking['end_time']) - strtotime($booking['start_time'])) / 3600;

// ==================== FPDF GENERATION ====================
if ($download) {
    // Include FPDF library
    require('fpdf/fpdf.php');
    
    class PDF extends FPDF {
        function Header() {
            // Logo or header content
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, 'UNIVERSITY', 0, 1, 'C');
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'VENUE BOOKING CONFIRMATION', 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, '', 0, 1);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' - University Venue Booking System', 0, 0, 'C');
        }
        
        function ChapterTitle($title) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(200, 220, 255);
            $this->Cell(0, 8, $title, 0, 1, 'L', true);
            $this->Ln(4);
        }
        
        function TwoColumnTable($labels, $values) {
            $this->SetFont('Arial', '', 10);
            for ($i = 0; $i < count($labels); $i++) {
                $this->Cell(60, 7, $labels[$i], 0, 0, 'L');
                $this->Cell(0, 7, $values[$i], 0, 1);
            }
            $this->Ln(5);
        }
    }
    
    // Create PDF instance
    $pdf = new PDF();
    $pdf->AddPage();
    
    // Reference Number and Status
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Reference: ' . $reference_number, 0, 1, 'C');
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 6, 'Status: ' . strtoupper($booking['status']) . ' | Generated: ' . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Status badge
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor($booking['status'] == 'approved' ? 212 : ($booking['status'] == 'pending' ? 255 : 248), 
                      $booking['status'] == 'approved' ? 237 : ($booking['status'] == 'pending' ? 243 : 215), 
                      $booking['status'] == 'approved' ? 218 : ($booking['status'] == 'pending' ? 205 : 218));
    $pdf->Cell(0, 8, strtoupper($booking['status']) . ' BOOKING', 0, 1, 'C', true);
    $pdf->Ln(10);
    
    // Booking Details
    $pdf->ChapterTitle('Booking Details');
    $bookingLabels = [
        'Booking Date:',
        'Time Slot:',
        'Duration:',
        'Venue:',
        'Venue Type:',
        'Location:',
        'Capacity:'
    ];
    
    $bookingValues = [
        date('d/m/Y', strtotime($booking['booking_date'])),
        date('H:i', strtotime($booking['start_time'])) . ' - ' . date('H:i', strtotime($booking['end_time'])),
        round($duration, 1) . ' hours',
        htmlspecialchars($booking['venue_name']),
        ucwords(str_replace('_', ' ', $booking['venue_type'])),
        htmlspecialchars($booking['venue_location']),
        $booking['venue_capacity'] . ' persons'
    ];
    
    $pdf->TwoColumnTable($bookingLabels, $bookingValues);
    
    // Requester Information
    $pdf->ChapterTitle('Requester Information');
    $requesterLabels = [
        'Name:',
        'Department:',
        'Designation:',
        'Office Location:',
        'Email:',
        'Phone:'
    ];
    
    $requesterValues = [
        htmlspecialchars($booking['faculty_name']),
        htmlspecialchars($booking['faculty_dept']),
        htmlspecialchars($booking['faculty_designation']),
        htmlspecialchars($booking['faculty_office']),
        htmlspecialchars($booking['faculty_email']),
        htmlspecialchars($booking['faculty_phone'])
    ];
    
    $pdf->TwoColumnTable($requesterLabels, $requesterValues);
    
    // Event Information
    $pdf->ChapterTitle('Event Information');
    $eventLabels = [
        'Purpose:',
        'Expected Attendees:',
        'Request Submitted:'
    ];
    
    $eventValues = [
        htmlspecialchars($booking['purpose']),
        $booking['attendees_count'] . ' persons',
        date('d/m/Y H:i', strtotime($booking['requested_at']))
    ];
    
    if (!empty($booking['equipment_needed'])) {
        $eventLabels[] = 'Equipment Required:';
        $eventValues[] = htmlspecialchars($booking['equipment_needed']);
    }
    
    if (!empty($booking['additional_notes'])) {
        $eventLabels[] = 'Additional Notes:';
        $eventValues[] = htmlspecialchars($booking['additional_notes']);
    }
    
    if ($booking['status'] == 'approved') {
        $eventLabels[] = 'Approved By:';
        $eventValues[] = !empty($booking['approved_by']) ? htmlspecialchars($booking['approved_by']) : 'Administration';
        
        $eventLabels[] = 'Approval Date:';
        $eventValues[] = date('d/m/Y H:i', strtotime($booking['responded_at']));
    }
    
    $pdf->TwoColumnTable($eventLabels, $eventValues);
    
    // Terms and Conditions
    $pdf->ChapterTitle('Important Terms & Conditions');
    $pdf->SetFont('Arial', '', 10);
    $terms = [
        '1. Present this confirmation at venue entrance',
        '2. Adhere strictly to booking time slot',
        '3. Keep venue clean and return equipment',
        '4. University may cancel bookings if necessary',
        '5. Contact admin for any changes/issues'
    ];
    
    foreach ($terms as $term) {
        $pdf->Cell(0, 6, $term, 0, 1);
    }
    $pdf->Ln(10);
    
    // Footer note
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'This is an electronically generated document. No signature required.', 0, 1, 'C');
    $pdf->Cell(0, 5, 'University Venue Booking System | Reference: ' . $reference_number, 0, 1, 'C');
    $pdf->Cell(0, 5, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    
    // Output the PDF for download
    $pdf->Output('D', 'Booking_Confirmation_' . $reference_number . '.pdf');
    exit();
}

// ==================== HTML VIEW (Only shows if NOT downloading) ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - <?php echo $reference_number; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Print styles */
        @media print {
            @page {
                size: A4;
                margin: 0.5cm;
            }
            
            body {
                margin: 0;
                padding: 0;
                background: white !important;
                font-family: Arial, sans-serif !important;
                font-size: 11pt !important;
            }
            
            .no-print,
            .actions,
            .btn,
            i,
            .fa {
                display: none !important;
            }
            
            .print-container {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            
            .print-header {
                text-align: center;
                margin-bottom: 15px;
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
            }
            
            .print-title {
                font-size: 14pt !important;
                font-weight: bold;
            }
            
            .print-subtitle {
                font-size: 12pt !important;
            }
            
            .print-ref {
                text-align: center;
                background: #f0f0f0;
                padding: 8px;
                margin: 10px 0;
                border: 1px solid #ccc;
                font-size: 10pt;
            }
            
            .print-status {
                text-align: center;
                font-weight: bold;
                margin: 10px 0;
                padding: 5px;
                background: <?php echo $booking['status'] == 'approved' ? '#d4edda' : '#fff3cd'; ?>;
                border: 1px solid <?php echo $booking['status'] == 'approved' ? '#c3e6cb' : '#ffeaa7'; ?>;
                text-transform: uppercase;
            }
            
            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0;
                font-size: 10pt;
            }
            
            .print-table td {
                padding: 5px;
                border-bottom: 1px solid #ddd;
                vertical-align: top;
            }
            
            .print-label {
                font-weight: bold;
                width: 35%;
            }
            
            .clearfix {
                clear: both;
            }
            
            .terms-box {
                background: #f8f9fa;
                padding: 10px;
                margin-top: 20px;
                font-size: 9pt;
                border: 1px solid #ddd;
            }
        }
        
        /* Screen styles */
        @media screen {
            body {
                background: #f5f5f5;
                padding: 20px;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                color: #333;
                line-height: 1.6;
            }
            
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                box-shadow: 0 0 25px rgba(0,0,0,0.1);
                border-radius: 10px;
                overflow: hidden;
            }
            
            .header {
                background: linear-gradient(135deg, #2c3e50, #34495e);
                color: white;
                padding: 30px 40px;
                text-align: center;
            }
            
            .university-name {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 5px;
                letter-spacing: 1px;
            }
            
            .document-title {
                font-size: 22px;
                opacity: 0.95;
                margin-bottom: 15px;
                font-weight: 500;
            }
            
            .subtitle {
                font-size: 16px;
                opacity: 0.8;
                font-weight: 300;
            }
            
            .reference-section {
                background: #f8f9fa;
                padding: 20px;
                text-align: center;
                border-bottom: 3px solid #e9ecef;
                margin-bottom: 20px;
            }
            
            .reference-number {
                font-size: 20px;
                font-weight: 600;
                color: #2c3e50;
                margin-bottom: 8px;
                letter-spacing: 1px;
            }
            
            .meta-info {
                color: #6c757d;
                font-size: 14px;
            }
            
            .status-badge {
                display: inline-block;
                padding: 12px 28px;
                margin: 20px 0;
                background: <?php echo $booking['status'] == 'approved' ? '#28a745' : ($booking['status'] == 'pending' ? '#ffc107' : ($booking['status'] == 'rejected' ? '#dc3545' : '#6c757d')); ?>;
                color: white;
                border-radius: 30px;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 15px;
                letter-spacing: 1.5px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
                transition: all 0.3s ease;
            }
            
            .status-badge:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 12px rgba(0,0,0,0.2);
            }
            
            .actions {
                text-align: center;
                padding: 25px;
                background: linear-gradient(to right, #f8f9fa, #e9ecef);
                border-bottom: 1px solid #dee2e6;
                margin-bottom: 30px;
            }
            
            .btn {
                display: inline-block;
                padding: 14px 28px;
                margin: 8px 12px;
                border: none;
                border-radius: 6px;
                color: white;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 15px;
                letter-spacing: 0.5px;
                box-shadow: 0 3px 6px rgba(0,0,0,0.1);
            }
            
            .btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 6px 15px rgba(0,0,0,0.2);
            }
            
            .btn:active {
                transform: translateY(-1px);
            }
            
            .btn-download {
                background: linear-gradient(135deg, #9b59b6, #8e44ad);
            }
            
            .btn-download:hover {
                background: linear-gradient(135deg, #8e44ad, #7d3c98);
            }
            
            .btn-print {
                background: linear-gradient(135deg, #3498db, #2980b9);
            }
            
            .btn-print:hover {
                background: linear-gradient(135deg, #2980b9, #2573a7);
            }
            
            .btn-back {
                background: linear-gradient(135deg, #e74c3c, #c0392b);
            }
            
            .btn-back:hover {
                background: linear-gradient(135deg, #c0392b, #a93226);
            }
            
            .btn i {
                margin-right: 8px;
            }
            
            .content {
                padding: 0 40px 40px 40px;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin-bottom: 35px;
            }
            
            .section {
                background: white;
                border-radius: 8px;
                padding: 25px;
                box-shadow: 0 3px 12px rgba(0,0,0,0.08);
                border: 1px solid #e9ecef;
                transition: transform 0.3s ease;
            }
            
            .section:hover {
                transform: translateY(-5px);
                box-shadow: 0 5px 20px rgba(0,0,0,0.12);
            }
            
            .section-title {
                font-size: 18px;
                font-weight: 600;
                color: #2c3e50;
                margin-bottom: 20px;
                padding-bottom: 12px;
                border-bottom: 3px solid #3498db;
                position: relative;
            }
            
            .section-title:after {
                content: '';
                position: absolute;
                bottom: -3px;
                left: 0;
                width: 60px;
                height: 3px;
                background: #9b59b6;
            }
            
            .info-row {
                display: flex;
                margin-bottom: 18px;
                padding-bottom: 18px;
                border-bottom: 1px dashed #e9ecef;
                transition: border-color 0.3s ease;
            }
            
            .info-row:hover {
                border-bottom: 1px dashed #3498db;
            }
            
            .info-label {
                flex: 1;
                font-weight: 600;
                color: #495057;
                font-size: 15px;
            }
            
            .info-value {
                flex: 2;
                color: #212529;
                font-size: 15px;
            }
            
            .footer {
                text-align: center;
                padding: 25px;
                background: linear-gradient(135deg, #2c3e50, #34495e);
                color: white;
                font-size: 14px;
                margin-top: 40px;
                border-top: 5px solid #3498db;
            }
            
            .terms-box-screen {
                background: linear-gradient(to right, #e8f4fc, #f0f8ff);
                padding: 25px;
                margin-top: 35px;
                border-radius: 8px;
                border-left: 5px solid #3498db;
                box-shadow: 0 3px 10px rgba(52, 152, 219, 0.1);
            }
            
            .terms-title {
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 15px;
                font-size: 17px;
                display: flex;
                align-items: center;
            }
            
            .terms-title:before {
                content: '📋';
                margin-right: 10px;
                font-size: 20px;
            }
            
            .terms-list {
                margin: 15px 0 0 20px;
                color: #495057;
            }
            
            .terms-list li {
                margin-bottom: 10px;
                line-height: 1.5;
            }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .container {
                border-radius: 5px;
            }
            
            .header {
                padding: 20px;
            }
            
            .university-name {
                font-size: 24px;
            }
            
            .document-title {
                font-size: 18px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .content {
                padding: 0 20px 20px 20px;
            }
            
            .actions {
                padding: 15px;
            }
            
            .btn {
                display: block;
                width: 100%;
                margin: 10px 0;
                padding: 12px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Main Container -->
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="university-name">UNIVERSITY</div>
            <div class="document-title">VENUE BOOKING CONFIRMATION</div>
            <div class="subtitle">Official Confirmation Document</div>
        </div>
        
        <!-- Reference Section -->
        <div class="reference-section">
            <div class="reference-number">REFERENCE: <?php echo $reference_number; ?></div>
            <div class="meta-info">
                Status: <?php echo strtoupper($booking['status']); ?> | 
                Generated: <?php echo date('F j, Y g:i A'); ?>
            </div>
        </div>
        
        <!-- Status Badge -->
        <div style="text-align: center; padding: 10px 40px 0 40px;">
            <span class="status-badge">
                <?php echo strtoupper($booking['status']); ?> BOOKING
            </span>
        </div>
        
        <!-- Action Buttons -->
        <div class="actions no-print">
            <a href="?id=<?php echo $booking_id; ?>&download=1" class="btn btn-download">
                <i class="fas fa-download"></i> Download PDF
            </a>
            <a href="javascript:window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Print Document
            </a>
            <a href="my_bookings.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Bookings
            </a>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Two Column Information Grid -->
            <div class="info-grid">
                <!-- Left Column - Booking Details -->
                <div class="section">
                    <div class="section-title">Booking Details</div>
                    <div class="info-row">
                        <div class="info-label">Booking Date:</div>
                        <div class="info-value"><?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Time Slot:</div>
                        <div class="info-value">
                            <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Duration:</div>
                        <div class="info-value"><?php echo round($duration, 1); ?> hours</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Venue:</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['venue_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Venue Type:</div>
                        <div class="info-value"><?php echo ucwords(str_replace('_', ' ', $booking['venue_type'])); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Location:</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['venue_location']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Capacity:</div>
                        <div class="info-value"><?php echo $booking['venue_capacity']; ?> persons</div>
                    </div>
                </div>
                
                <!-- Right Column - Requester Information -->
                <div class="section">
                    <div class="section-title">Requester Information</div>
                    <div class="info-row">
                        <div class="info-label">Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['faculty_name']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Department:</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['faculty_dept']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Designation:</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['faculty_designation']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Office Location:</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['faculty_office']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['faculty_email']); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Phone:</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['faculty_phone']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Event Information Section -->
            <div class="section">
                <div class="section-title">Event Information</div>
                <div class="info-row">
                    <div class="info-label">Purpose:</div>
                    <div class="info-value"><?php echo htmlspecialchars($booking['purpose']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Expected Attendees:</div>
                    <div class="info-value"><?php echo $booking['attendees_count']; ?> persons</div>
                </div>
                <?php if (!empty($booking['equipment_needed'])): ?>
                <div class="info-row">
                    <div class="info-label">Equipment Required:</div>
                    <div class="info-value"><?php echo htmlspecialchars($booking['equipment_needed']); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($booking['additional_notes'])): ?>
                <div class="info-row">
                    <div class="info-label">Additional Notes:</div>
                    <div class="info-value"><?php echo htmlspecialchars($booking['additional_notes']); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <div class="info-label">Request Submitted:</div>
                    <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($booking['requested_at'])); ?></div>
                </div>
                <?php if ($booking['status'] == 'approved'): ?>
                <div class="info-row">
                    <div class="info-label">Approved By:</div>
                    <div class="info-value"><?php echo !empty($booking['approved_by']) ? htmlspecialchars($booking['approved_by']) : 'Administration'; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Approval Date:</div>
                    <div class="info-value"><?php echo date('F j, Y g:i A', strtotime($booking['responded_at'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Terms & Conditions -->
            <div class="terms-box-screen">
                <div class="terms-title">Important Terms & Conditions</div>
                <ul class="terms-list">
                    <li>Present this confirmation at the venue entrance before the event</li>
                    <li>Adhere strictly to the allocated time slot. Late departures may incur penalties</li>
                    <li>Keep the venue clean and return all equipment in proper condition</li>
                    <li>University reserves the right to cancel bookings if necessary for institutional priorities</li>
                    <li>Contact the administration office at least 24 hours in advance for any changes or issues</li>
                </ul>
            </div>
            
            <!-- Footer Note -->
            <div style="text-align: center; margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef;">
                <p style="font-style: italic; color: #6c757d; margin: 0;">
                    This is an electronically generated document. No physical signature required.<br>
                    University Venue Booking System | Reference: <?php echo $reference_number; ?>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer no-print">
            <div style="font-size: 16px; font-weight: 600; margin-bottom: 10px;">University Venue Booking System</div>
            <div style="margin-bottom: 5px; opacity: 0.9;">
                Reference: <?php echo $reference_number; ?> | Generated: <?php echo date('Y-m-d H:i:s'); ?>
            </div>
            <div style="font-size: 13px; opacity: 0.8;">
                Email: venue.booking@university.edu | Phone: (123) 456-7890 | Extension: 1234
            </div>
        </div>
    </div>
    
    <!-- Hidden Print Container (for printing only) -->
    <div class="print-container" style="display: none;">
        <!-- Print Header -->
        <div class="print-header">
            <div class="print-title">UNIVERSITY</div>
            <div class="print-subtitle">Venue Booking Confirmation</div>
        </div>
        
        <!-- Reference -->
        <div class="print-ref">
            <strong>REF: <?php echo $reference_number; ?></strong><br>
            Status: <?php echo strtoupper($booking['status']); ?> | Generated: <?php echo date('d/m/Y H:i'); ?>
        </div>
        
        <!-- Status -->
        <div class="print-status">
            <?php echo strtoupper($booking['status']); ?> BOOKING
        </div>
        
        <!-- Booking Details -->
        <table class="print-table">
            <tr>
                <td class="print-label">Booking Date:</td>
                <td><?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?></td>
            </tr>
            <tr>
                <td class="print-label">Time:</td>
                <td><?php echo date('H:i', strtotime($booking['start_time'])); ?> - <?php echo date('H:i', strtotime($booking['end_time'])); ?></td>
            </tr>
            <tr>
                <td class="print-label">Duration:</td>
                <td><?php echo round($duration, 1); ?> hours</td>
            </tr>
            <tr>
                <td class="print-label">Venue:</td>
                <td><?php echo htmlspecialchars($booking['venue_name']); ?></td>
            </tr>
            <tr>
                <td class="print-label">Venue Type:</td>
                <td><?php echo ucwords(str_replace('_', ' ', $booking['venue_type'])); ?></td>
            </tr>
            <tr>
                <td class="print-label">Location:</td>
                <td><?php echo htmlspecialchars($booking['venue_location']); ?></td>
            </tr>
            <tr>
                <td class="print-label">Capacity:</td>
                <td><?php echo $booking['venue_capacity']; ?> persons</td>
            </tr>
        </table>
        
        <!-- Requester Details -->
        <table class="print-table">
            <tr>
                <td colspan="2" style="font-weight: bold; background: #f0f0f0; padding: 8px;">Requester Information</td>
            </tr>
            <tr>
                <td class="print-label">Name:</td>
                <td><?php echo htmlspecialchars($booking['faculty_name']); ?></td>
            </tr>
            <tr>
                <td class="print-label">Department:</td>
                <td><?php echo htmlspecialchars($booking['faculty_dept']); ?></td>
            </tr>
            <tr>
                <td class="print-label">Designation:</td>
                <td><?php echo htmlspecialchars($booking['faculty_designation']); ?></td>
            </tr>
            <tr>
                <td class="print-label">Office:</td>
                <td><?php echo htmlspecialchars($booking['faculty_office']); ?></td>
            </tr>
            <tr>
                <td class="print-label">Email:</td>
                <td><?php echo htmlspecialchars($booking['faculty_email']); ?></td>
            </tr>
            <tr>
                <td class="print-label">Phone:</td>
                <td><?php echo htmlspecialchars($booking['faculty_phone']); ?></td>
            </tr>
        </table>
        
        <!-- Event Details -->
        <table class="print-table">
            <tr>
                <td colspan="2" style="font-weight: bold; background: #f0f0f0; padding: 8px;">Event Information</td>
            </tr>
            <tr>
                <td class="print-label">Purpose:</td>
                <td><?php echo htmlspecialchars($booking['purpose']); ?></td>
            </tr>
            <tr>
                <td class="print-label">Attendees:</td>
                <td><?php echo $booking['attendees_count']; ?> persons</td>
            </tr>
            <?php if (!empty($booking['equipment_needed'])): ?>
            <tr>
                <td class="print-label">Equipment:</td>
                <td><?php echo htmlspecialchars($booking['equipment_needed']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="print-label">Requested:</td>
                <td><?php echo date('d/m/Y H:i', strtotime($booking['requested_at'])); ?></td>
            </tr>
            <?php if ($booking['status'] == 'approved'): ?>
            <tr>
                <td class="print-label">Approved:</td>
                <td><?php echo date('d/m/Y H:i', strtotime($booking['responded_at'])); ?></td>
            </tr>
            <?php if (!empty($booking['approved_by'])): ?>
            <tr>
                <td class="print-label">By:</td>
                <td><?php echo htmlspecialchars($booking['approved_by']); ?></td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
        </table>
        
        <!-- Terms -->
        <div class="terms-box">
            <strong>Important Terms:</strong><br>
            1. Present this confirmation at venue entrance<br>
            2. Adhere strictly to booking time slot<br>
            3. Keep venue clean and return equipment<br>
            4. University may cancel bookings if necessary<br>
            5. Contact admin for any changes/issues
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; margin-top: 30px; font-size: 9pt; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
            University Venue Booking System | Reference: <?php echo $reference_number; ?> | Generated: <?php echo date('Y-m-d H:i:s'); ?>
            <br>
            <em>This is an electronically generated document. No signature required.</em>
        </div>
    </div>

    <script>
        // Handle print button and print view
        document.addEventListener('DOMContentLoaded', function() {
            // Print button functionality
            document.querySelector('.btn-print').addEventListener('click', function(e) {
                e.preventDefault();
                
                // Hide main container and show print container
                document.querySelector('.container').style.display = 'none';
                document.querySelector('.print-container').style.display = 'block';
                
                // Trigger print
                window.print();
            });
            
            // Handle browser print dialog close
            window.addEventListener('afterprint', function() {
                // Restore original view
                document.querySelector('.print-container').style.display = 'none';
                document.querySelector('.container').style.display = 'block';
            });
        });
    </script>
</body>
</html>