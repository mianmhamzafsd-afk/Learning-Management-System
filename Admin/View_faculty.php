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
    $stmt = $conn->prepare("SELECT * FROM faculty WHERE name LIKE ? OR email LIKE ? OR department LIKE ? OR designation LIKE ? ORDER BY faculty_id DESC");
    $search_param = "%$search%";
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
} else {
    $stmt = $conn->prepare("SELECT * FROM faculty ORDER BY faculty_id DESC");
}

$stmt->execute();
$result = $stmt->get_result();

$total_faculty = $conn->query("SELECT COUNT(*) as total FROM faculty")->fetch_assoc()['total'];
$cs_faculty = $conn->query("SELECT COUNT(*) as total FROM faculty WHERE department = 'Computer Science'")->fetch_assoc()['total'];
$it_faculty = $conn->query("SELECT COUNT(*) as total FROM faculty WHERE department = 'Information Technology'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Faculty - Admin Dashboard</title>
    <link rel="stylesheet" href="Css/View faculty.css">
</head>
<body>
    <div class="view-faculty-container">
        <a href="index.php" class="back-btn-faculty">← Back to Dashboard</a>
        
        <h2>Faculty Management</h2>

        <div class="faculty-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_faculty; ?></div>
                <div class="stat-label">Total Faculty</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $cs_faculty; ?></div>
                <div class="stat-label">Computer Science</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $it_faculty; ?></div>
                <div class="stat-label">IT Faculty</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_faculty - ($cs_faculty + $it_faculty); ?></div>
                <div class="stat-label">Other Departments</div>
            </div>
        </div>

        <div class="faculty-header">
            <form method="GET" action="" class="faculty-search">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, department, or designation...">
                <button type="submit">Search</button>
                <?php if(!empty($search)): ?>
                    <a href="View_faculty.php" class="action-btn" style="background: #6c757d; color: white; text-decoration: none; padding: 12px 20px; border-radius: 6px;">Clear</a>
                <?php endif; ?>
            </form>
            <a href="add_faculty.php" class="action-btn" style="background: #28a745; color: white; text-decoration: none; padding: 12px 20px; border-radius: 6px;">+ Add New Faculty</a>
        </div>

        <div class="faculty-table-container">
            <?php if ($result->num_rows > 0): ?>
                <table class="faculty-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Specialization</th>
                            <th>Office</th>
                            <th>Hire Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($faculty = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $faculty['faculty_id']; ?></td>
                                <td><?php echo htmlspecialchars($faculty['name']); ?></td>
                                <td><?php echo htmlspecialchars($faculty['email']); ?></td>
                                <td><?php echo htmlspecialchars($faculty['phone']); ?></td>
                                <td><span class="department-badge"><?php echo htmlspecialchars($faculty['department']); ?></span></td>
                                <td><span class="designation-badge"><?php echo htmlspecialchars($faculty['designation']); ?></span></td>
                                <td><?php echo htmlspecialchars($faculty['specialization'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($faculty['office_location'] ?: 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($faculty['hire_date'])); ?></td>
                                <td>
                                    <div class="faculty-actions">
                                        <a href="edit_faculty.php?id=<?php echo $faculty['faculty_id']; ?>" class="action-btn edit-faculty">Edit</a>
                                        <a href="delete_faculty.php?id=<?php echo $faculty['faculty_id']; ?>" class="action-btn delete-faculty" onclick="return confirm('Are you sure you want to delete this faculty member?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-faculty">
                    <p>📝</p>
                    <h3>No Faculty Members Found</h3>
                    <p><?php echo !empty($search) ? 'Try adjusting your search criteria' : 'Start by adding your first faculty member'; ?></p>
                    <?php if(empty($search)): ?>
                        <a href="add_faculty.php" class="action-btn" style="background: #28a745; color: white; text-decoration: none; padding: 12px 20px; border-radius: 6px; margin-top: 15px; display: inline-block;">Add First Faculty Member</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div style="margin-top: 20px; text-align: center; color: #6c757d; font-size: 14px;">
                Showing <?php echo $result->num_rows; ?> faculty member(s)
                <?php if(!empty($search)): ?>
                    for "<?php echo htmlspecialchars($search); ?>"
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>