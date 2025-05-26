<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['role'])) {
    header("Location: role.php");
    exit;
}

$role = $_POST['role'];

if ($role !== 'student' && $role !== 'teacher') {
    echo "<script>alert('Invalid role selected'); window.location.href='role.php';</script>";
    exit;
}

$userId = $_SESSION['user']['id'];

// Check if role already set
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user && $user['role']) {
    // Role already assigned, redirect to corresponding dashboard
    $_SESSION['user']['role'] = $user['role']; // Update session role just in case

    if ($user['role'] === 'student') {
        header("Location: student_dashboard.php");
    } else {
        header("Location: teacher_dashboard.php");
    }
    exit;
}

// Update users table role
$updateRole = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
$updateRole->execute([$role, $userId]);

// Update session role
$_SESSION['user']['role'] = $role;

// Redirect to details form
if ($role === 'student') {
    header("Location: student_details.php");
} else {
    header("Location: teacher_details.php");
}
exit;
?>
