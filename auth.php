<?php
session_start();
require 'db.php';

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Check if email already exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        echo "<script>alert('Email already exists'); window.location.href='index.php?view=signup';</script>";
    } else {
        // Insert with role set to NULL (user has not chosen yet)
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, NULL)");
        $stmt->execute([$name, $email, $password]);

        // Save user info to session without role yet
        $_SESSION['user'] = [
            'id' => $conn->lastInsertId(),
            'username' => $name,
            'email' => $email,
            'role' => null
        ];

        header("Location: role.php");
        exit;
    }

} elseif ($action === 'login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $password === $user['password']) {
        $_SESSION['user'] = $user;

        $studentCheck = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
        $studentCheck->execute([$user['id']]);

        $teacherCheck = $conn->prepare("SELECT * FROM teachers WHERE user_id = ?");
        $teacherCheck->execute([$user['id']]);

        if ($studentCheck->rowCount() === 0 && $teacherCheck->rowCount() === 0) {
            header("Location: role.php");
        } else {
            // Redirect based on role
            if ($user['role'] === 'student') {
                header("Location: student_dashboard.php");
            } elseif ($user['role'] === 'teacher') {
                header("Location: teacher_dashboard.php");
            } else {
                // fallback in case role not set
                header("Location: role.php");
            }
        }
        exit;
    } else {
        echo "<script>alert('Invalid credentials'); window.location.href='index.php';</script>";
    }

} else {
    header("Location: index.php");
    exit;
}
?>
