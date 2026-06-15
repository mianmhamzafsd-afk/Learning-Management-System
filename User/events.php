<?php
session_start();
include('../db_connect.php');

// Check if user is logged in and is student
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'] ?? 0;

// Fetch student details
$student_sql = "SELECT * FROM students WHERE id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();
$student_stmt->close();

// Get student's department
$department = $student['department'] ?? '';

// Fetch events
// Show: 1) Department events 2) General events 3) All active events
$events = [];
$current_date = date('Y-m-d');

if ($department) {
    // Get events for the student's department OR general events
    $events_sql = "SELECT e.*, f.name as faculty_name, f.department as faculty_dept,
                          (SELECT COUNT(*) FROM event_registrations er 
                           WHERE er.event_id = e.id AND er.student_id = ?) as is_registered
                   FROM events e 
                   LEFT JOIN faculty f ON e.faculty_id = f.faculty_id 
                   WHERE (f.department = ? OR f.department IS NULL) 
                   AND e.is_active = 1
                   ORDER BY e.event_date ASC, e.event_time ASC";
    
    $events_stmt = $conn->prepare($events_sql);
    $events_stmt->bind_param("is", $student_id, $department);
    $events_stmt->execute();
    $events_result = $events_stmt->get_result();
    
    while ($event = $events_result->fetch_assoc()) {
        $events[] = $event;
    }
    $events_stmt->close();
} else {
    // Fallback: get all active events
    $events_sql = "SELECT e.*, f.name as faculty_name, f.department as faculty_dept,
                          (SELECT COUNT(*) FROM event_registrations er 
                           WHERE er.event_id = e.id AND er.student_id = ?) as is_registered
                   FROM events e 
                   LEFT JOIN faculty f ON e.faculty_id = f.faculty_id 
                   WHERE e.is_active = 1
                   ORDER BY e.event_date ASC, e.event_time ASC";
    
    $events_stmt = $conn->prepare($events_sql);
    $events_stmt->bind_param("i", $student_id);
    $events_stmt->execute();
    $events_result = $events_stmt->get_result();
    
    while ($event = $events_result->fetch_assoc()) {
        $events[] = $event;
    }
    $events_stmt->close();
}

// Categorize events
$upcoming_events = [];
$ongoing_events = [];
$past_events = [];

foreach ($events as $event) {
    $event_date = $event['event_date'];
    
    if ($event_date > $current_date) {
        $upcoming_events[] = $event;
    } elseif ($event_date == $current_date) {
        $ongoing_events[] = $event;
    } else {
        $past_events[] = $event;
    }
}

// Count events
$total_events = count($events);
$total_upcoming = count($upcoming_events);
$total_ongoing = count($ongoing_events);
$total_past = count($past_events);

// Count by event type
$event_type_counts = [
    'conference' => 0,
    'workshop' => 0,
    'seminar' => 0,
    'meeting' => 0,
    'cultural' => 0,
    'sports' => 0
];

foreach ($events as $event) {
    $type = $event['event_type'] ?? 'meeting';
    if (isset($event_type_counts[$type])) {
        $event_type_counts[$type]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Events - Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        /* Header */
        .header {
            background: linear-gradient(to right, #2c3e50, #3498db);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .nav-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .back-link a, .student-info a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            transition: background 0.3s;
        }

        .back-link a:hover, .student-info a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Main Content */
        .main-content {
            padding: 30px;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid #3498db;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
        }

        .stat-card.upcoming .stat-icon {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            border-left-color: #4CAF50;
        }

        .stat-card.ongoing .stat-icon {
            background: linear-gradient(135deg, #FF9800, #EF6C00);
            border-left-color: #FF9800;
        }

        .stat-card.past .stat-icon {
            background: linear-gradient(135deg, #607D8B, #455A64);
            border-left-color: #607D8B;
        }

        .stat-card.registered .stat-icon {
            background: linear-gradient(135deg, #9C27B0, #6A1B9A);
            border-left-color: #9C27B0;
        }

        .stat-info h3 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #6c757d;
            font-size: 14px;
        }

        /* Calendar View */
        .calendar-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
        }

        @media (max-width: 992px) {
            .calendar-section {
                grid-template-columns: 1fr;
            }
        }

        .mini-calendar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .calendar-header h3 {
            color: #2c3e50;
            font-size: 18px;
        }

        .calendar-nav {
            display: flex;
            gap: 10px;
        }

        .calendar-nav button {
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin-bottom: 15px;
        }

        .calendar-day {
            text-align: center;
            font-weight: 600;
            color: #6c757d;
            padding: 5px;
            font-size: 14px;
        }

        .calendar-date {
            text-align: center;
            padding: 8px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .calendar-date:hover {
            background: #e9ecef;
        }

        .calendar-date.today {
            background: #3498db;
            color: white;
        }

        .calendar-date.has-event {
            background: #e8f4ff;
            border: 1px solid #3498db;
        }

        .calendar-date.has-event::after {
            content: '';
            position: absolute;
            bottom: 2px;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 4px;
            background: #e74c3c;
            border-radius: 50%;
        }

        .calendar-date.other-month {
            color: #adb5bd;
        }

        .calendar-events {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .calendar-events h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .event-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .event-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .event-list-item:last-child {
            border-bottom: none;
        }

        .event-list-time {
            font-weight: 600;
            color: #2c3e50;
            min-width: 80px;
        }

        .event-list-title {
            flex: 1;
            color: #495057;
        }

        .event-list-type {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #e8f4ff;
            color: #3498db;
        }

        /* Filters */
        .filters-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .section-title {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
        }

        .filter-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-select, .search-input {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .search-input {
            min-width: 250px;
        }

        .filter-btn {
            padding: 8px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-btn:hover {
            background: #2980b9;
        }

        .filter-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .tag {
            padding: 5px 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tag:hover, .tag.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        /* Events Tabs */
        .events-tabs {
            margin-bottom: 30px;
        }

        .tab-buttons {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 12px 30px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            color: #6c757d;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn:hover {
            color: #3498db;
        }

        .tab-btn.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .tab-content.active {
            display: block;
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .event-card.upcoming {
            border-top: 4px solid #4CAF50;
        }

        .event-card.ongoing {
            border-top: 4px solid #FF9800;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 5px 20px rgba(255, 152, 0, 0.1); }
            50% { box-shadow: 0 5px 20px rgba(255, 152, 0, 0.3); }
            100% { box-shadow: 0 5px 20px rgba(255, 152, 0, 0.1); }
        }

        .event-card.past {
            border-top: 4px solid #607D8B;
            opacity: 0.9;
        }

        .event-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            z-index: 1;
        }

        .badge-upcoming {
            background: #4CAF50;
        }

        .badge-ongoing {
            background: #FF9800;
        }

        .badge-past {
            background: #607D8B;
        }

        .event-header {
            padding: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
        }

        .event-date {
            font-size: 14px;
            margin-bottom: 5px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .event-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .event-type {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .event-body {
            padding: 20px;
        }

        .event-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-row i {
            width: 20px;
            color: #3498db;
        }

        .info-row span {
            color: #495057;
        }

        .event-description {
            color: #6c757d;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .event-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .event-stats {
            display: flex;
            gap: 15px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #6c757d;
            font-size: 14px;
        }

        .event-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 8px 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #495057;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .action-btn:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .action-btn.registered {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }

        .action-btn.registered:hover {
            background: #388E3C;
        }

        .action-btn.full {
            background: #f44336;
            color: white;
            border-color: #f44336;
            cursor: not-allowed;
        }

        .action-btn.disabled {
            background: #e9ecef;
            color: #adb5bd;
            border-color: #dee2e6;
            cursor: not-allowed;
        }

        .action-btn.disabled:hover {
            background: #e9ecef;
            color: #adb5bd;
            border-color: #dee2e6;
        }

        /* No Events */
        .no-events {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
            grid-column: 1 / -1;
        }

        .no-events i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #dee2e6;
        }

        .no-events h3 {
            margin-bottom: 10px;
            color: #495057;
        }

        /* Footer */
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            border-top: 1px solid #dee2e6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .event-footer {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .event-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .filter-controls {
                width: 100%;
            }
            
            .search-input {
                min-width: 100%;
            }
            
            .nav-links {
                flex-direction: column;
                gap: 15px;
            }
            
            .tab-buttons {
                overflow-x: auto;
                white-space: nowrap;
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .event-stats {
                flex-direction: column;
                gap: 5px;
            }
        }

        /* Progress Bar */
        .registration-progress {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .progress-bar {
            flex: 1;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, #4CAF50, #2E7D32);
            border-radius: 4px;
        }

        .progress-text {
            font-size: 12px;
            color: #6c757d;
            min-width: 60px;
        }

        /* Countdown */
        .countdown {
            display: flex;
            gap: 5px;
            margin-top: 10px;
            justify-content: center;
        }

        .countdown-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 5px;
            border-radius: 5px;
            min-width: 40px;
        }

        .countdown-value {
            font-size: 18px;
            font-weight: bold;
            color: white;
        }

        .countdown-label {
            font-size: 10px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-calendar-alt"></i> University Events</h1>
            <div class="nav-links">
                <div class="back-link">
                    <a href="index.php">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
                <div class="student-info">
                    <a href="profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_events; ?></h3>
                        <p>Total Events</p>
                    </div>
                </div>

                <div class="stat-card upcoming">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_upcoming; ?></h3>
                        <p>Upcoming</p>
                    </div>
                </div>

                <div class="stat-card ongoing">
                    <div class="stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_ongoing; ?></h3>
                        <p>Ongoing</p>
                    </div>
                </div>

                <div class="stat-card past">
                    <div class="stat-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_past; ?></h3>
                        <p>Past Events</p>
                    </div>
                </div>
            </div>

            <!-- Calendar Section -->
            <div class="calendar-section">
                <div class="mini-calendar">
                    <div class="calendar-header">
                        <h3><i class="fas fa-calendar"></i> Event Calendar</h3>
                        <div class="calendar-nav">
                            <button id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                            <button id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div id="calendarContainer"></div>
                    <div class="event-list">
                        <h4><i class="fas fa-list"></i> Today's Events</h4>
                        <?php if (count($ongoing_events) > 0): ?>
                            <?php foreach ($ongoing_events as $event): ?>
                                <div class="event-list-item">
                                    <div class="event-list-time">
                                        <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                                    </div>
                                    <div class="event-list-title">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </div>
                                    <div class="event-list-type">
                                        <?php echo ucfirst($event['event_type']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #6c757d; font-size: 14px; padding: 10px 0;">No events today</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="calendar-events">
                    <h3><i class="fas fa-star"></i> Featured Events</h3>
                    <?php if (count($upcoming_events) > 0): ?>
                        <?php foreach (array_slice($upcoming_events, 0, 3) as $event): ?>
                            <div class="event-card upcoming" style="margin-bottom: 15px; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                                <div class="event-header">
                                    <div class="event-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                                    </div>
                                    <div class="event-title" style="font-size: 16px;">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </div>
                                    <div class="event-type">
                                        <?php echo ucfirst($event['event_type']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #6c757d; text-align: center; padding: 20px;">
                            <i class="fas fa-calendar-times"></i> No upcoming featured events
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <div class="filters-header">
                    <h2 class="section-title">
                        <i class="fas fa-filter"></i> 
                        Filter Events
                    </h2>
                    <div class="filter-controls">
                        <select class="filter-select" id="typeFilter">
                            <option value="">All Types</option>
                            <option value="conference">Conference</option>
                            <option value="workshop">Workshop</option>
                            <option value="seminar">Seminar</option>
                            <option value="meeting">Meeting</option>
                            <option value="cultural">Cultural</option>
                            <option value="sports">Sports</option>
                        </select>
                        
                        <select class="filter-select" id="dateFilter">
                            <option value="">All Dates</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="past">Past</option>
                        </select>
                        
                        <input type="text" class="search-input" id="searchInput" placeholder="Search events...">
                        
                        <button class="filter-btn" onclick="applyFilters()">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                
                <div class="filter-tags">
                    <span class="tag active" data-filter="all">All Events</span>
                    <span class="tag" data-filter="registered">Registered</span>
                    <span class="tag" data-filter="available">Available</span>
                    <span class="tag" data-filter="featured">Featured</span>
                    <span class="tag" data-filter="department">My Department</span>
                </div>
            </div>

            <!-- Events Tabs -->
            <div class="events-tabs">
                <div class="tab-buttons">
                    <button class="tab-btn active" data-tab="upcoming">
                        <i class="fas fa-clock"></i> Upcoming (<?php echo $total_upcoming; ?>)
                    </button>
                    <button class="tab-btn" data-tab="ongoing">
                        <i class="fas fa-play-circle"></i> Ongoing (<?php echo $total_ongoing; ?>)
                    </button>
                    <button class="tab-btn" data-tab="past">
                        <i class="fas fa-history"></i> Past (<?php echo $total_past; ?>)
                    </button>
                </div>

                <!-- Upcoming Events Tab -->
                <div class="tab-content active" id="upcomingTab">
                    <div class="events-grid">
                        <?php if (count($upcoming_events) > 0): ?>
                            <?php foreach ($upcoming_events as $event): ?>
                                <?php
                                $event_date = $event['event_date'];
                                $event_time = $event['event_time'];
                                $is_registered = $event['is_registered'] > 0;
                                $spots_left = $event['max_participants'] > 0 ? 
                                    ($event['max_participants'] - ($event['current_participants'] ?? 0)) : null;
                                $is_full = $spots_left !== null && $spots_left <= 0;
                                $progress_percentage = $event['max_participants'] > 0 ? 
                                    min(100, round((($event['current_participants'] ?? 0) / $event['max_participants']) * 100)) : 0;
                                ?>
                                <div class="event-card upcoming" 
                                     data-type="<?php echo htmlspecialchars($event['event_type']); ?>"
                                     data-date="<?php echo htmlspecialchars($event_date); ?>"
                                     data-registered="<?php echo $is_registered ? 'true' : 'false'; ?>"
                                     data-spots="<?php echo $spots_left; ?>">
                                    <div class="event-badge badge-upcoming">Upcoming</div>
                                    
                                    <div class="event-header">
                                        <div class="event-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('F d, Y', strtotime($event_date)); ?>
                                            <?php if ($event_time): ?>
                                                • <i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($event_time)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-title">
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </div>
                                        <div class="event-type">
                                            <?php echo ucfirst($event['event_type']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="event-body">
                                        <div class="event-info">
                                            <div class="info-row">
                                                <i class="fas fa-user-tie"></i>
                                                <span>Organizer: <?php echo htmlspecialchars($event['faculty_name'] ?? 'University'); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span>Venue: <?php echo htmlspecialchars($event['venue'] ?? 'To be announced'); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <i class="fas fa-users"></i>
                                                <span>Department: <?php echo htmlspecialchars($event['faculty_dept'] ?? 'General'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($event['description'])): ?>
                                            <div class="event-description">
                                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($event['max_participants'] > 0): ?>
                                            <div class="registration-progress">
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                                                </div>
                                                <div class="progress-text">
                                                    <?php echo $spots_left; ?> spots left
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($event_time): ?>
                                            <div class="countdown" id="countdown-<?php echo $event['id']; ?>">
                                                <!-- Countdown will be populated by JavaScript -->
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="event-footer">
                                        <div class="event-stats">
                                            <div class="stat">
                                                <i class="fas fa-users"></i>
                                                <span><?php echo $event['current_participants'] ?? 0; ?> registered</span>
                                            </div>
                                            <?php if ($event['max_participants'] > 0): ?>
                                                <div class="stat">
                                                    <i class="fas fa-ticket-alt"></i>
                                                    <span>Max: <?php echo $event['max_participants']; ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-actions">
                                            <?php if ($is_full): ?>
                                                <button class="action-btn full" disabled>
                                                    <i class="fas fa-times-circle"></i> Full
                                                </button>
                                            <?php elseif ($is_registered): ?>
                                                <button class="action-btn registered" onclick="unregisterEvent(<?php echo $event['id']; ?>)">
                                                    <i class="fas fa-check-circle"></i> Registered
                                                </button>
                                            <?php else: ?>
                                                <button class="action-btn" onclick="registerEvent(<?php echo $event['id']; ?>)">
                                                    <i class="fas fa-user-plus"></i> Register
                                                </button>
                                            <?php endif; ?>
                                            <button class="action-btn" onclick="viewEventDetails(<?php echo $event['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
                                            <button class="action-btn" onclick="shareEvent(<?php echo $event['id']; ?>)">
                                                <i class="fas fa-share-alt"></i> Share
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-events">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Upcoming Events</h3>
                                <p>There are no upcoming events scheduled at this time.</p>
                                <p>Check back later for new events.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ongoing Events Tab -->
                <div class="tab-content" id="ongoingTab">
                    <div class="events-grid">
                        <?php if (count($ongoing_events) > 0): ?>
                            <?php foreach ($ongoing_events as $event): ?>
                                <?php
                                $is_registered = $event['is_registered'] > 0;
                                ?>
                                <div class="event-card ongoing">
                                    <div class="event-badge badge-ongoing">Happening Now</div>
                                    
                                    <div class="event-header">
                                        <div class="event-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                                            <?php if ($event['event_time']): ?>
                                                • <i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($event['event_time'])); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-title">
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </div>
                                        <div class="event-type">
                                            <?php echo ucfirst($event['event_type']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="event-body">
                                        <div class="event-info">
                                            <div class="info-row">
                                                <i class="fas fa-user-tie"></i>
                                                <span>Organizer: <?php echo htmlspecialchars($event['faculty_name'] ?? 'University'); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span>Venue: <?php echo htmlspecialchars($event['venue'] ?? 'To be announced'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($event['description'])): ?>
                                            <div class="event-description">
                                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="event-footer">
                                        <div class="event-stats">
                                            <div class="stat">
                                                <i class="fas fa-users"></i>
                                                <span><?php echo $event['current_participants'] ?? 0; ?> attending</span>
                                            </div>
                                        </div>
                                        <div class="event-actions">
                                            <?php if ($is_registered): ?>
                                                <button class="action-btn registered">
                                                    <i class="fas fa-check-circle"></i> Attending
                                                </button>
                                            <?php else: ?>
                                                <button class="action-btn disabled" disabled>
                                                    <i class="fas fa-clock"></i> Event Started
                                                </button>
                                            <?php endif; ?>
                                            <button class="action-btn" onclick="viewEventDetails(<?php echo $event['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-events">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Ongoing Events</h3>
                                <p>There are no events happening right now.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Past Events Tab -->
                <div class="tab-content" id="pastTab">
                    <div class="events-grid">
                        <?php if (count($past_events) > 0): ?>
                            <?php foreach ($past_events as $event): ?>
                                <?php
                                $is_registered = $event['is_registered'] > 0;
                                ?>
                                <div class="event-card past">
                                    <div class="event-badge badge-past">Completed</div>
                                    
                                    <div class="event-header">
                                        <div class="event-date">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('F d, Y', strtotime($event['event_date'])); ?>
                                        </div>
                                        <div class="event-title">
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </div>
                                        <div class="event-type">
                                            <?php echo ucfirst($event['event_type']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="event-body">
                                        <div class="event-info">
                                            <div class="info-row">
                                                <i class="fas fa-user-tie"></i>
                                                <span>Organizer: <?php echo htmlspecialchars($event['faculty_name'] ?? 'University'); ?></span>
                                            </div>
                                            <div class="info-row">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span>Venue: <?php echo htmlspecialchars($event['venue'] ?? 'To be announced'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($event['description'])): ?>
                                            <div class="event-description">
                                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="event-footer">
                                        <div class="event-stats">
                                            <div class="stat">
                                                <i class="fas fa-users"></i>
                                                <span><?php echo $event['current_participants'] ?? 0; ?> attended</span>
                                            </div>
                                        </div>
                                        <div class="event-actions">
                                            <?php if ($is_registered): ?>
                                                <button class="action-btn registered">
                                                    <i class="fas fa-check-circle"></i> Attended
                                                </button>
                                            <?php endif; ?>
                                            <button class="action-btn" onclick="viewEventDetails(<?php echo $event['id']; ?>)">
                                                <i class="fas fa-info-circle"></i> View Details
                                            </button>
                                            <button class="action-btn" onclick="downloadCertificate(<?php echo $event['id']; ?>)">
                                                <i class="fas fa-certificate"></i> Certificate
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-events">
                                <i class="fas fa-calendar-times"></i>
                                <h3>No Past Events</h3>
                                <p>No past events are available in the system.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><i class="fas fa-calendar-alt"></i> Events are updated regularly. Register early to secure your spot!</p>
            <p>&copy; <?php echo date('Y'); ?> University Events Management System</p>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                
                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Show active tab content
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tabId + 'Tab').classList.add('active');
            });
        });

        // Filter functionality
        function applyFilters() {
            const type = document.getElementById('typeFilter').value;
            const dateFilter = document.getElementById('dateFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            const activeTab = document.querySelector('.tab-btn.active').dataset.tab;
            const currentTab = document.getElementById(activeTab + 'Tab');
            
            currentTab.querySelectorAll('.event-card').forEach(card => {
                const cardType = card.dataset.type;
                const cardDate = new Date(card.dataset.date);
                const cardTitle = card.querySelector('.event-title').textContent.toLowerCase();
                const cardDescription = card.querySelector('.event-description')?.textContent.toLowerCase() || '';
                const isRegistered = card.dataset.registered === 'true';
                const spotsLeft = parseInt(card.dataset.spots) || 999;
                
                let showCard = true;
                
                // Type filter
                if (type && cardType !== type) {
                    showCard = false;
                }
                
                // Date filter
                if (dateFilter) {
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    switch(dateFilter) {
                        case 'today':
                            if (cardDate.toDateString() !== today.toDateString()) showCard = false;
                            break;
                        case 'week':
                            const weekAgo = new Date(today);
                            weekAgo.setDate(today.getDate() - 7);
                            if (cardDate < weekAgo) showCard = false;
                            break;
                        case 'month':
                            const monthAgo = new Date(today);
                            monthAgo.setMonth(today.getMonth() - 1);
                            if (cardDate < monthAgo) showCard = false;
                            break;
                        case 'upcoming':
                            if (cardDate < today) showCard = false;
                            break;
                        case 'past':
                            if (cardDate >= today) showCard = false;
                            break;
                    }
                }
                
                // Search filter
                if (searchTerm && !cardTitle.includes(searchTerm) && !cardDescription.includes(searchTerm)) {
                    showCard = false;
                }
                
                // Tag filter
                const activeTag = document.querySelector('.filter-tags .tag.active');
                if (activeTag && activeTag.dataset.filter !== 'all') {
                    const filter = activeTag.dataset.filter;
                    
                    switch(filter) {
                        case 'registered':
                            if (!isRegistered) showCard = false;
                            break;
                        case 'available':
                            if (isRegistered || spotsLeft <= 0) showCard = false;
                            break;
                        case 'featured':
                            // You would need a featured flag in your database
                            // This is a simplified version
                            if (!cardTitle.includes('featured')) showCard = false;
                            break;
                        case 'department':
                            // Filter by student's department
                            // This would require additional data
                            break;
                    }
                }
                
                card.style.display = showCard ? 'block' : 'none';
            });
            
            // Show/hide no events message
            const visibleCards = currentTab.querySelectorAll('.event-card[style="display: block"]').length;
            const noEvents = currentTab.querySelector('.no-events');
            
            if (visibleCards === 0 && !noEvents) {
                const grid = currentTab.querySelector('.events-grid');
                const noEventsMsg = document.createElement('div');
                noEventsMsg.className = 'no-events';
                noEventsMsg.innerHTML = `
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Events Found</h3>
                    <p>No events match your filter criteria.</p>
                `;
                grid.appendChild(noEventsMsg);
            } else if (noEvents && visibleCards > 0) {
                noEvents.remove();
            }
        }

        // Tag filter
        document.querySelectorAll('.filter-tags .tag').forEach(tag => {
            tag.addEventListener('click', function() {
                document.querySelectorAll('.filter-tags .tag').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                applyFilters();
            });
        });

        // Live search
        document.getElementById('searchInput').addEventListener('input', applyFilters);

        // Event registration
        function registerEvent(eventId) {
            if (confirm('Are you sure you want to register for this event?')) {
                // AJAX call to register
                fetch('register_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ event_id: eventId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Successfully registered for the event!');
                        location.reload();
                    } else {
                        alert('Registration failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Registration failed. Please try again.');
                });
            }
        }

        function unregisterEvent(eventId) {
            if (confirm('Are you sure you want to cancel your registration?')) {
                // AJAX call to unregister
                fetch('unregister_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ event_id: eventId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Successfully cancelled registration!');
                        location.reload();
                    } else {
                        alert('Cancellation failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Cancellation failed. Please try again.');
                });
            }
        }

        function viewEventDetails(eventId) {
            // Open event details modal or page
            window.location.href = `event_details.php?id=${eventId}`;
        }

        function shareEvent(eventId) {
            if (navigator.share) {
                navigator.share({
                    title: 'University Event',
                    text: 'Check out this university event!',
                    url: window.location.origin + '/event_details.php?id=' + eventId
                });
            } else {
                // Fallback for browsers that don't support Web Share API
                const shareUrl = window.location.origin + '/event_details.php?id=' + eventId;
                navigator.clipboard.writeText(shareUrl).then(() => {
                    alert('Event link copied to clipboard!');
                });
            }
        }

        function downloadCertificate(eventId) {
            window.open(`certificate.php?event_id=${eventId}`, '_blank');
        }

        // Calendar functionality
        let currentDate = new Date();

        function renderCalendar() {
            const container = document.getElementById('calendarContainer');
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            
            // Get first day of month and total days
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();
            const startingDay = firstDay.getDay();
            
            // Month names
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                               'July', 'August', 'September', 'October', 'November', 'December'];
            
            // Day names
            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            
            // Create calendar grid
            let calendarHTML = '<div class="calendar-grid">';
            
            // Day headers
            dayNames.forEach(day => {
                calendarHTML += `<div class="calendar-day">${day}</div>`;
            });
            
            // Previous month's days
            const prevMonthLastDay = new Date(year, month, 0).getDate();
            for (let i = 0; i < startingDay; i++) {
                const day = prevMonthLastDay - startingDay + i + 1;
                calendarHTML += `<div class="calendar-date other-month">${day}</div>`;
            }
            
            // Current month's days
            const today = new Date();
            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const isToday = date.toDateString() === today.toDateString();
                const hasEvent = hasEventOnDate(date);
                
                let classes = 'calendar-date';
                if (isToday) classes += ' today';
                if (hasEvent) classes += ' has-event';
                
                calendarHTML += `<div class="${classes}" data-date="${date.toISOString().split('T')[0]}">${day}</div>`;
            }
            
            // Next month's days
            const totalCells = 42; // 6 weeks
            const remainingCells = totalCells - (startingDay + daysInMonth);
            for (let day = 1; day <= remainingCells; day++) {
                calendarHTML += `<div class="calendar-date other-month">${day}</div>`;
            }
            
            calendarHTML += '</div>';
            container.innerHTML = calendarHTML;
            
            // Add click event to dates
            container.querySelectorAll('.calendar-date:not(.other-month)').forEach(dateCell => {
                dateCell.addEventListener('click', function() {
                    const selectedDate = this.dataset.date;
                    filterByDate(selectedDate);
                });
            });
        }

        function hasEventOnDate(date) {
            const dateStr = date.toISOString().split('T')[0];
            return <?php echo json_encode(array_column($events, 'event_date')); ?>.some(eventDate => eventDate === dateStr);
        }

        function filterByDate(selectedDate) {
            const dateFilter = document.getElementById('dateFilter');
            dateFilter.value = 'custom';
            
            // Filter events for selected date
            document.querySelectorAll('.event-card').forEach(card => {
                const cardDate = card.dataset.date;
                card.style.display = cardDate === selectedDate ? 'block' : 'none';
            });
            
            // Switch to appropriate tab based on date
            const selected = new Date(selectedDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selected > today) {
                document.querySelector('[data-tab="upcoming"]').click();
            } else if (selected.toDateString() === today.toDateString()) {
                document.querySelector('[data-tab="ongoing"]').click();
            } else {
                document.querySelector('[data-tab="past"]').click();
            }
        }

        // Initialize calendar
        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });

        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });

        renderCalendar();

        // Countdown timers for upcoming events
        function updateCountdowns() {
            document.querySelectorAll('.event-card.upcoming').forEach(card => {
                const dateStr = card.dataset.date;
                const timeStr = card.querySelector('.event-time')?.textContent;
                
                if (dateStr && timeStr) {
                    const eventDate = new Date(dateStr + ' ' + timeStr);
                    const now = new Date();
                    const diff = eventDate - now;
                    
                    if (diff > 0) {
                        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                        
                        const countdownId = 'countdown-' + card.dataset.id;
                        const countdownEl = document.getElementById(countdownId);
                        if (countdownEl) {
                            countdownEl.innerHTML = `
                                <div class="countdown-item">
                                    <div class="countdown-value">${days}</div>
                                    <div class="countdown-label">Days</div>
                                </div>
                                <div class="countdown-item">
                                    <div class="countdown-value">${hours}</div>
                                    <div class="countdown-label">Hours</div>
                                </div>
                                <div class="countdown-item">
                                    <div class="countdown-value">${minutes}</div>
                                    <div class="countdown-label">Mins</div>
                                </div>
                                <div class="countdown-item">
                                    <div class="countdown-value">${seconds}</div>
                                    <div class="countdown-label">Secs</div>
                                </div>
                            `;
                        }
                    }
                }
            });
        }

        // Update countdowns every second
        setInterval(updateCountdowns, 1000);
        updateCountdowns();

        // Auto-refresh events every 5 minutes
        setInterval(() => {
            console.log('Checking for new events...');
            // You could implement auto-refresh with AJAX here
        }, 300000);
    </script>
</body>
</html>

<?php
// Close database connection at the end
if (isset($conn)) {
    $conn->close();
}
?>