<?php
session_start();
include('../db_connect.php');

// Check if user is logged in and is student
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student ID from session
$student_id = $_SESSION['user_id'] ?? 0;

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_picture'])) {
    $upload_dir = 'uploads/profile_pictures/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = $_FILES['profile_picture']['name'];
    $file_tmp = $_FILES['profile_picture']['tmp_name'];
    $file_size = $_FILES['profile_picture']['size'];
    $file_error = $_FILES['profile_picture']['error'];
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($file_ext, $allowed_ext)) {
        if ($file_error === 0) {
            if ($file_size <= 5242880) { // 5MB max
                // Generate unique filename
                $new_file_name = 'student_' . $student_id . '_' . time() . '.' . $file_ext;
                $file_destination = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $file_destination)) {
                    // Update database with profile picture path
                    $sql = "UPDATE students SET profile_picture = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("si", $new_file_name, $student_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Profile picture updated successfully!";
                        // Update session with new picture
                        $_SESSION['profile_picture'] = $new_file_name;
                    } else {
                        $error_message = "Failed to update database.";
                    }
                    $stmt->close();
                } else {
                    $error_message = "Failed to upload file.";
                }
            } else {
                $error_message = "File is too large. Maximum size is 5MB.";
            }
        } else {
            $error_message = "Error uploading file.";
        }
    } else {
        $error_message = "Invalid file type. Allowed: JPG, JPEG, PNG, GIF.";
    }
}

// Fetch student data
$sql = "SELECT *, 
        IF(profile_picture IS NOT NULL, CONCAT('uploads/profile_pictures/', profile_picture), NULL) as profile_pic_url 
        FROM students WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $student = $result->fetch_assoc();
} else {
    $error = "Student record not found!";
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - MYPR System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS for Student Dashboard */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        /* Header */
        .dashboard-header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container h1 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-container h1 i {
            color: #3498db;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-pic-container {
            position: relative;
            width: 60px;
            height: 60px;
        }

        .profile-pic {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3498db;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .profile-pic:hover {
            transform: scale(1.05);
        }

        .change-pic-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-details h3 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .user-details p {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Messages */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin: 20px auto;
            max-width: 800px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Main Content */
        .dashboard-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Welcome Card */
        .welcome-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .welcome-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #3498db;
        }

        .welcome-text h2 {
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .welcome-text p {
            color: #7f8c8d;
            line-height: 1.6;
        }

        /* Info Cards Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }

        .info-card:hover {
            transform: translateY(-5px);
        }

        .info-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f2f6;
        }

        .info-icon {
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

        .info-header h3 {
            color: #2c3e50;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f2f6;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #7f8c8d;
            font-weight: 500;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
        }

        /* Profile Picture Upload Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            animation: modalSlide 0.3s ease;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f2f6;
        }

        .modal-header h3 {
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: #7f8c8d;
            cursor: pointer;
        }

        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .file-input-container {
            border: 2px dashed #3498db;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-input-container:hover {
            background: #f8f9fa;
        }

        .file-input-container i {
            font-size: 48px;
            color: #3498db;
            margin-bottom: 15px;
        }

        .file-input-container p {
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .file-input-container input[type="file"] {
            display: none;
        }

        .preview-container {
            text-align: center;
            display: none;
        }

        #imagePreview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin: 0 auto 15px;
            display: block;
        }

        .upload-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: background 0.3s;
        }

        .upload-btn:hover {
            background: #2980b9;
        }

        /* Action Buttons */
        .action-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .section-title {
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f2f6;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 30px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
            border: none;
            cursor: pointer;
            min-width: 180px;
        }

        .btn:hover {
            background: #2980b9;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn.logout {
            background: #e74c3c;
        }

        .btn.logout:hover {
            background: #c0392b;
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .btn.profile {
            background: #2ecc71;
        }

        .btn.profile:hover {
            background: #27ae60;
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }

        /* Footer */
        .dashboard-footer {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            margin-top: 50px;
        }

        .dashboard-footer p {
            opacity: 0.9;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .welcome-card {
                flex-direction: column;
                text-align: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="dashboard-header">
        <div class="logo-container">
            <h1><i class="fas fa-graduation-cap"></i> Student Dashboard</h1>
        </div>
        <div class="user-info">
            <div class="profile-pic-container">
                <img src="<?php echo !empty($student['profile_pic_url']) ? $student['profile_pic_url'] : 'https://via.placeholder.com/60'; ?>" 
                     alt="Profile Picture" 
                     class="profile-pic"
                     onclick="openModal()">
                <button class="change-pic-btn" onclick="openModal()">
                    <i class="fas fa-camera"></i>
                </button>
            </div>
            <div class="user-details">
                <h3><?php echo htmlspecialchars($_SESSION['name'] ?? 'Student'); ?></h3>
                <p><i class="fas fa-user-tag"></i> <?php echo ucfirst($_SESSION['role']); ?></p>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($success_message)): ?>
        <div class="message success-message">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="message error-message">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="dashboard-container">
        <?php if (isset($error)): ?>
            <!-- Error Message -->
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                <a href="../login.php" class="btn">Go Back to Login</a>
            </div>
        <?php elseif (isset($student)): ?>
            <!-- Welcome Card -->
            <div class="welcome-card">
                <img src="<?php echo !empty($student['profile_pic_url']) ? $student['profile_pic_url'] : 'https://via.placeholder.com/120'; ?>" 
                     alt="Profile Picture" 
                     class="welcome-pic"
                     onclick="openModal()">
                <div class="welcome-text">
                    <h2><i class="fas fa-hand-wave"></i> Welcome, <?php echo htmlspecialchars($student['name']); ?>!</h2>
                    <p>Welcome to your student dashboard. Here you can view and update your profile information.</p>
                    <button class="btn" onclick="openModal()" style="margin-top: 15px;">
                        <i class="fas fa-camera"></i> Change Profile Picture
                    </button>
                </div>
            </div>

            <!-- Student Information Grid -->
            <div class="info-grid">
                <!-- Personal Info Card -->
                <div class="info-card">
                    <div class="info-header">
                        <div class="info-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h3>Personal Information</h3>
                    </div>
                    <div class="info-content">
                        <div class="info-item">
                            <span class="info-label">Student ID:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['id']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Username:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['username']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['name']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Contact Info Card -->
                <div class="info-card">
                    <div class="info-header">
                        <div class="info-icon">
                            <i class="fas fa-address-book"></i>
                        </div>
                        <h3>Contact Information</h3>
                    </div>
                    <div class="info-content">
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['phone']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['address'] ?? 'Not provided'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Academic Info Card -->
                <div class="info-card">
                    <div class="info-header">
                        <div class="info-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h3>Academic Information</h3>
                    </div>
                    <div class="info-content">
                        <div class="info-item">
                            <span class="info-label">Department:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['department']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Course:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['course']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Registration Date:</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['registration_date']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-section">
                <h3 class="section-title"><i class="fas fa-cogs"></i> Quick Actions</h3>
                <div class="action-buttons">
                    <a href="edit_profile.php" class="btn profile">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                    <a href="logout.php" class="btn logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="dashboard-footer">
        <p><i class="fas fa-copyright"></i> <?php echo date('Y'); ?> MYPR System. All rights reserved.</p>
        <p>Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?> | Student ID: <?php echo htmlspecialchars($student['id'] ?? 'N/A'); ?></p>
    </div>

    <!-- Profile Picture Upload Modal -->
    <div class="modal-overlay" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Upload Profile Picture</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" 
                  enctype="multipart/form-data" class="upload-form">
                <div class="file-input-container" onclick="document.getElementById('profilePicture').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to select a file</p>
                    <p class="file-info">Max size: 5MB | Formats: JPG, JPEG, PNG, GIF</p>
                    <input type="file" id="profilePicture" name="profile_picture" accept="image/*" onchange="previewImage(event)" required>
                </div>
                <div class="preview-container" id="previewContainer">
                    <img id="imagePreview" src="#" alt="Preview">
                    <p>Image Preview</p>
                </div>
                <button type="submit" class="upload-btn">
                    <i class="fas fa-upload"></i> Upload Picture
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal Functions
        function openModal() {
            document.getElementById('uploadModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('uploadModal').style.display = 'none';
            resetPreview();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('uploadModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Image Preview Function
        function previewImage(event) {
            const input = event.target;
            const previewContainer = document.getElementById('previewContainer');
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        function resetPreview() {
            document.getElementById('profilePicture').value = '';
            document.getElementById('previewContainer').style.display = 'none';
            document.getElementById('imagePreview').src = '#';
        }

        // Auto logout warning (30 minutes)
        let idleTime = 0;
        
        function resetIdleTime() {
            idleTime = 0;
        }
        
        setInterval(function() {
            idleTime++;
            if (idleTime > 29) {
                alert('You will be logged out due to inactivity. Please save your work.');
            }
            if (idleTime > 30) {
                window.location.href = 'logout.php';
            }
        }, 60000);
        
        // Reset idle time on user activity
        document.addEventListener('mousemove', resetIdleTime);
        document.addEventListener('keypress', resetIdleTime);
        document.addEventListener('click', resetIdleTime);
        
        // Confirm logout
        document.querySelectorAll('.btn.logout').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.display = 'none';
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