<?php
session_start();

// Check if user is logged in as faculty and is focal person
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../../login.php");
    exit();
}

// Include database connection
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
$faculty_name = $_SESSION['name'] ?? 'Focal Person';
$department = $_SESSION['department'] ?? 'Department';

// Verify focal person status
$faculty_query = "SELECT * FROM faculty WHERE faculty_id = ? AND is_focal_person = 1";
$faculty_stmt = $conn->prepare($faculty_query);
$faculty_stmt->bind_param("i", $faculty_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
$faculty_data = $faculty_result->fetch_assoc();
$faculty_stmt->close();

if (!$faculty_data) {
    header("Location: ../Regular/faculty_dashboard.php");
    exit();
}

$error = "";
$success = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['type'] ?? 'news';
    
    // Validation
    if (empty($title)) {
        $error = "News title is required!";
    } elseif (empty($content)) {
        $error = "News content is required!";
    } else {
        // Handle file upload
        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['attachment']['type'], $allowed_types)) {
                $error = "Invalid file type. Allowed types: JPG, PNG, GIF, PDF, DOC, DOCX";
            } elseif ($_FILES['attachment']['size'] > $max_size) {
                $error = "File is too large. Maximum size is 5MB";
            } else {
                $upload_dir = '../../uploads/news/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                $file_name = 'news_' . time() . '_' . $faculty_id . '.' . $file_ext;
                $upload_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
                    $attachment_path = $upload_path;
                } else {
                    $error = "Failed to upload file";
                }
            }
        }
        
        if (!$error) {
            // Insert news
            $stmt = $conn->prepare("INSERT INTO news_updates (faculty_id, title, content, type, attachment) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $faculty_id, $title, $content, $type, $attachment_path);
            
            if ($stmt->execute()) {
                $success = "News posted successfully!";
                // Clear form
                $_POST = [];
            } else {
                $error = "Error posting news: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$news_types = ['news', 'update'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add News - Focal Person Portal</title>
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

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: white;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            box-shadow: var(--shadow);
            padding-top: 90px;
            z-index: 999;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover {
            background: var(--gray-light);
            border-left: 4px solid var(--primary);
        }

        .sidebar-menu a.active {
            background: var(--primary);
            color: white;
            border-left: 4px solid var(--warning);
        }

        .sidebar-menu a i {
            width: 20px;
            text-align: center;
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

        /* Main Content */
        .main-container {
            max-width: 1000px;
            margin: 30px auto 30px 280px;
            padding: 0 30px;
        }

        /* Form Container */
        .form-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .page-title {
            color: var(--dark);
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-subtitle {
            color: var(--gray);
            margin-bottom: 30px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius);
            font-size: 15px;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(114, 9, 183, 0.1);
            outline: none;
        }

        .form-textarea {
            min-height: 200px;
            resize: vertical;
        }

        /* File Upload */
        .file-upload {
            border: 2px dashed var(--gray-light);
            border-radius: var(--border-radius);
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-upload:hover {
            border-color: var(--primary);
            background: rgba(114, 9, 183, 0.05);
        }

        .file-upload i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-name {
            margin-top: 10px;
            font-size: 14px;
            color: var(--gray);
        }

        .file-info {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(114, 9, 183, 0.3);
        }

        .btn-secondary {
            background: var(--gray-light);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: #d1d9e0;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        /* Messages */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
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

        /* Preview Image */
        .preview-container {
            margin-top: 20px;
            text-align: center;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .sidebar-menu a span {
                display: none;
            }
            
            .main-container {
                margin-left: 90px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                padding-top: 20px;
                margin-bottom: 20px;
            }
            
            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            .sidebar-menu a {
                padding: 10px 15px;
                border-radius: 8px;
                border-left: none;
                border-bottom: 3px solid transparent;
            }
            
            .main-container {
                margin-left: 0;
                padding: 0 15px;
            }
        }

        @media (max-width: 576px) {
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .file-upload {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Menu -->
    <div class="sidebar">
        <div class="sidebar-menu">
            <a href="index.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="add_event.php">
                <i class="fas fa-calendar-plus"></i>
                <span>Add Event</span>
            </a>
            <a href="add_notice.php">
                <i class="fas fa-bullhorn"></i>
                <span>Add Notice</span>
            </a>
            <a href="add_news.php" class="active">
                <i class="fas fa-newspaper"></i>
                <span>Add News</span>
            </a>
            <a href="view_events.php">
                <i class="fas fa-calendar-alt"></i>
                <span>View Events</span>
            </a>
            <a href="view_notices.php">
                <i class="fas fa-clipboard-list"></i>
                <span>View Notices</span>
            </a>
            <a href="view_news.php">
                <i class="fas fa-newspaper"></i>
                <span>View News</span>
            </a>
            <a href="view_notifications.php">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
        </div>
    </div>

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
                    <h4 style="margin: 0; font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($faculty_name); ?></h4>
                    <p style="margin: 0; font-size: 12px; color: var(--gray);"><?php echo htmlspecialchars($department); ?> Department</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Messages -->
        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="form-container">
            <h1 class="page-title">
                <i class="fas fa-newspaper"></i> Post News Update
            </h1>
            <p class="page-subtitle">Share news and updates with the <?php echo htmlspecialchars($department); ?> department</p>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-heading"></i> News Title
                    </label>
                    <input type="text" name="title" class="form-control" placeholder="Enter news title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i> News Content
                    </label>
                    <textarea name="content" class="form-control form-textarea" placeholder="Enter news content" required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-tag"></i> Type
                    </label>
                    <select name="type" class="form-select" required>
                        <option value="">Select type</option>
                        <?php foreach($news_types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo ($_POST['type'] ?? 'news') == $type ? 'selected' : ''; ?>>
                                <?php echo ucfirst($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-paperclip"></i> Attachment (Optional)
                    </label>
                    <div class="file-upload" id="fileUpload">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click to upload attachment</p>
                        <p class="file-info">Max size: 5MB | Allowed: Images, PDF, DOC, DOCX</p>
                        <input type="file" name="attachment" id="attachmentInput" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                        <div class="file-name" id="fileName">No file selected</div>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Publish News
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>
                <i class="fas fa-info-circle"></i> 
                Sharing news for <?php echo htmlspecialchars($department); ?> department
            </p>
            <p>&copy; <?php echo date('Y'); ?> University Portal - Focal Person View</p>
        </footer>
    </div>

    <script>
        // File upload handling
        document.addEventListener('DOMContentLoaded', function() {
            const fileUpload = document.getElementById('fileUpload');
            const fileInput = document.getElementById('attachmentInput');
            const fileName = document.getElementById('fileName');
            
            fileUpload.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileName.textContent = this.files[0].name;
                    
                    // Show image preview if image file
                    const file = this.files[0];
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            // Remove existing preview
                            const existingPreview = document.querySelector('.preview-container');
                            if (existingPreview) {
                                existingPreview.remove();
                            }
                            
                            // Create new preview
                            const previewContainer = document.createElement('div');
                            previewContainer.className = 'preview-container';
                            previewContainer.innerHTML = `
                                <p>Preview:</p>
                                <img src="${e.target.result}" class="preview-image" alt="Preview">
                            `;
                            fileUpload.parentNode.insertBefore(previewContainer, fileUpload.nextSibling);
                        };
                        reader.readAsDataURL(file);
                    } else {
                        // Remove image preview for non-image files
                        const existingPreview = document.querySelector('.preview-container');
                        if (existingPreview) {
                            existingPreview.remove();
                        }
                    }
                } else {
                    fileName.textContent = 'No file selected';
                }
            });
            
            // Remove file on click of file name
            fileName.addEventListener('click', function(e) {
                e.stopPropagation();
                fileInput.value = '';
                fileName.textContent = 'No file selected';
                
                // Remove preview
                const existingPreview = document.querySelector('.preview-container');
                if (existingPreview) {
                    existingPreview.remove();
                }
            });
        });
    </script>
</body>
</html>