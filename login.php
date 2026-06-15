<?php
session_start();

// Include database connection
include('db_connect.php');

// Initialize variables
$username = $password = $user_type = "";
$error = "";
$success = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $user_type = $_POST['user_type'];
    
    // Validate inputs
    if (empty($username) || empty($password) || empty($user_type)) {
        $error = "All fields are required!";
    } else {
        // Query based on user type
        switch($user_type) {
            case 'admin':
                $table = "admin";
                $redirect = "Admin/index.php";
                break;
            case 'faculty':
                $table = "faculty";
                // Check if this is a focal person
                $sql = "SELECT * FROM $table WHERE username = '$username'";
                $result = mysqli_query($conn, $sql);
                if ($result && mysqli_num_rows($result) == 1) {
                    $user = mysqli_fetch_assoc($result);
                    // Redirect based on focal person status
                    if ($user['is_focal_person'] == 1) {
                        $redirect = "Faculty/Focal/index.php";
                    } else {
                        $redirect = "Faculty/Regular/index.php";
                    }
                }
                break;
            case 'student':
                $table = "students";
                $redirect = "User/index.php";
                break;
            default:
                $error = "Invalid user type!";
                break;
        }
        if (!$error) {
            // Query the database for the username
            $sql = "SELECT * FROM $table WHERE username = '$username'";
            $result = mysqli_query($conn, $sql);
    
            if ($result && mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
        
                // Verify hashed password
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['logged_in'] = true;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user_type;
            
                    // ✅ FIXED: Set CORRECT session variables for faculty
                    if ($user_type === 'admin') {
                        $_SESSION['user_id'] = $user['admin_id'];
                        $_SESSION['admin_id'] = $user['admin_id'];
                        $_SESSION['name'] = $user['admin_name'] ?? $user['username'];
                    } elseif ($user_type === 'faculty') {
                        // FIX: Set BOTH user_id AND faculty_id
                        $_SESSION['user_id'] = $user['faculty_id'];
                        $_SESSION['faculty_id'] = $user['faculty_id'];  // ← THIS IS WHAT'S MISSING!
                        $_SESSION['faculty_name'] = $user['name'] ?? $user['username'];  // ← Set faculty_name too!
                        $_SESSION['name'] = $user['name'] ?? $user['username'];
                        // Store focal person status and department
                        $_SESSION['is_focal_person'] = $user['is_focal_person'];
                        $_SESSION['department'] = $user['department'];
                        $_SESSION['focal_responsibility'] = $user['focal_responsibility'] ?? '';
                    } elseif ($user_type === 'student') {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['name'] = $user['name'] ?? $user['username'];
                    }
            
                    // Redirect to appropriate page
                    header("Location: $redirect");
                    exit();
                } else {
                    $error = "Invalid password!";
                }
            } else {
                $error = "User not found!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS STYLES - EMBEDDED IN PHP FILE */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #1a237e 0%, #4a148c 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(to right, #283593, #4527a0);
            color: white;
            padding: 25px 20px;
            text-align: center;
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .login-header h1 i {
            margin-right: 10px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .login-form {
            padding: 30px;
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .error-message {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .user-type-selector {
            display: flex;
            margin-bottom: 25px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }

        .user-type-btn {
            flex: 1;
            padding: 12px;
            background-color: #f5f5f5;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .user-type-btn.active {
            background-color: #3f51b5;
            color: white;
        }

        .user-type-btn:hover:not(.active) {
            background-color: #e8eaf6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group label i {
            margin-right: 8px;
            color: #3f51b5;
        }

        .input-container {
            position: relative;
        }

        .input-container input {
            width: 100%;
            padding: 14px 45px 14px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }

        .input-container i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
        }

        .input-container input:focus {
            border-color: #3f51b5;
            outline: none;
            box-shadow: 0 0 0 2px rgba(63, 81, 181, 0.2);
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(to right, #3f51b5, #673ab7);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .login-btn:hover {
            background: linear-gradient(to right, #303f9f, #512da8);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(63, 81, 181, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .login-footer p {
            margin-bottom: 8px;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }
            
            .login-form {
                padding: 20px;
            }
            
            .user-type-selector {
                flex-direction: column;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
            
            .user-type-btn span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-graduation-cap"></i> Learning management System</h1>
            <p>Select your role and login</p>
        </div>
        
        <div class="login-form">
            <?php if ($error): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="message success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="user-type-selector">
                    <button type="button" class="user-type-btn active" data-type="admin">
                        <i class="fas fa-user-shield"></i> <span>Admin</span>
                    </button>
                    <button type="button" class="user-type-btn" data-type="faculty">
                        <i class="fas fa-chalkboard-teacher"></i> <span>Faculty</span>
                    </button>
                    <button type="button" class="user-type-btn" data-type="student">
                        <i class="fas fa-user-graduate"></i> <span>Student</span>
                    </button>
                </div>
                
                <input type="hidden" id="user_type" name="user_type" value="admin">
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username</label>
                    <div class="input-container">
                        <input type="text" id="username" name="username" 
                               value="<?php echo htmlspecialchars($username); ?>" 
                               placeholder="Enter your username" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <div class="input-container">
                        <input type="password" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <i class="fas fa-eye" id="togglePassword"></i>
                    </div>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="login-footer">
                <p>Need help? Contact system administrator</p>
                <p>&copy; <?php echo date('Y'); ?> Learning Management System</p>
            </div>
        </div>
    </div>

    <script>
        // User type selector functionality
        document.addEventListener('DOMContentLoaded', function() {
            const userTypeButtons = document.querySelectorAll('.user-type-btn');
            const userTypeInput = document.getElementById('user_type');
            
            userTypeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    userTypeButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Update hidden input value
                    userTypeInput.value = this.getAttribute('data-type');
                });
            });
            
            // Toggle password visibility
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Auto focus on username field
            document.getElementById('username').focus();
            
            // Clear error messages after 5 seconds
            setTimeout(function() {
                const errorMsg = document.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.style.display = 'none';
                }
            }, 5000);
        });
    </script>
</body>
</html>