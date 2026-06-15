<?php
session_start();
include('../db_connect.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student ID - check both possible session variable names
if (isset($_SESSION['student_id'])) {
    $student_id = $_SESSION['student_id'];
} elseif (isset($_SESSION['user_id'])) {
    $student_id = $_SESSION['user_id'];
} else {
    header("Location: ../login.php");
    exit();
}

$sql = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $student = $result->fetch_assoc();
} else {
    $error = "Student record not found!";
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Profile</title>
    <link rel="stylesheet" href="Css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .info-item strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 5px;
        }
        
        .links {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn.logout {
            background: #e74c3c;
        }
        
        .btn.logout:hover {
            background: #c0392b;
        }
        
        .error {
            color: #e74c3c;
            background: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($student)): ?>
            <h2><i class="fas fa-user-circle"></i> Student Profile</h2>

            <div class="profile-info">
                <div class="info-item">
                    <strong><i class="fas fa-user"></i> Full Name:</strong>
                    <?= htmlspecialchars($student['name']) ?>
                </div>
                
                <div class="info-item">
                    <strong><i class="fas fa-envelope"></i> Email:</strong>
                    <?= htmlspecialchars($student['email']) ?>
                </div>
                
                <div class="info-item">
                    <strong><i class="fas fa-phone"></i> Phone:</strong>
                    <?= htmlspecialchars($student['phone']) ?>
                </div>
                
                <div class="info-item">
                    <strong><i class="fas fa-building"></i> Department:</strong>
                    <?= htmlspecialchars($student['department']) ?>
                </div>
                
                <div class="info-item">
                    <strong><i class="fas fa-book"></i> Course:</strong>
                    <?= htmlspecialchars($student['course']) ?>
                </div>
                
                <div class="info-item">
                    <strong><i class="fas fa-map-marker-alt"></i> Address:</strong>
                    <?= htmlspecialchars($student['address'] ?? 'Not provided') ?>
                </div>
                
                <div class="info-item">
                    <strong><i class="fas fa-calendar-alt"></i> Registration Date:</strong>
                    <?= htmlspecialchars($student['registration_date']) ?>
                </div>
                
                <div class="info-item">
                    <strong><i class="fas fa-id-card"></i> Student ID:</strong>
                    <?= htmlspecialchars($student['id']) ?>
                </div>
            </div>

            <div class="links">
                <a href="edit_profile.php" class="btn">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
                <a href="index.php" class="btn">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
                <a href="logout.php" class="btn logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        <?php else: ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
            <p><a href="../login.php" class="btn"><i class="fas fa-sign-in-alt"></i> Go Back to Login</a></p>
        <?php endif; ?>
    </div>
</body>
</html>