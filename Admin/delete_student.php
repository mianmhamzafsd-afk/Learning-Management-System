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
    $error = "Student not found!";
    header("refresh:2;url=view_students.php");
}

// Handle deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    if (isset($_POST['confirm_text']) && strtoupper($_POST['confirm_text']) === 'DELETE') {
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        
        if ($stmt->execute()) {
            $success = "Student deleted successfully!";
            header("refresh:2;url=view_students.php");
        } else {
            $error = "Error deleting student: " . $conn->error;
        }
        $stmt->close();
    } else {
        $error = "Please type 'DELETE' to confirm";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Student</title>
    <link rel="stylesheet" href="Css/delete_student.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <a href="view_students.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Students</a>
        
        <div class="card">
            <div class="header">
                <i class="fas fa-user-graduate"></i>
                <h2>Delete Student</h2>
                <p>This action cannot be undone</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                <p class="redirect">Redirecting to students list...</p>
            <?php endif; ?>
            
            <?php if($student && !$success): ?>
                <div class="student-info">
                    <div class="avatar"><?php echo strtoupper(substr($student['name'], 0, 2)); ?></div>
                    <div>
                        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                        <p class="id">ID: <?php echo $student['id']; ?></p>
                    </div>
                </div>
                
                <div class="details">
                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <strong>Phone:</strong> <?php echo htmlspecialchars($student['phone']); ?></p>
                    <p><i class="fas fa-building"></i> <strong>Department:</strong> <span class="dept"><?php echo htmlspecialchars($student['department']); ?></span></p>
                    <p><i class="fas fa-book"></i> <strong>Course:</strong> <?php echo htmlspecialchars($student['course']); ?></p>
                    <p><i class="fas fa-calendar"></i> <strong>Registration Date:</strong> <?php echo date('F j, Y', strtotime($student['registration_date'])); ?></p>
                </div>
                
                <div class="warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <h4>Warning</h4>
                        <p>All student data will be permanently deleted.</p>
                    </div>
                </div>
                
                <form method="POST" class="delete-form">
                    <label>Type "DELETE" to confirm:</label>
                    <input type="text" name="confirm_text" placeholder="Type DELETE" required autofocus>
                    
                    <div class="buttons">
                        <a href="view_students.php" class="btn cancel">Cancel</a>
                        <button type="submit" name="confirm_delete" class="btn delete" id="deleteBtn" disabled>Delete Student</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        document.querySelector('[name="confirm_text"]').addEventListener('input', function() {
            document.getElementById('deleteBtn').disabled = this.value.toUpperCase() !== 'DELETE';
        });
        
        document.querySelector('.delete-form').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to delete this student?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>