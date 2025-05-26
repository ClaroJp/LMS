<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

require 'db.php';

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
</head>
<body>
<h1>Create New Subject</h1>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="POST">
    <label>Subject Name:</label><br>
    <input type="text" name="subject_name" required><br>
    <label>Description:</label><br>
    <textarea name="description"></textarea><br>
    <button type="submit">Create Subject</button>
</form>
<a href="teacher_dashboard.php">Back to Dashboard</a>
</body>
</html>
