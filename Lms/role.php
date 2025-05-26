<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// Redirect if role is already set
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] !== null) {
    if ($_SESSION['user']['role'] === 'student') {
        header("Location: student_dashboard.php");
        exit;
    } elseif ($_SESSION['user']['role'] === 'teacher') {
        header("Location: teacher_dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Choose Role</title>
    <link rel="stylesheet" href="./styles/role.css">
</head>
<body>
<div class="role-box">
    <h2>Hello, <?php echo htmlspecialchars($_SESSION['user']['username']); ?>!</h2>
    <p>Please choose your role:</p>
    <form method="POST" action="process_role.php">
        <button type="submit" name="role" value="student">I'm a Student</button>
        <button type="submit" name="role" value="teacher">I'm a Teacher</button>
    </form>
</div>
</body>
</html>
