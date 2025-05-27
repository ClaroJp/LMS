<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];
$user_role = $user['role'] ?? null;

if (!$user_role) {
    echo "Please select your role first. <a href='role.php'>Choose Role</a>";
    exit;
}
try {
    if ($user_role === 'student') {
        $stmt = $conn->prepare("
            SELECT s.id, s.subject_name, s.description
            FROM subjects s
            JOIN student_subjects ss ON s.id = ss.subject_id
            WHERE ss.student_id = ?
        ");
        $stmt->execute([$user_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role === 'teacher') {
        $stmt = $conn->prepare("
            SELECT id, subject_name, description
            FROM subjects
            WHERE teacher_id = ?
        ");
        $stmt->execute([$user_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        echo "Invalid role.";
        exit;
    }
} catch (Exception $e) {
    die("Error fetching subjects: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Subjects</title>
<link rel="stylesheet" href="./styles/subjects.css">
</head>
<body>
    <div class="sidebar">
        <h2>LMS</h2>
        <ul>
            <?php if ($user_role === 'teacher'): ?>
                <li><a href="teacher_dashboard.php">🏠 Home</a></li>
                <li><a href="todo.php">📝 To-Do List</a></li>
                <li><a href="subjects.php">My Subjects</a></li>
                <li><a href="create_subject.php">➕ Create Subject</a></li>
                <li><a href="chat.php">💬 Chat</a></li>
            <?php else: ?>
                <li><a href="student_dashboard.php">🏠 Home</a></li>
                <li><a href="todo.php">📝 To-Do List</a></li>
                <li><a href="subjects.php">My Subjects</a></li>
                <li><a href="chat.php">💬 Chat</a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        
        <?php if (empty($subjects)): ?>
            <p class="no-subjects">No subjects found.</p>
        <?php else: ?>
            <div class="cards-container">
                <?php foreach ($subjects as $subject): ?>
                    <a href="subject.php?id=<?= urlencode($subject['id']) ?>" class="card-link">
                        <div class="card">
                            <h3><?= htmlspecialchars($subject['subject_name']) ?></h3>
                            <p><?= nl2br(htmlspecialchars($subject['description'] ?? 'No description')) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
