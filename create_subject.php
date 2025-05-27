<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

require 'db.php';
$user_role = $_SESSION['user']['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_name = trim($_POST['subject_name']);
    $description = trim($_POST['description']);
    $teacher_id = $_SESSION['user']['id'];

    if (!$subject_name) {
        $error = "Subject name is required";
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (teacher_id, subject_name, description) VALUES (?, ?, ?)");
        $stmt->execute([$teacher_id, $subject_name, $description]);
        header("Location: teacher_dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Subject</title>
    <link rel="stylesheet" href="./styles/create_subject.css">
</head>

<body>
    <button id="menuBtn" class="menu-btn" aria-label="Toggle menu">☰ Menu</button>
    <div class="sidebar">
        <h2>LMS</h2>
        <ul>
            <?php if ($user_role === 'teacher'): ?>
                <li><a href="teacher_dashboard.php">🏠 Home</a></li>
                <li><a href="todo.php">📝 To-Do List</a></li>
                <li><a href="subjects.php">My Subjects</a></li>
                <li><a href="materials.php">📤 Materials</a></li>
                <li><a href="create_subject.php">➕ Create Subject</a></li>
                <li><a href="chat.php">💬 Chat</a></li>
            <?php else: ?>
                <li><a href="student_dashboard.php">🏠 Home</a></li>
                <li><a href="todo.php">📝 To-Do List</a></li>
                <li><a href="subjects.php">My Subjects</a></li>
                <li><a href="materials.php">📤 Materials</a></li>
                <li><a href="chat.php">💬 Chat</a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <h1>Create New Subject</h1>
        <?php if (!empty($error))
            echo "<p style='color:red;'>$error</p>"; ?>
        <form method="POST">
            <label>Subject Name:</label><br>
            <input type="text" name="subject_name" required><br>
            <label>Description:</label><br>
            <textarea name="description"></textarea><br>
            <button type="submit">Create Subject</button>
        </form>
        <a href="teacher_dashboard.php">Back to Dashboard</a>
    </div>
    <script src="./scripts/dashboard.js"></script>
</body>

</html>