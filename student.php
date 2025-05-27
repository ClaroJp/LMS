<?php
session_start();
require 'db.php';

$user_role = $_SESSION['user']['role'];

// Only allow admin or teacher to access this page
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'teacher'])) {
    header("Location: index.php");
    exit;
}

// Get student ID from query param
$studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($studentId <= 0) {
    die("Invalid student ID.");
}

// Fetch student details
$stmt = $conn->prepare("SELECT s.*, u.email FROM students s LEFT JOIN users u ON s.user_id = u.id WHERE s.student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
    die("Student not found.");
}

// Fetch assignments with progress and scores for this student
// Modified query to use CASE statement for status because 'status' column doesn't exist
$stmt = $conn->prepare("
    SELECT a.id, a.title, a.due_date, 
           COALESCE(asub.score, 'N/A') AS score,
           CASE 
               WHEN asub.assignment_id IS NULL THEN 'Not Submitted' 
               ELSE 'Submitted' 
           END AS status
    FROM assignments a
    LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
    ORDER BY a.due_date ASC
");
$stmt->execute([$studentId]);
$assignments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Student Profile & Assignments</title>
    <link rel="stylesheet" href="./styles/student.css">
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
    <div class="profile-section">
        <h1>Student Profile</h1>
        <p><strong>Name:</strong> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($student['email']) ?></p>
        <p><strong>Gender:</strong> <?= htmlspecialchars($student['gender']) ?></p>
        <p><strong>Birthdate:</strong> <?= htmlspecialchars($student['birthdate']) ?></p>
        <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($student['address'])) ?></p>
        <p><strong>Contact Number:</strong> <?= htmlspecialchars($student['contact_number']) ?></p>
        <p><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></p>
        <p><strong>Year Level:</strong> <?= htmlspecialchars($student['year_level']) ?></p>
        <p><strong>Section:</strong> <?= htmlspecialchars($student['section']) ?></p>
    </div>

    <div class="assignments-section">
        <h2>Assignment Progress & Scores</h2>
        <?php if (count($assignments) === 0): ?>
            <p>No assignments found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Assignment Title</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['title']) ?></td>
                            <td><?= htmlspecialchars($a['due_date']) ?></td>
                            <td><?= htmlspecialchars($a['status']) ?></td>
                            <td><?= is_numeric($a['score']) ? htmlspecialchars($a['score']) : htmlspecialchars($a['score']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<script src="./scripts/dashboard.js"></script>
</body>

</html>