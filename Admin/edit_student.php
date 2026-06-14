<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include("../db_connect.php");

$error = $success = "";
$student = null;
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get student details
if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$student) {
    header("Location: view_students.php");
    exit();
}

// Departments and courses arrays
$departments = ['Computer Science', 'Information Technology', 'Software Engineering', 'Data Science', 'Cyber Security', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Zoology', 'Business Administration'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_student'])) {
    $data = [
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone'],
        'department' => $_POST['department'],
        'course' => $_POST['course'],
        'address' => $_POST['address'] ?? '',
        'id' => $student_id
    ];
    
    // Check if email already exists (excluding current student)
    $check = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
    $check->bind_param("si", $data['email'], $data['id']);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $error = "Email already exists!";
    } else {
        // Update password only if provided
        if (!empty($_POST['password'])) {
            $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE students SET name=?, email=?, phone=?, password=?, department=?, course=?, address=? WHERE id=?");
            $stmt->bind_param("sssssssi", $data['name'], $data['email'], $data['phone'], $data['password'], $data['department'], $data['course'], $data['address'], $data['id']);
        } else {
            $stmt = $conn->prepare("UPDATE students SET name=?, email=?, phone=?, department=?, course=?, address=? WHERE id=?");
            $stmt->bind_param("ssssssi", $data['name'], $data['email'], $data['phone'], $data['department'], $data['course'], $data['address'], $data['id']);
        }
        
        if ($stmt->execute()) {
            $success = "Student updated successfully!";
            // Refresh student data
            $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $error = "Error updating student: " . $conn->error;
        }
    }
    $check->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
    <link rel="stylesheet" href="Css/edit_student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <a href="view_students.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Students</a>
        
        <div class="card">
            <div class="header">
                <i class="fas fa-user-edit"></i>
                <h2>Edit Student</h2>
                <p>Update student information</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="student-info">
                    <div class="avatar"><?php echo strtoupper(substr($student['name'], 0, 2)); ?></div>
                    <div>
                        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                        <p class="id">ID: <?php echo $student['id']; ?></p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Full Name *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone *</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Department *</label>
                        <select name="department" required>
                            <option value="">Select Department</option>
                            <?php foreach($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>" <?php echo $student['department'] == $dept ? 'selected' : ''; ?>>
                                    <?php echo $dept; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-book"></i> Course *</label>
                        <input type="text" name="course" value="<?php echo htmlspecialchars($student['course']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Address</label>
                    <textarea name="address"><?php echo htmlspecialchars($student['address']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-key"></i> Password (Leave blank to keep current)</label>
                    <input type="password" name="password" placeholder="Enter new password">
                </div>
                
                <div class="buttons">
                    <a href="view_students.php" class="btn cancel">Cancel</a>
                    <button type="submit" name="update_student" class="btn update">Update Student</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Set max date for registration date
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('[name="registration_date"]').max = today;
        });
    </script>
</body>
</html>