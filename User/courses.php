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

// Fetch courses for the student's department
$department = $student['department'] ?? '';
$courses = [];
$faculty_info = [];

if ($department) {
    // First, get all courses (you need to create a courses table)
    $courses_sql = "SELECT * FROM courses WHERE department = ? OR department = 'General' ORDER BY course_code";
    $courses_stmt = $conn->prepare($courses_sql);
    $courses_stmt->bind_param("s", $department);
    $courses_stmt->execute();
    $courses_result = $courses_stmt->get_result();
    
    while ($course = $courses_result->fetch_assoc()) {
        $courses[] = $course;
    }
    $courses_stmt->close();
    
    // Get faculty for each course
    foreach ($courses as &$course) {
        if (!empty($course['faculty_id'])) {
            $faculty_sql = "SELECT name, email, designation FROM faculty WHERE faculty_id = ?";
            $faculty_stmt = $conn->prepare($faculty_sql);
            $faculty_stmt->bind_param("i", $course['faculty_id']);
            $faculty_stmt->execute();
            $faculty_result = $faculty_stmt->get_result();
            $faculty_info[$course['course_id']] = $faculty_result->fetch_assoc();
            $faculty_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Student Dashboard</title>
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

        /* Student Info Card */
        .student-card {
            background: linear-gradient(135deg, #4CAF50, #2E7D32);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .student-details h3 {
            font-size: 22px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-stats {
            display: flex;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 8px;
            min-width: 120px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Courses Grid */
        .courses-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 20px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .filter-btn:hover:not(.active) {
            background: #e9ecef;
        }

        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .course-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #e9ecef;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .course-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 20px;
            position: relative;
        }

        .course-code {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .course-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .course-credits {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        .course-body {
            padding: 20px;
        }

        .course-info {
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

        .course-actions {
            display: flex;
            gap: 10px;
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
        }

        .action-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #495057;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .action-btn:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .action-btn.danger {
            background: #ffeaea;
            border-color: #ffcdd2;
            color: #c62828;
        }

        .action-btn.danger:hover {
            background: #e74c3c;
            color: white;
            border-color: #e74c3c;
        }

        /* No Courses Message */
        .no-courses {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }

        .no-courses i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #dee2e6;
        }

        .no-courses h3 {
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
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .student-card {
                flex-direction: column;
                text-align: center;
            }
            
            .student-stats {
                justify-content: center;
            }
            
            .courses-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters {
                width: 100%;
                justify-content: center;
            }
            
            .nav-links {
                flex-direction: column;
                gap: 15px;
            }
        }

        /* Semester Progress */
        .semester-progress {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            margin-top: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, #4CAF50, #2E7D32);
            border-radius: 5px;
            width: 65%; /* Example progress */
            transition: width 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-book-open"></i> My Courses</h1>
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
            <!-- Student Info Card -->
            <div class="student-card">
                <div class="student-details">
                    <h3><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student['name']); ?></h3>
                    <p><?php echo htmlspecialchars($student['department']); ?> • <?php echo htmlspecialchars($student['course']); ?></p>
                </div>
                <div class="student-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo count($courses); ?></div>
                        <div class="stat-label">Total Courses</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">15</div>
                        <div class="stat-label">Credits</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">3.8</div>
                        <div class="stat-label">GPA</div>
                    </div>
                </div>
            </div>

            <!-- Semester Progress -->
            <div class="semester-progress">
                <h3 class="section-title"><i class="fas fa-chart-line"></i> Semester Progress</h3>
                <p>Spring 2024 • Week 8 of 16</p>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>

            <!-- Courses Header -->
            <div class="courses-header">
                <h2 class="section-title">
                    <i class="fas fa-list-alt"></i> 
                    <?php echo count($courses); ?> Courses Enrolled
                </h2>
                <div class="filters">
                    <button class="filter-btn active">All</button>
                    <button class="filter-btn">Current</button>
                    <button class="filter-btn">Completed</button>
                    <button class="filter-btn">Electives</button>
                </div>
            </div>

            <!-- Courses Grid -->
            <?php if (count($courses) > 0): ?>
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <div class="course-code"><?php echo htmlspecialchars($course['course_code'] ?? 'CSC101'); ?></div>
                                <div class="course-title"><?php echo htmlspecialchars($course['course_name'] ?? 'Computer Science Fundamentals'); ?></div>
                                <div class="course-credits"><?php echo htmlspecialchars($course['credits'] ?? '3'); ?> Credits</div>
                            </div>
                            <div class="course-body">
                                <div class="course-info">
                                    <div class="info-row">
                                        <i class="fas fa-chalkboard-teacher"></i>
                                        <span>
                                            <?php 
                                            if (isset($faculty_info[$course['course_id']])) {
                                                echo htmlspecialchars($faculty_info[$course['course_id']]['name'] . ' (' . $faculty_info[$course['course_id']]['designation'] . ')');
                                            } else {
                                                echo 'Dr. Smith (Professor)';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?php echo htmlspecialchars($course['semester'] ?? 'Spring 2024'); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <i class="fas fa-clock"></i>
                                        <span>Mon/Wed/Fri • 10:00 AM - 11:30 AM</span>
                                    </div>
                                    <div class="info-row">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span>Room <?php echo htmlspecialchars($course['room'] ?? 'CS-101'); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <i class="fas fa-chart-bar"></i>
                                        <span>Grade: <?php echo htmlspecialchars($course['grade'] ?? 'A-'); ?></span>
                                    </div>
                                </div>
                                <div class="course-actions">
                                    <a href="course_details.php?id=<?php echo $course['course_id'] ?? '1'; ?>" class="action-btn">
                                        <i class="fas fa-info-circle"></i> Details
                                    </a>
                                    <a href="assignments.php?course=<?php echo $course['course_id'] ?? '1'; ?>" class="action-btn">
                                        <i class="fas fa-tasks"></i> Assignments
                                    </a>
                                    <a href="resources.php?course=<?php echo $course['course_id'] ?? '1'; ?>" class="action-btn">
                                        <i class="fas fa-download"></i> Resources
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- No Courses Message -->
                <div class="no-courses">
                    <i class="fas fa-book"></i>
                    <h3>No Courses Found</h3>
                    <p>You are not enrolled in any courses for this semester.</p>
                    <p>Please contact your department administrator to enroll in courses.</p>
                    <div style="margin-top: 20px;">
                        <a href="index.php" class="action-btn" style="display: inline-flex; width: auto; padding: 10px 20px;">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><i class="fas fa-graduation-cap"></i> Academic Portal • Current Semester: Spring 2024</p>
            <p>&copy; <?php echo date('Y'); ?> University Course Management System</p>
        </div>
    </div>

    <script>
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Add filter logic here
                const filter = this.textContent.toLowerCase();
                // You would filter courses based on the selected filter
            });
        });

        // Course search functionality
        const searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.placeholder = 'Search courses...';
        searchInput.style.cssText = 'padding: 8px 15px; border: 1px solid #ddd; border-radius: 5px; width: 250px;';
        
        document.querySelector('.courses-header').insertBefore(searchInput, document.querySelector('.filters'));
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.course-card').forEach(card => {
                const title = card.querySelector('.course-title').textContent.toLowerCase();
                const code = card.querySelector('.course-code').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || code.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s';
                setTimeout(() => msg.style.display = 'none', 500);
            });
        }, 5000);
    </script>
</body>
</html>

<?php
// Close database connection at the end
if (isset($conn)) {
    $conn->close();
}
?>