<?php
session_start();

// Check if user is logged in as faculty and is focal person
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../../login.php");
    exit();
}

// Try different paths for database connection
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
$faculty_name = $_SESSION['faculty_name'] ?? $_SESSION['user_name'] ?? 'Focal Person';
$department = $_SESSION['department'] ?? 'Department';

// Get faculty details and verify focal person status
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ? AND is_focal_person = 1";
$faculty_stmt = $conn->prepare($faculty_query);
$faculty_stmt->bind_param("i", $faculty_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
$faculty_data = $faculty_result->fetch_assoc();
$faculty_stmt->close();

// If not focal person, redirect to regular faculty dashboard
if (!$faculty_data) {
    header("Location: ../Regular/faculty_dashboard.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $notification_type = $_POST['notification_type'] ?? 'info';
    $target_url = trim($_POST['target_url'] ?? '');
    
    // Validate inputs
    if (empty($title)) {
        $error_message = "Title is required!";
    } elseif (empty($message)) {
        $error_message = "Message is required!";
    } elseif (strlen($title) > 255) {
        $error_message = "Title must be less than 255 characters!";
    } else {
        // Insert notification
        $insert_query = "INSERT INTO notifications (faculty_id, title, message, notification_type, target_url) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        
        if (!$stmt) {
            $error_message = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("issss", $faculty_id, $title, $message, $notification_type, $target_url);
            
            if ($stmt->execute()) {
                $success_message = "Notification sent successfully!";
                // Clear form fields
                $_POST = [];
            } else {
                $error_message = "Failed to send notification: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Notification - Focal Person Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7209b7;
            --primary-dark: #560bad;
            --secondary: #4361ee;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #ff9e00;
            --info: #4cc9f0;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
            --gray-light: #e9ecef;
            --border-radius: 12px;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e0c3fc 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        /* Header */
        .header {
            background: white;
            padding: 15px 30px;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
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

        .logo i {
            font-size: 24px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }

        .user-details {
            text-align: right;
        }

        .user-details h4 {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
        }

        .user-details p {
            font-size: 12px;
            color: var(--gray);
            margin: 2px 0 0 0;
        }

        .user-details small {
            font-size: 11px;
            color: var(--primary);
            font-weight: 500;
            background: #f3e5f5;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }

        .logout-btn {
            background: var(--gray-light);
            color: var(--dark);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: var(--danger);
            color: white;
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 30px;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border-left: 5px solid var(--danger);
        }

        .page-header h1 {
            color: var(--dark);
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header p {
            color: var(--gray);
            margin: 0;
        }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .card-title {
            color: var(--dark);
            font-size: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-light);
        }

        .card-title i {
            color: var(--danger);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: var(--primary);
            width: 20px;
        }

        .form-control, .form-select {
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 15px;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(114, 9, 183, 0.1);
        }

        .form-control.is-invalid, .form-select.is-invalid {
            border-color: var(--danger);
        }

        .form-text {
            font-size: 13px;
            color: var(--gray);
            margin-top: 5px;
        }

        /* Notification Type Badges */
        .notification-type-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .notification-type-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            background: var(--light);
            border: 2px solid var(--gray-light);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            user-select: none;
        }

        .notification-type-option:hover {
            background: #f0f2f5;
        }

        .notification-type-option input {
            display: none;
        }

        .notification-type-option input:checked + .notification-type-label {
            font-weight: 600;
        }

        .notification-type-option.info input:checked + .notification-type-label {
            color: var(--info);
        }

        .notification-type-option.warning input:checked + .notification-type-label {
            color: var(--warning);
        }

        .notification-type-option.success input:checked + .notification-type-label {
            color: var(--success);
        }

        .notification-type-option.error input:checked + .notification-type-label {
            color: var(--danger);
        }

        .notification-type-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .notification-type-label i {
            font-size: 16px;
        }

        /* Message Counter */
        .message-counter {
            font-size: 12px;
            color: var(--gray);
            text-align: right;
            margin-top: 5px;
        }

        /* Button Styles */
        .btn-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid var(--gray-light);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 500;
            font-size: 15px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(114, 9, 183, 0.3);
        }

        .btn-secondary {
            background: var(--gray-light);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 500;
            font-size: 15px;
            color: var(--dark);
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-secondary:hover {
            background: #dee2e6;
            color: var(--dark);
            transform: translateY(-2px);
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 25px;
            background: white;
            margin-top: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            color: var(--gray);
            font-size: 14px;
        }

        .footer p {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .footer i {
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .notification-type-options {
                flex-direction: column;
            }
            
            .btn-container {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .main-container {
                padding: 0 15px;
            }
            
            .content-card {
                padding: 20px;
            }
            
            .page-header h1 {
                font-size: 20px;
            }
        }

        /* Character Count Warning */
        .text-warning {
            color: var(--warning) !important;
        }

        .text-danger {
            color: var(--danger) !important;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
                <i class="fas fa-user-shield"></i>
                <span>Focal Person Portal</span>
            </a>
            
            <div class="user-info">
                <div class="avatar">
                    <?php echo strtoupper(substr($faculty_name, 0, 2)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($faculty_name); ?></h4>
                    <p><?php echo htmlspecialchars($department); ?> Department</p>
                    <small>Focal Person</small>
                </div>
                <a href="../../login.php?logout=true" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> Send Notification</h1>
            <p>Create and send notifications to department members and students.</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Notification Form -->
        <div class="content-card">
            <div class="card-title">
                <i class="fas fa-bell"></i>
                <span>Notification Details</span>
            </div>

            <form id="notificationForm" method="POST" action="">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-heading"></i>
                        <span>Title *</span>
                    </label>
                    <input type="text" 
                           class="form-control" 
                           name="title" 
                           id="title"
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                           placeholder="Enter notification title"
                           maxlength="255"
                           required>
                    <div class="form-text">Maximum 255 characters</div>
                    <div id="titleCounter" class="message-counter">0/255</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i>
                        <span>Message *</span>
                    </label>
                    <textarea class="form-control" 
                              name="message" 
                              id="message"
                              rows="6"
                              placeholder="Enter notification message"
                              required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    <div id="messageCounter" class="message-counter">0 characters</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag"></i>
                        <span>Notification Type</span>
                    </label>
                    <div class="notification-type-options">
                        <label class="notification-type-option info">
                            <input type="radio" name="notification_type" value="info" checked>
                            <span class="notification-type-label">
                                <i class="fas fa-info-circle"></i>
                                <span>Information</span>
                            </span>
                        </label>
                        
                        <label class="notification-type-option warning">
                            <input type="radio" name="notification_type" value="warning">
                            <span class="notification-type-label">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Warning</span>
                            </span>
                        </label>
                        
                        <label class="notification-type-option success">
                            <input type="radio" name="notification_type" value="success">
                            <span class="notification-type-label">
                                <i class="fas fa-check-circle"></i>
                                <span>Success</span>
                            </span>
                        </label>
                        
                        <label class="notification-type-option error">
                            <input type="radio" name="notification_type" value="error">
                            <span class="notification-type-label">
                                <i class="fas fa-times-circle"></i>
                                <span>Error</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-link"></i>
                        <span>Target URL (Optional)</span>
                    </label>
                    <input type="url" 
                           class="form-control" 
                           name="target_url" 
                           id="target_url"
                           value="<?php echo htmlspecialchars($_POST['target_url'] ?? ''); ?>"
                           placeholder="https://example.com/page">
                    <div class="form-text">Optional link for users to click on the notification</div>
                </div>

                <div class="btn-container">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        <span>Send Notification</span>
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Dashboard</span>
                    </a>
                    <a href="view_notifications.php" class="btn btn-secondary">
                        <i class="fas fa-eye"></i>
                        <span>View Notifications</span>
                    </a>
                </div>
            </form>
        </div>

        <!-- Notification Preview -->
        <div class="content-card">
            <div class="card-title">
                <i class="fas fa-eye"></i>
                <span>Notification Preview</span>
            </div>
            
            <div id="notificationPreview" class="p-4 border rounded" style="background: var(--light);">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div id="previewIcon" class="rounded-circle p-3" style="background: var(--info); color: white;">
                        <i class="fas fa-info-circle fa-lg"></i>
                    </div>
                    <div>
                        <h4 id="previewTitle" class="mb-1" style="color: var(--dark);">Your notification title will appear here</h4>
                        <small id="previewTime" class="text-muted">Just now</small>
                    </div>
                </div>
                <p id="previewMessage" class="mb-0" style="color: var(--gray);">
                    Your notification message will appear here. This is how it will look to recipients.
                </p>
                <div id="previewLink" class="mt-3" style="display: none;">
                    <a href="#" class="text-decoration-none" style="color: var(--primary);">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Click here for more information</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>
                <i class="fas fa-info-circle"></i> 
                Notifications will be visible to department members and students.
            </p>
            <p>&copy; <?php echo date('Y'); ?> University Portal - Focal Person View</p>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const titleInput = document.getElementById('title');
            const messageInput = document.getElementById('message');
            const targetUrlInput = document.getElementById('target_url');
            const titleCounter = document.getElementById('titleCounter');
            const messageCounter = document.getElementById('messageCounter');
            const notificationTypeRadios = document.querySelectorAll('input[name="notification_type"]');
            
            // Preview elements
            const previewTitle = document.getElementById('previewTitle');
            const previewMessage = document.getElementById('previewMessage');
            const previewIcon = document.getElementById('previewIcon');
            const previewLink = document.getElementById('previewLink');
            const previewLinkAnchor = previewLink.querySelector('a');
            
            // Color mapping for notification types
            const typeColors = {
                'info': {bg: 'var(--info)', icon: 'fa-info-circle'},
                'warning': {bg: 'var(--warning)', icon: 'fa-exclamation-triangle'},
                'success': {bg: 'var(--success)', icon: 'fa-check-circle'},
                'error': {bg: 'var(--danger)', icon: 'fa-times-circle'}
            };
            
            // Update character counters
            function updateCounters() {
                const titleLength = titleInput.value.length;
                const messageLength = messageInput.value.length;
                
                titleCounter.textContent = `${titleLength}/255`;
                messageCounter.textContent = `${messageLength} characters`;
                
                // Change color based on length
                if (titleLength > 200) {
                    titleCounter.className = 'message-counter text-warning';
                } else if (titleLength > 250) {
                    titleCounter.className = 'message-counter text-danger';
                } else {
                    titleCounter.className = 'message-counter';
                }
                
                if (messageLength > 1000) {
                    messageCounter.className = 'message-counter text-warning';
                } else if (messageLength > 1500) {
                    messageCounter.className = 'message-counter text-danger';
                } else {
                    messageCounter.className = 'message-counter';
                }
            }
            
            // Update preview
            function updatePreview() {
                // Update title
                previewTitle.textContent = titleInput.value || 'Your notification title will appear here';
                
                // Update message
                previewMessage.textContent = messageInput.value || 'Your notification message will appear here. This is how it will look to recipients.';
                
                // Update type styling
                const selectedType = document.querySelector('input[name="notification_type"]:checked').value;
                const typeColor = typeColors[selectedType];
                
                previewIcon.style.background = typeColor.bg;
                previewIcon.innerHTML = `<i class="fas ${typeColor.icon} fa-lg"></i>`;
                
                // Update link
                if (targetUrlInput.value) {
                    previewLink.style.display = 'block';
                    previewLinkAnchor.href = targetUrlInput.value;
                    previewLinkAnchor.innerHTML = `<i class="fas fa-external-link-alt"></i> <span>Click here for more information</span>`;
                } else {
                    previewLink.style.display = 'none';
                }
            }
            
            // Event listeners
            titleInput.addEventListener('input', function() {
                updateCounters();
                updatePreview();
            });
            
            messageInput.addEventListener('input', function() {
                updateCounters();
                updatePreview();
            });
            
            targetUrlInput.addEventListener('input', updatePreview);
            
            notificationTypeRadios.forEach(radio => {
                radio.addEventListener('change', updatePreview);
            });
            
            // Form validation
            const form = document.getElementById('notificationForm');
            form.addEventListener('submit', function(e) {
                const title = titleInput.value.trim();
                const message = messageInput.value.trim();
                
                if (!title) {
                    e.preventDefault();
                    alert('Please enter a title for the notification.');
                    titleInput.focus();
                    return;
                }
                
                if (!message) {
                    e.preventDefault();
                    alert('Please enter a message for the notification.');
                    messageInput.focus();
                    return;
                }
                
                if (title.length > 255) {
                    e.preventDefault();
                    alert('Title must be less than 255 characters.');
                    titleInput.focus();
                    return;
                }
            });
            
            // Initialize counters and preview
            updateCounters();
            updatePreview();
            
            // Set current time in preview
            const now = new Date();
            const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            document.getElementById('previewTime').textContent = `Today at ${timeString}`;
        });
    </script>
</body>
</html>