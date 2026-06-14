<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if admin_id is set in session
if (!isset($_SESSION['admin_id'])) {
    die("Admin ID not found in session. Please log in again.");
}

include("../db_connect.php");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = [
        'admin_id' => $_SESSION['admin_id'], // UNCOMMENT THIS LINE
        'name' => $_POST['name'],
        'username' => $_POST['username'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone'],
        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'department' => $_POST['department'],
        'designation' => $_POST['designation'],
        'specialization' => $_POST['specialization'] ?? '',
        'office_location' => $_POST['office_location'] ?? '',
        'office_hours' => $_POST['office_hours'] ?? '',
        'address' => $_POST['address'] ?? '',
        'hire_date' => $_POST['hire_date'],
        'is_focal_person' => isset($_POST['is_focal_person']) ? 1 : 0,
        'focal_responsibility' => $_POST['focal_responsibility'] ?? '',
    ];

    // Check duplicates
    $check = $conn->prepare("SELECT faculty_id FROM faculty WHERE username = ? OR email = ?");
    $check->bind_param("ss", $data['username'], $data['email']);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $error = "Username or Email already exists!";
    } else {
        // Update focal person if needed
        if ($data['is_focal_person']) {
            // Use prepared statement to prevent SQL injection
            $updateStmt = $conn->prepare("UPDATE faculty SET is_focal_person = 0 WHERE department = ?");
            $updateStmt->bind_param("s", $data['department']);
            $updateStmt->execute();
            $updateStmt->close();
        }

        // Insert faculty
        $stmt = $conn->prepare("INSERT INTO faculty (admin_id, name, username, email, phone, password, department, designation, specialization, office_location, office_hours, address, hire_date, is_focal_person, focal_responsibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Bind parameters individually for clarity and debugging
        $stmt->bind_param(
            "issssssssssssis",
            $data['admin_id'],
            $data['name'],
            $data['username'],
            $data['email'],
            $data['phone'],
            $data['password'],
            $data['department'],
            $data['designation'],
            $data['specialization'],
            $data['office_location'],
            $data['office_hours'],
            $data['address'],
            $data['hire_date'],
            $data['is_focal_person'],
            $data['focal_responsibility']
        );
        
        if ($stmt->execute()) {
            $success = "Faculty added successfully!";
            $_POST = []; // Clear form fields
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    $check->close();
}

// Departments and designations arrays
$departments = ['Computer Science', 'Information Technology', 'Software Engineering', 'Data Science', 'Cyber Security', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'Zoology', 'Business Administration'];
$designations = ['Professor', 'Associate Professor', 'Assistant Professor', 'Senior Lecturer', 'Lecturer', 'Visiting Faculty', 'Adjunct Professor'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Faculty</title>
    <link rel="stylesheet" href="Css/add_faculty.css">
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">← Dashboard</a>
        <a href="View_faculty.php" class="back-btn">← View Faculty</a>
        
        <h2>Add Faculty</h2>
        
        <?php if($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <form method="POST" action="">
            <!-- Personal Info -->
            <h3>Personal Info</h3>
            <div class="form-row">
                <input type="text" name="name" placeholder="Full Name *" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                <input type="text" name="username" placeholder="Username *" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            <div class="form-row">
                <input type="email" name="email" placeholder="Email *" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <input type="tel" name="phone" placeholder="Phone *" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
            </div>
            <input type="password" name="password" placeholder="Password *" required>

            <!-- Professional Info -->
            <h3>Professional Info</h3>
            <div class="form-row">
                <select name="department" required>
                    <option value="">Select Department</option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($_POST['department'] ?? '') == $dept ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="designation" required>
                    <option value="">Select Designation</option>
                    <?php foreach($designations as $desig): ?>
                        <option value="<?php echo htmlspecialchars($desig); ?>" <?php echo ($_POST['designation'] ?? '') == $desig ? 'selected' : ''; ?>><?php echo htmlspecialchars($desig); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="text" name="specialization" placeholder="Specialization" value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>">

            <!-- Focal Person -->
            <h3>Focal Person</h3>
            <label class="checkbox">
                <input type="checkbox" name="is_focal_person" value="1" <?php echo isset($_POST['is_focal_person']) ? 'checked' : ''; ?>>
                Mark as Department Focal Person
            </label>
            <input type="text" name="focal_responsibility" placeholder="Focal Responsibility" value="<?php echo htmlspecialchars($_POST['focal_responsibility'] ?? ''); ?>" style="<?php echo isset($_POST['is_focal_person']) ? '' : 'display:none;'; ?>" id="focalInput">

            <!-- Office Info -->
            <h3>Office Info</h3>
            <div class="form-row">
                <input type="text" name="office_location" placeholder="Office Location" value="<?php echo htmlspecialchars($_POST['office_location'] ?? ''); ?>">
                <input type="text" name="office_hours" placeholder="Office Hours" value="<?php echo htmlspecialchars($_POST['office_hours'] ?? ''); ?>">
            </div>
            <textarea name="address" placeholder="Address"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
            <input type="date" name="hire_date" value="<?php echo htmlspecialchars($_POST['hire_date'] ?? ''); ?>" required>

            <button type="submit" class="submit-btn">Add Faculty</button>
        </form>
    </div>

    <script>
        // Toggle focal responsibility input
        document.querySelector('[name="is_focal_person"]').addEventListener('change', function() {
            document.getElementById('focalInput').style.display = this.checked ? 'block' : 'none';
        });

        // Set max hire date
        document.querySelector('[name="hire_date"]').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>