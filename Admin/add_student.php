<?php
session_start();
include('../db_connect.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_id = $_SESSION['admin_id'];
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $department = $_POST['department'];
    $course = $_POST['course'];
    $address = $_POST['address'];
    $registration_date = $_POST['registration_date'];

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO students 
            (admin_id, name, username, email, phone, password, department, course, address, registration_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "isssssssss",
        $admin_id,
        $name,
        $username,
        $email,
        $phone,
        $hashed_password,
        $department,
        $course,
        $address,
        $registration_date
    );

    if ($stmt->execute()) {
        $success = "Student added successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Student</title>
    <link rel="stylesheet" href="Css/add student.css">
</head>
<body>
    <h2>Add Student</h2>

    <form method="POST" action="">
        <label>Name:</label>
        <input type="text" name="name" required><br><br>

        <label>Username:</label>
        <input type="text" name="username" required><br><br>

        <label>Email:</label>
        <input type="email" name="email" required><br><br>

        <label>Phone:</label>
        <input type="text" name="phone" required><br><br>

        <label>Password:</label>
        <input type="password" name="password" required><br><br>

        <label>Department:</label>
        <input type="text" name="department" required><br><br>

        <label>Course:</label>
        <input type="text" name="course" required><br><br>

        <label>Address:</label>
        <textarea name="address" rows="3"></textarea><br><br>

        <label>Registration Date:</label>
        <input type="date" name="registration_date" required><br><br>

        <button type="submit">Add Student</button>
    </form>

    <?php
    if (isset($success)) echo "<p class='success'>$success</p>";
    if (isset($error)) echo "<p class='error'>$error</p>";
    ?>

    <p><a href="index.php">Back to Dashboard</a></p>
</body>
</html>
