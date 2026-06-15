<?php
session_start();
include('../db_connect.php');

// Check if user is logged in and is student
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'] ?? 0;
$success_message = '';
$error_message = '';

// Fetch current student data
$sql = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $error_message = "Student record not found!";
    header("Location: index.php");
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $phone = mysqli_real_escape_string($conn, $_POST['phone'] ?? '');
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $department = mysqli_real_escape_string($conn, $_POST['department'] ?? '');
    $course = mysqli_real_escape_string($conn, $_POST['course'] ?? '');
    
    // Handle password change (if provided)
    $password_change = '';
    if (!empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($current_password, $student['password'])) {
            $error_message = "Current password is incorrect!";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long!";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_change = ", password = '$hashed_password'";
        }
    }
    
    // If no error, update the profile
    if (empty($error_message)) {
        // Check if email already exists (excluding current student)
        $check_email_sql = "SELECT id FROM students WHERE email = ? AND id != ?";
        $check_stmt = $conn->prepare($check_email_sql);
        $check_stmt->bind_param("si", $email, $student_id);
        $check_stmt->execute();
        $email_result = $check_stmt->get_result();
        
        if ($email_result->num_rows > 0) {
            $error_message = "Email already exists!";
        } else {
            // Update query
            $update_sql = "UPDATE students SET 
                          name = ?, 
                          email = ?, 
                          phone = ?, 
                          address = ?, 
                          department = ?, 
                          course = ?
                          $password_change
                          WHERE id = ?";
            
            $update_stmt = $conn->prepare($update_sql);
            
            if (!empty($_POST['new_password'])) {
                // With password change
                $update_stmt->bind_param("ssssssi", $name, $email, $phone, $address, $department, $course, $hashed_password, $student_id);
            } else {
                // Without password change
                $update_stmt->bind_param("ssssssi", $name, $email, $phone, $address, $department, $course, $student_id);
            }
            
            if ($update_stmt->execute()) {
                $success_message = "Profile updated successfully!";
                
                // Update session data
                $_SESSION['name'] = $name;
                $_SESSION['username'] = $student['username']; // Keep username same
                
                // Refresh student data
                $sql = "SELECT * FROM students WHERE id = ?";
                $refresh_stmt = $conn->prepare($sql);
                $refresh_stmt->bind_param("i", $student_id);
                $refresh_stmt->execute();
                $refresh_result = $refresh_stmt->get_result();
                $student = $refresh_result->fetch_assoc();
                $refresh_stmt->close();
                
            } else {
                $error_message = "Failed to update profile. Please try again.";
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Student Dashboard</title>
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
            max-width: 900px;
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

        .back-link {
            text-align: left;
            margin-bottom: 20px;
        }

        .back-link a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        /* Messages */
        .message {
            padding: 15px;
            margin: 20px;
            border-radius: 8px;
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

        /* Form Container */
        .form-container {
            padding: 30px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f2f6;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .readonly-field {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
        }

        /* Buttons */
        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f1f2f6;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
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
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            body {
                padding: 10px;
            }
        }

        /* Additional Styles */
        .info-note {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 5px;
            color: #1565c0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 14px;
            padding: 5px;
            border-radius: 4px;
            display: none;
        }

        .strength-weak {
            background: #ffcdd2;
            color: #c62828;
        }

        .strength-medium {
            background: #fff3e0;
            color: #ef6c00;
        }

        .strength-strong {
            background: #e8f5e9;
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
            <p>Update your personal information</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="form-container">
            <form method="POST" action="">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-user-circle"></i> Personal Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($student['name']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email Address *</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($student['email']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Phone Number *</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($student['phone']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="username"><i class="fas fa-user-tag"></i> Username</label>
                            <input type="text" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($student['username']); ?>" 
                                   class="readonly-field" readonly>
                            <div class="info-note">
                                <i class="fas fa-info-circle"></i> Username cannot be changed
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="student_id"><i class="fas fa-id-card"></i> Student ID</label>
                            <input type="text" id="student_id" 
                                   value="<?php echo htmlspecialchars($student['id']); ?>" 
                                   class="readonly-field" readonly>
                        </div>

                        <div class="form-group">
                            <label for="registration_date"><i class="fas fa-calendar-alt"></i> Registration Date</label>
                            <input type="text" id="registration_date" 
                                   value="<?php echo htmlspecialchars($student['registration_date']); ?>" 
                                   class="readonly-field" readonly>
                        </div>
                    </div>
                </div>

                <!-- Academic Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-graduation-cap"></i> Academic Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="department"><i class="fas fa-building"></i> Department *</label>
                            <input type="text" id="department" name="department" 
                                   value="<?php echo htmlspecialchars($student['department']); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="course"><i class="fas fa-book"></i> Course *</label>
                            <input type="text" id="course" name="course" 
                                   value="<?php echo htmlspecialchars($student['course']); ?>" 
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-address-book"></i> Contact Information</h2>
                    <div class="form-group">
                        <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Change Password Section -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-lock"></i> Change Password</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="current_password"><i class="fas fa-key"></i> Current Password</label>
                            <div class="password-toggle">
                                <input type="password" id="current_password" name="current_password">
                                <i class="fas fa-eye" onclick="togglePassword('current_password')"></i>
                            </div>
                            <div class="info-note">
                                <i class="fas fa-info-circle"></i> Leave empty if you don't want to change password
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="new_password"><i class="fas fa-key"></i> New Password</label>
                            <div class="password-toggle">
                                <input type="password" id="new_password" name="new_password" 
                                       onkeyup="checkPasswordStrength(this.value)">
                                <i class="fas fa-eye" onclick="togglePassword('new_password')"></i>
                            </div>
                            <div id="passwordStrength" class="password-strength"></div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-key"></i> Confirm New Password</label>
                            <div class="password-toggle">
                                <input type="password" id="confirm_password" name="confirm_password">
                                <i class="fas fa-eye" onclick="togglePassword('confirm_password')"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Buttons -->
                <div class="form-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                    
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    
                    <a href="profile.php" class="btn" style="background: #17a2b8; color: white;">
                        <i class="fas fa-eye"></i> View Profile
                    </a>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><i class="fas fa-user-shield"></i> Your information is secured and private</p>
            <p>&copy; <?php echo date('Y'); ?> Student Portal</p>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Check password strength
        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.style.display = 'none';
                return;
            }
            
            let strength = 0;
            let message = '';
            let className = '';
            
            // Check length
            if (password.length >= 8) strength++;
            // Check for mixed case
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            // Check for numbers
            if (/\d/.test(password)) strength++;
            // Check for special characters
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength <= 1) {
                message = 'Weak password';
                className = 'strength-weak';
            } else if (strength <= 3) {
                message = 'Medium password';
                className = 'strength-medium';
            } else {
                message = 'Strong password';
                className = 'strength-strong';
            }
            
            strengthDiv.textContent = message;
            strengthDiv.className = 'password-strength ' + className;
            strengthDiv.style.display = 'block';
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            
            // If new password is entered, current password must be entered
            if (newPassword && !currentPassword) {
                e.preventDefault();
                alert('Please enter your current password to change it.');
                document.getElementById('current_password').focus();
                return;
            }
            
            // If new password is entered, it must match confirmation
            if (newPassword && newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                document.getElementById('confirm_password').focus();
                return;
            }
            
            // If new password is entered, it must be at least 6 characters
            if (newPassword && newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long!');
                document.getElementById('new_password').focus();
                return;
            }
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