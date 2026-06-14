<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include("../db_connect.php");

$error = $success = "";
$faculty = null;
$faculty_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get faculty details
if ($faculty_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE faculty_id = ?");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $faculty = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$faculty) {
    $error = "Faculty not found!";
    header("refresh:2;url=View_faculty.php");
}

// Handle deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    if (isset($_POST['confirm_text']) && strtoupper($_POST['confirm_text']) === 'DELETE') {
        $conn->begin_transaction();
        
        try {
            // Delete related records
            $tables = ['events', 'news_updates', 'notices', 'notifications'];
            foreach ($tables as $table) {
                $conn->query("DELETE FROM $table WHERE faculty_id = $faculty_id");
            }
            
            // Delete faculty
            $stmt = $conn->prepare("DELETE FROM faculty WHERE faculty_id = ?");
            $stmt->bind_param("i", $faculty_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $success = "Faculty deleted! Redirecting...";
                header("refresh:2;url=View_faculty.php");
            } else {
                throw new Exception("Delete failed");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
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
    <title>Delete Faculty</title>
    <link rel="stylesheet" href="Css/delete_faculty.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <a href="View_faculty.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
        
        <div class="card">
            <div class="header">
                <i class="fas fa-user-slash"></i>
                <h2>Delete Faculty</h2>
                <p>This action cannot be undone</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($faculty && !$success): ?>
                <div class="details">
                    <div class="faculty-info">
                        <div class="avatar"><?php echo strtoupper(substr($faculty['name'], 0, 2)); ?></div>
                        <div>
                            <h3><?php echo htmlspecialchars($faculty['name']); ?></h3>
                            <p>ID: <?php echo $faculty['faculty_id']; ?></p>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($faculty['email']); ?></div>
                        <div><i class="fas fa-phone"></i> <strong>Phone:</strong> <?php echo htmlspecialchars($faculty['phone']); ?></div>
                        <div><i class="fas fa-building"></i> <strong>Dept:</strong> <span class="dept"><?php echo htmlspecialchars($faculty['department']); ?></span></div>
                        <div><i class="fas fa-user-tie"></i> <strong>Designation:</strong> <span class="desig"><?php echo htmlspecialchars($faculty['designation']); ?></span></div>
                        <?php if($faculty['is_focal_person']): ?>
                            <div class="warning"><i class="fas fa-exclamation-triangle"></i> This faculty is a focal person!</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <h4>Warning</h4>
                            <p>All data will be permanently deleted. This includes events, notices, and news.</p>
                        </div>
                    </div>
                    
                    <form method="POST" class="delete-form">
                        <label>Type "DELETE" to confirm:</label>
                        <input type="text" name="confirm_text" placeholder="Type DELETE" required autocomplete="off" oninput="toggleDeleteBtn()">
                        
                        <div class="buttons">
                            <a href="View_faculty.php" class="btn cancel">Cancel</a>
                            <button type="submit" name="confirm_delete" class="btn delete" id="deleteBtn" disabled>Delete Permanently</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleDeleteBtn() {
            const input = document.querySelector('[name="confirm_text"]');
            const btn = document.getElementById('deleteBtn');
            btn.disabled = input.value.toUpperCase() !== 'DELETE';
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm('Are you absolutely sure?')) e.preventDefault();
        });
    </script>
</body>
</html>