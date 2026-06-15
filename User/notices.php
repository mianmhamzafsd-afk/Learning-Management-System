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

// Fetch notices
// Show: 1) General notices 2) Department-specific notices 3) All active notices
$notices = [];

if ($department) {
    // Get notices for the student's department OR general notices
    $notices_sql = "SELECT n.*, f.name as faculty_name, f.department as faculty_dept 
                   FROM notices n 
                   LEFT JOIN faculty f ON n.faculty_id = f.faculty_id 
                   WHERE (n.category = 'general' OR f.department = ?) 
                   AND n.is_active = 1 
                   ORDER BY n.created_at DESC";
    
    $notices_stmt = $conn->prepare($notices_sql);
    $notices_stmt->bind_param("s", $department);
    $notices_stmt->execute();
    $notices_result = $notices_stmt->get_result();
    
    while ($notice = $notices_result->fetch_assoc()) {
        $notices[] = $notice;
    }
    $notices_stmt->close();
} else {
    // Fallback: get all active notices
    $notices_sql = "SELECT n.*, f.name as faculty_name, f.department as faculty_dept 
                   FROM notices n 
                   LEFT JOIN faculty f ON n.faculty_id = f.faculty_id 
                   WHERE n.is_active = 1 
                   ORDER BY n.created_at DESC";
    
    $notices_result = $conn->query($notices_sql);
    while ($notice = $notices_result->fetch_assoc()) {
        $notices[] = $notice;
    }
}

// Count notices by category
$category_counts = [
    'academic' => 0,
    'administrative' => 0,
    'event' => 0,
    'general' => 0
];

foreach ($notices as $notice) {
    $category = $notice['category'] ?? 'general';
    if (isset($category_counts[$category])) {
        $category_counts[$category]++;
    }
}

// Count unread notices (simple implementation)
$total_notices = count($notices);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Notices - Student Dashboard</title>
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
            max-width: 1200px;
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

        .stat-card.academic .stat-icon {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            border-left-color: #4CAF50;
        }

        .stat-card.administrative .stat-icon {
            background: linear-gradient(135deg, #9C27B0, #6A1B9A);
            border-left-color: #9C27B0;
        }

        .stat-card.event .stat-icon {
            background: linear-gradient(135deg, #FF9800, #EF6C00);
            border-left-color: #FF9800;
        }

        .stat-card.general .stat-icon {
            background: linear-gradient(135deg, #607D8B, #455A64);
            border-left-color: #607D8B;
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

        /* Notices List */
        .notices-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .notice-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .notice-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .notice-card.unread {
            border-left: 4px solid #e74c3c;
        }

        .notice-card.important {
            border-left: 4px solid #FF9800;
        }

        .notice-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 10px;
        }

        .notice-title {
            font-size: 18px;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .notice-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #6c757d;
            font-size: 14px;
        }

        .category-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .category-academic {
            background: #4CAF50;
        }

        .category-administrative {
            background: #9C27B0;
        }

        .category-event {
            background: #FF9800;
        }

        .category-general {
            background: #607D8B;
        }

        .priority-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .priority-high {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .priority-urgent {
            background: #c62828;
            color: white;
        }

        .notice-body {
            padding: 20px;
        }

        .notice-content {
            color: #495057;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .notice-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .notice-date {
            color: #6c757d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notice-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            padding: 6px 15px;
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

        .action-btn.mark-read {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #c8e6c9;
        }

        .action-btn.mark-read:hover {
            background: #4CAF50;
            color: white;
        }

        .action-btn.print {
            background: #fff3e0;
            color: #ef6c00;
            border-color: #ffe0b2;
        }

        .action-btn.print:hover {
            background: #FF9800;
            color: white;
        }

        /* No Notices */
        .no-notices {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }

        .no-notices i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #dee2e6;
        }

        .no-notices h3 {
            margin-bottom: 10px;
            color: #495057;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px;
            border-top: 1px solid #e9ecef;
        }

        .page-btn {
            padding: 8px 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #495057;
            cursor: pointer;
            transition: all 0.3s;
        }

        .page-btn:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .page-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-btn.disabled:hover {
            background: white;
            color: #495057;
            border-color: #ddd;
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
            
            .notice-header {
                flex-direction: column;
            }
            
            .notice-footer {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .notice-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .filters-header {
                flex-direction: column;
                align-items: flex-start;
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
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .notice-meta {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Date Highlight */
        .date-highlight {
            color: #e74c3c;
            font-weight: 600;
        }

        .expired {
            opacity: 0.7;
            background: #f8f9fa;
        }

        /* Print Styles */
        @media print {
            .header, .filters-section, .notice-actions, .pagination, .footer {
                display: none;
            }
            
            .notice-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #000;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-bullhorn"></i> University Notices</h1>
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
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_notices; ?></h3>
                        <p>Total Notices</p>
                    </div>
                </div>

                <div class="stat-card academic">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $category_counts['academic']; ?></h3>
                        <p>Academic</p>
                    </div>
                </div>

                <div class="stat-card administrative">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $category_counts['administrative']; ?></h3>
                        <p>Administrative</p>
                    </div>
                </div>

                <div class="stat-card event">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $category_counts['event']; ?></h3>
                        <p>Events</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <div class="filters-header">
                    <h2 class="section-title">
                        <i class="fas fa-filter"></i> 
                        Filter Notices
                    </h2>
                    <div class="filter-controls">
                        <select class="filter-select" id="categoryFilter">
                            <option value="">All Categories</option>
                            <option value="academic">Academic</option>
                            <option value="administrative">Administrative</option>
                            <option value="event">Events</option>
                            <option value="general">General</option>
                        </select>
                        
                        <select class="filter-select" id="priorityFilter">
                            <option value="">All Priorities</option>
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                        
                        <input type="text" class="search-input" id="searchInput" placeholder="Search notices...">
                        
                        <button class="filter-btn" onclick="applyFilters()">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </div>
                
                <div class="filter-tags">
                    <span class="tag active" data-filter="all">All</span>
                    <span class="tag" data-filter="today">Today</span>
                    <span class="tag" data-filter="week">This Week</span>
                    <span class="tag" data-filter="month">This Month</span>
                    <span class="tag" data-filter="unread">Unread</span>
                    <span class="tag" data-filter="important">Important</span>
                </div>
            </div>

            <!-- Notices List -->
            <div class="notices-list" id="noticesList">
                <?php if (count($notices) > 0): ?>
                    <?php 
                    $current_date = date('Y-m-d');
                    foreach ($notices as $notice): 
                        // Check if notice is expired
                        $is_expired = false;
                        if (!empty($notice['end_date']) && $notice['end_date'] < $current_date) {
                            $is_expired = true;
                        }
                        
                        // Determine if notice is important (high/urgent priority)
                        $is_important = in_array($notice['priority'], ['high', 'urgent']);
                        
                        // Format date
                        $notice_date = date('F d, Y', strtotime($notice['created_at']));
                        $is_today = date('Y-m-d', strtotime($notice['created_at'])) === $current_date;
                    ?>
                        <div class="notice-card <?php echo $is_important ? 'important' : ''; ?> <?php echo $is_expired ? 'expired' : ''; ?>" 
                             data-category="<?php echo htmlspecialchars($notice['category']); ?>"
                             data-priority="<?php echo htmlspecialchars($notice['priority']); ?>"
                             data-date="<?php echo htmlspecialchars($notice['created_at']); ?>"
                             data-important="<?php echo $is_important ? 'true' : 'false'; ?>">
                            <div class="notice-header">
                                <div>
                                    <div class="notice-title">
                                        <?php echo htmlspecialchars($notice['title']); ?>
                                        <?php if ($is_today): ?>
                                            <span class="date-highlight"> (New)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notice-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-user-tie"></i>
                                            <?php echo htmlspecialchars($notice['faculty_name'] ?? 'Administration'); ?>
                                        </span>
                                        <span class="meta-item">
                                            <i class="fas fa-building"></i>
                                            <?php echo htmlspecialchars($notice['faculty_dept'] ?? 'General'); ?>
                                        </span>
                                        <span class="category-badge category-<?php echo htmlspecialchars($notice['category']); ?>">
                                            <?php echo ucfirst($notice['category']); ?>
                                        </span>
                                        <?php if ($notice['priority'] === 'high' || $notice['priority'] === 'urgent'): ?>
                                            <span class="priority-badge priority-<?php echo htmlspecialchars($notice['priority']); ?>">
                                                <?php echo ucfirst($notice['priority']); ?> Priority
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="notice-body">
                                <div class="notice-content">
                                    <?php echo nl2br(htmlspecialchars($notice['content'])); ?>
                                </div>
                                
                                <?php if (!empty($notice['start_date']) || !empty($notice['end_date'])): ?>
                                    <div class="notice-meta">
                                        <?php if (!empty($notice['start_date'])): ?>
                                            <span class="meta-item">
                                                <i class="fas fa-calendar-plus"></i>
                                                From: <?php echo date('M d, Y', strtotime($notice['start_date'])); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($notice['end_date'])): ?>
                                            <span class="meta-item">
                                                <i class="fas fa-calendar-minus"></i>
                                                Until: <?php echo date('M d, Y', strtotime($notice['end_date'])); ?>
                                                <?php if ($is_expired): ?>
                                                    <span class="date-highlight"> (Expired)</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="notice-footer">
                                <div class="notice-date">
                                    <i class="fas fa-clock"></i>
                                    Posted: <?php echo $notice_date; ?>
                                    <?php if ($is_today): ?>
                                        <span class="date-highlight"> • Today</span>
                                    <?php endif; ?>
                                </div>
                                <div class="notice-actions">
                                    <a href="#" class="action-btn mark-read" onclick="markAsRead(this)">
                                        <i class="fas fa-check"></i> Mark as Read
                                    </a>
                                    <button class="action-btn print" onclick="window.print()">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                    <?php if (!empty($notice['attachment'])): ?>
                                        <a href="<?php echo htmlspecialchars($notice['attachment']); ?>" class="action-btn" download>
                                            <i class="fas fa-download"></i> Attachment
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- No Notices Message -->
                    <div class="no-notices">
                        <i class="fas fa-bullhorn"></i>
                        <h3>No Notices Available</h3>
                        <p>There are no notices published for your department at this time.</p>
                        <p>Check back later for updates or contact your department office.</p>
                        <div style="margin-top: 20px;">
                            <a href="index.php" class="action-btn" style="display: inline-flex; width: auto; padding: 10px 20px;">
                                <i class="fas fa-home"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if (count($notices) > 0): ?>
                <div class="pagination">
                    <button class="page-btn disabled">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn">2</button>
                    <button class="page-btn">3</button>
                    <button class="page-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><i class="fas fa-info-circle"></i> Notices are updated regularly. Check back often for important updates.</p>
            <p>&copy; <?php echo date('Y'); ?> University Notice Board System</p>
        </div>
    </div>

    <script>
        // Filter functionality
        function applyFilters() {
            const category = document.getElementById('categoryFilter').value;
            const priority = document.getElementById('priorityFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            document.querySelectorAll('.notice-card').forEach(card => {
                const cardCategory = card.dataset.category;
                const cardPriority = card.dataset.priority;
                const cardTitle = card.querySelector('.notice-title').textContent.toLowerCase();
                const cardContent = card.querySelector('.notice-content').textContent.toLowerCase();
                
                let showCard = true;
                
                // Category filter
                if (category && cardCategory !== category) {
                    showCard = false;
                }
                
                // Priority filter
                if (priority && cardPriority !== priority) {
                    showCard = false;
                }
                
                // Search filter
                if (searchTerm && !cardTitle.includes(searchTerm) && !cardContent.includes(searchTerm)) {
                    showCard = false;
                }
                
                // Tag filter (from active tag)
                const activeTag = document.querySelector('.filter-tags .tag.active');
                if (activeTag && activeTag.dataset.filter !== 'all') {
                    const filter = activeTag.dataset.filter;
                    const cardDate = new Date(card.dataset.date);
                    const today = new Date();
                    
                    switch(filter) {
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
                        case 'unread':
                            // You would need to track read status in your database
                            // This is a simplified version
                            if (!card.classList.contains('unread')) showCard = false;
                            break;
                        case 'important':
                            if (card.dataset.important !== 'true') showCard = false;
                            break;
                    }
                }
                
                card.style.display = showCard ? 'block' : 'none';
            });
            
            // Show/hide no results message
            const visibleCards = document.querySelectorAll('.notice-card[style="display: block"]').length;
            const noNotices = document.querySelector('.no-notices');
            
            if (visibleCards === 0 && noNotices) {
                noNotices.style.display = 'block';
            } else if (noNotices) {
                noNotices.style.display = 'none';
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

        // Mark as read functionality
        function markAsRead(button) {
            const card = button.closest('.notice-card');
            card.classList.remove('unread');
            button.innerHTML = '<i class="fas fa-check-circle"></i> Read';
            button.classList.add('read');
            
            // You would typically send an AJAX request to update the database
            // const noticeId = card.dataset.id;
            // fetch('mark_read.php', {
            //     method: 'POST',
            //     body: JSON.stringify({ notice_id: noticeId })
            // });
            
            // Show success message
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Marked as Read';
            button.style.background = '#4CAF50';
            button.style.color = 'white';
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.background = '';
                button.style.color = '';
            }, 2000);
        }

        // Live search
        document.getElementById('searchInput').addEventListener('input', applyFilters);

        // Auto-refresh notices every 5 minutes
        setInterval(() => {
            // You could implement auto-refresh with AJAX here
            console.log('Checking for new notices...');
        }, 300000); // 5 minutes

        // Initialize with today's notices
        document.addEventListener('DOMContentLoaded', function() {
            // Apply initial filter
            applyFilters();
            
            // Highlight today's notices
            const today = new Date().toISOString().split('T')[0];
            document.querySelectorAll('.notice-card').forEach(card => {
                const noticeDate = card.dataset.date.split(' ')[0];
                if (noticeDate === today) {
                    card.classList.add('unread');
                }
            });
        });
    </script>
</body>
</html>

<?php
// Close database connection at the end
if (isset($conn)) {
    $conn->close();
}
?>