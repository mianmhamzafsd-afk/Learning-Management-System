<?php
session_start();

// Check if user is logged in and is faculty
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../login.php");
    exit();
}

// Debug: Check session data (remove this in production)
// echo "<pre>Session data: ";
// print_r($_SESSION);
// echo "</pre>";

// Check if faculty has focal person status set in session
if (isset($_SESSION['is_focal_person']) && $_SESSION['is_focal_person'] == 1) {
    // Redirect to Focal folder dashboard
    // Try different possible paths
    $focal_paths = [
        'Focal/index.php',        // Same level
        '../Focal/index.php',     // One level up
        '../../Focal/index.php'   // Two levels up
    ];
    
    foreach ($focal_paths as $path) {
        if (file_exists($path)) {
            header("Location: $path");
            exit();
        }
    }
    
    // If no file found, show error
    die("Error: Focal dashboard not found. Please check folder structure.");
} else {
    // Redirect to Regular folder dashboard
    // Try different possible paths
    $regular_paths = [
        'Regular/index.php',        // Same level
        '../Regular/index.php',     // One level up  
        '../../Regular/index.php'   // Two levels up
    ];
    
    foreach ($regular_paths as $path) {
        if (file_exists($path)) {
            header("Location: $path");
            exit();
        }
    }
    
    // If no file found, show error
    die("Error: Regular dashboard not found. Please check folder structure.");
}
?>