<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

include("../db_connect.php");

$error = "";
$success = "";
$faculty = null;

// Get faculty ID from URL
$faculty_id = $_GET['id'] ?? 0;

if (!$faculty_id) {
    header("Location: View_faculty.php");
    exit();
}

// Fetch faculty data
$stmt = $conn->prepare("SELECT * FROM faculty WHERE faculty_id = ?");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$result = $stmt->get_result();
$faculty = $result->fetch_assoc();

if (!$faculty) {
    header("Location: View_faculty.php");
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $department = $_POST['department'];
    $designation = $_POST['designation'];
    $specialization = $_POST['specialization'];
    $office_location = $_POST['office_location'];
    $office_hours = $_POST['office_hours'];
    $address = $_POST['address'];
    $hire_date = $_POST['hire_date'];
    $is_focal_person = isset($_POST['is_focal_person']) ? 1 : 0;
    $focal_responsibility = $_POST['focal_responsibility'] ?? '';
    
    // Check if username or email already exists (excluding current faculty)
    $check_stmt = $conn->prepare("SELECT faculty_id FROM faculty WHERE (username = ? OR email = ?) AND faculty_id != ?");
    $check_stmt->bind_param("ssi", $username, $email, $faculty_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error = "Username or Email already exists for another faculty member!";
    } else {
        // If this faculty is marked as focal person, remove focal person status from others in same department
        if ($is_focal_person) {
            $conn->query("UPDATE faculty SET is_focal_person = 0 WHERE department = '$department' AND faculty_id != $faculty_id");
        }
        
        // Check if password is being updated
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE faculty SET name = ?, username = ?, email = ?, phone = ?, password = ?, department = ?, designation = ?, specialization = ?, office_location = ?, office_hours = ?, address = ?, hire_date = ?, is_focal_person = ?, focal_responsibility = ? WHERE faculty_id = ?");
            $stmt->bind_param("ssssssssssssisi", $name, $username, $email, $phone, $password, $department, $designation, $specialization, $office_location, $office_hours, $address, $hire_date, $is_focal_person, $focal_responsibility, $faculty_id);
        } else {
            $stmt = $conn->prepare("UPDATE faculty SET name = ?, username = ?, email = ?, phone = ?, department = ?, designation = ?, specialization = ?, office_location = ?, office_hours = ?, address = ?, hire_date = ?, is_focal_person = ?, focal_responsibility = ? WHERE faculty_id = ?");
            $stmt->bind_param("sssssssssssisi", $name, $username, $email, $phone, $department, $designation, $specialization, $office_location, $office_hours, $address, $hire_date, $is_focal_person, $focal_responsibility, $faculty_id);
        }
        
        if ($stmt->execute()) {
            $success = "Faculty member updated successfully!";
            // Refresh faculty data
            $result = $conn->query("SELECT * FROM faculty WHERE faculty_id = $faculty_id");
            $faculty = $result->fetch_assoc();
        } else {
            $error = "Error updating faculty member: " . $conn->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Faculty - Admin Dashboard</title>
    <link rel="stylesheet" href="Css/edit_faculty.css">
</head>
<body>
    <div class="edit-faculty-container">
        <a href="View_faculty.php" class="back-btn">← Back to Faculty List</a>
        
        <h2>Edit Faculty Member</h2>
        
        <?php if($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="faculty-form">
            <!-- Personal Information -->
            <div class="form-section">
                <h3>👤 Personal Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($faculty['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($faculty['username']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($faculty['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($faculty['phone']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password (Leave empty to keep current)</label>
                    <input type="password" id="password" name="password" placeholder="Enter new password if changing">
                    <small>Minimum 8 characters</small>
                </div>
            </div>

            <!-- Professional Information -->
            <div class="form-section">
                <h3>💼 Professional Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="department">Department *</label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="Computer Science" <?php echo $faculty['department'] == 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                            <option value="Information Technology" <?php echo $faculty['department'] == 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                            <option value="Software Engineering" <?php echo $faculty['department'] == 'Software Engineering' ? 'selected' : ''; ?>>Software Engineering</option>
                            <option value="Data Science" <?php echo $faculty['department'] == 'Data Science' ? 'selected' : ''; ?>>Data Science</option>
                            <option value="Cyber Security" <?php echo $faculty['department'] == 'Cyber Security' ? 'selected' : ''; ?>>Cyber Security</option>
                            <option value="Mathematics" <?php echo $faculty['department'] == 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                            <option value="Physics" <?php echo $faculty['department'] == 'Physics' ? 'selected' : ''; ?>>Physics</option>
                            <option value="Chemistry" <?php echo $faculty['department'] == 'Chemistry' ? 'selected' : ''; ?>>Chemistry</option>
                            <option value="Biology" <?php echo $faculty['department'] == 'Biology' ? 'selected' : ''; ?>>Biology</option>
                            <option value="Zoology" <?php echo $faculty['department'] == 'Zoology' ? 'selected' : ''; ?>>Zoology</option>
                            <option value="Business Administration" <?php echo $faculty['department'] == 'Business Administration' ? 'selected' : ''; ?>>Business Administration</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="designation">Designation *</label>
                        <select id="designation" name="designation" required>
                            <option value="">Select Designation</option>
                            <option value="Professor" <?php echo $faculty['designation'] == 'Professor' ? 'selected' : ''; ?>>Professor</option>
                            <option value="Associate Professor" <?php echo $faculty['designation'] == 'Associate Professor' ? 'selected' : ''; ?>>Associate Professor</option>
                            <option value="Assistant Professor" <?php echo $faculty['designation'] == 'Assistant Professor' ? 'selected' : ''; ?>>Assistant Professor</option>
                            <option value="Senior Lecturer" <?php echo $faculty['designation'] == 'Senior Lecturer' ? 'selected' : ''; ?>>Senior Lecturer</option>
                            <option value="Lecturer" <?php echo $faculty['designation'] == 'Lecturer' ? 'selected' : ''; ?>>Lecturer</option>
                            <option value="Visiting Faculty" <?php echo $faculty['designation'] == 'Visiting Faculty' ? 'selected' : ''; ?>>Visiting Faculty</option>
                            <option value="Adjunct Professor" <?php echo $faculty['designation'] == 'Adjunct Professor' ? 'selected' : ''; ?>>Adjunct Professor</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="specialization">Specialization</label>
                    <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($faculty['specialization']); ?>" placeholder="e.g., Artificial Intelligence, Data Structures">
                </div>
            </div>

            <!-- Focal Person Section -->
            <div class="form-section focal-section">
                <h3>⭐ Focal Person Role</h3>
                <div class="focal-toggle">
                    <label class="toggle-label">
                        <input type="checkbox" id="is_focal_person" name="is_focal_person" value="1" <?php echo $faculty['is_focal_person'] ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                        <span class="toggle-text">Mark as Department Focal Person</span>
                    </label>
                    <small class="toggle-help">Only one focal person per department. Enabling this will replace existing focal person in the same department.</small>
                </div>

                <div class="focal-details" id="focalDetails" style="<?php echo $faculty['is_focal_person'] ? 'display: block;' : 'display: none;'; ?>">
                    <div class="form-group">
                        <label for="focal_responsibility">Focal Responsibility</label>
                        <input type="text" id="focal_responsibility" name="focal_responsibility" value="<?php echo htmlspecialchars($faculty['focal_responsibility']); ?>" placeholder="e.g., Student Affairs, Research Coordinator, Exam Controller">
                        <small>Specific responsibilities as focal person</small>
                    </div>
                </div>
            </div>

            <!-- Office & Contact Information -->
            <div class="form-section">
                <h3>🏢 Office & Contact Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="office_location">Office Location</label>
                        <input type="text" id="office_location" name="office_location" value="<?php echo htmlspecialchars($faculty['office_location']); ?>" placeholder="e.g., Room 101, CS Building">
                    </div>

                    <div class="form-group">
                        <label for="office_hours">Office Hours</label>
                        <input type="text" id="office_hours" name="office_hours" value="<?php echo htmlspecialchars($faculty['office_hours']); ?>" placeholder="e.g., Mon-Wed 10:00 AM - 12:00 PM">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" placeholder="Enter complete address"><?php echo htmlspecialchars($faculty['address']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="hire_date">Hire Date *</label>
                    <input type="date" id="hire_date" name="hire_date" value="<?php echo $faculty['hire_date']; ?>" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="submit-btn">Update Faculty Member</button>
                <a href="View_faculty.php" class="cancel-btn">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Set maximum date to today for hire date
        document.getElementById('hire_date').max = new Date().toISOString().split('T')[0];

        // Toggle focal person details
        document.getElementById('is_focal_person').addEventListener('change', function() {
            const focalDetails = document.getElementById('focalDetails');
            if (this.checked) {
                focalDetails.style.display = 'block';
                
                // Get selected department
                const department = document.getElementById('department').value;
                if (department) {
                    if (!confirm(`This will make this faculty the focal person for ${department}. Any existing focal person in this department will be replaced. Continue?`)) {
                        this.checked = false;
                        focalDetails.style.display = 'none';
                    }
                }
            } else {
                focalDetails.style.display = 'none';
            }
        });

        // Department change warning for focal person
        document.getElementById('department').addEventListener('change', function() {
            const isFocal = document.getElementById('is_focal_person');
            if (isFocal.checked && this.value) {
                if (!confirm(`You've marked this faculty as focal person. Changing department to ${this.value} will make them the focal person for the new department. Continue?`)) {
                    this.value = '<?php echo $faculty['department']; ?>';
                }
            }
        });

        // Password validation
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                if (this.value.length > 0 && this.value.length < 8) {
                    this.setCustomValidity('Password must be at least 8 characters long');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>