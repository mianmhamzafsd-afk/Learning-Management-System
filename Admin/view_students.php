<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include("../db_connect.php");

$search = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $stmt = $conn->prepare("SELECT * FROM students WHERE name LIKE ? OR email LIKE ? OR department LIKE ? OR course LIKE ? ORDER BY id DESC");
    $search_param = "%$search%";
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
} else {
    $stmt = $conn->prepare("SELECT * FROM students ORDER BY id DESC");
}

$stmt->execute();
$result = $stmt->get_result();

$total_students = $conn->query("SELECT COUNT(*) as total FROM students")->fetch_assoc()['total'];
$cs_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE department = 'Computer Science'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Students - Admin</title>
    <link rel="stylesheet" href="Css/view_students.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
        
        <div class="header">
            <h2><i class="fas fa-users"></i> Student Management</h2>
            <a href="add_student.php" class="add-btn"><i class="fas fa-plus"></i> Add Student</a>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $cs_students; ?></div>
                <div class="stat-label">Computer Science</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_students - $cs_students; ?></div>
                <div class="stat-label">Other Departments</div>
            </div>
        </div>
        
        <form method="GET" class="search-form">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search students...">
                <button type="submit">Search</button>
                <?php if(!empty($search)): ?>
                    <a href="view_students.php" class="clear-btn">Clear</a>
                <?php endif; ?>
            </div>
        </form>
        
        <div class="table-container">
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Course</th>
                            <th>Reg Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($student = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $student['id']; ?></td>
                                <td>
                                    <div class="student-info">
                                        <div class="avatar"><?php echo strtoupper(substr($student['name'], 0, 2)); ?></div>
                                        <span><?php echo htmlspecialchars($student['name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                <td><span class="dept-badge"><?php echo htmlspecialchars($student['department']); ?></span></td>
                                <td><?php echo htmlspecialchars($student['course']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($student['registration_date'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn edit"><i class="fas fa-edit"></i></a>
                                        <a href="delete_student.php?id=<?php echo $student['id']; ?>" class="btn delete" onclick="return confirm('Delete this student?')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-user-graduate"></i>
                    <h3>No Students Found</h3>
                    <p><?php echo !empty($search) ? 'Try different search terms' : 'Add your first student'; ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($result->num_rows > 0): ?>
            <div class="footer">Showing <?php echo $result->num_rows; ?> student(s)</div>
        <?php endif; ?>
    </div>
    
    <script>
        // Quick delete confirmation
        document.querySelectorAll('.delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this student?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>