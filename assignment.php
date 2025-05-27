<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$user_role = $_SESSION['user']['role'];
$assignment_id = $_GET['id'] ?? 0;
if (!$assignment_id)
    die("Assignment not specified.");

// Fetch assignment info + subject info
$stmt = $conn->prepare("
    SELECT a.*, s.subject_name, s.teacher_id 
    FROM assignments a 
    JOIN subjects s ON a.subject_id = s.id 
    WHERE a.id = ?");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch();
if (!$assignment)
    die("Assignment not found.");

// Access check
if ($user_role === 'student') {
    // Verify student enrollment
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    if (!$student)
        die("Student profile not found.");
    $student_id = $student['student_id'];

    $stmt = $conn->prepare("SELECT * FROM student_subjects WHERE student_id = ? AND subject_id = ?");
    $stmt->execute([$student_id, $assignment['subject_id']]);
    if ($stmt->rowCount() === 0)
        die("You are not enrolled in this subject.");
} elseif ($user_role === 'teacher') {
    if ($assignment['teacher_id'] != $user_id)
        die("You do not have access to this assignment.");
} else {
    die("Access denied.");
}

// Handle student submission upload
$submissionMessage = '';
if ($user_role === 'student' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $file = $_FILES['submission_file'] ?? null;
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'submissions/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);
        $filename = time() . '_' . basename($file['name']);
        $filePath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Check if student already submitted before - update if yes
            $check = $conn->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
            $check->execute([$assignment_id, $student_id]);

            if ($check->rowCount() > 0) {
                $update = $conn->prepare("UPDATE assignment_submissions SET file_path = ?, submitted_at = NOW() WHERE assignment_id = ? AND student_id = ?");
                $update->execute([$filePath, $assignment_id, $student_id]);
            } else {
                $insert = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path) VALUES (?, ?, ?)");
                $insert->execute([$assignment_id, $student_id, $filePath]);
            }
            $submissionMessage = "Submission uploaded successfully.";
        } else {
            $submissionMessage = "Failed to upload file.";
        }
    } else {
        $submissionMessage = "Please upload a valid file.";
    }
}

// For teacher: fetch submissions & all students in subject
$submissions = [];
$students = [];
if ($user_role === 'teacher') {
    // All students enrolled in subject
    $stmt = $conn->prepare("
        SELECT s.student_id, s.first_name, s.last_name, u.email
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN student_subjects ss ON s.student_id = ss.student_id
        WHERE ss.subject_id = ?");
    $stmt->execute([$assignment['subject_id']]);
    $students = $stmt->fetchAll();

    // Submissions
    $stmt = $conn->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = ?");
    $stmt->execute([$assignment_id]);
    $submissionsRaw = $stmt->fetchAll();
    foreach ($submissionsRaw as $sub) {
        $submissions[$sub['student_id']] = $sub;
    }
}
$updateMessage = '';
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_instructions'])) {
    $newInstructions = trim($_POST['instructions'] ?? '');
    $stmt = $conn->prepare("UPDATE assignments SET instructions = ? WHERE id = ?");
    $stmt->execute([$newInstructions, $assignment_id]);
    $updateMessage = "Instructions updated successfully.";

    // Refresh the local $assignment array with updated value
    $assignment['instructions'] = $newInstructions;
}
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scores'])) {
    foreach ($_POST['scores'] as $student_id => $score) {
        $score = trim($score);
        if ($score === '') {
            // Allow clearing score by setting NULL
            $score = null;
        } elseif (!is_numeric($score) || $score < 0) {
            // Invalid score input - skip or handle error
            continue;
        } else {
            $score = (int) $score;
        }

        // Check if submission exists for this student
        if (isset($submissions[$student_id])) {
            $stmt = $conn->prepare("UPDATE assignment_submissions SET score = ? WHERE assignment_id = ? AND student_id = ?");
            $stmt->execute([$score, $assignment_id, $student_id]);
        } else {
            // No submission yet; optionally you could ignore or insert a new row with null file and score
        }
    }
    // Reload submissions with updated scores
    $stmt = $conn->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = ?");
    $stmt->execute([$assignment_id]);
    $submissionsRaw = $stmt->fetchAll();
    $submissions = [];
    foreach ($submissionsRaw as $sub) {
        $submissions[$sub['student_id']] = $sub;
    }
    $updateMessage = "Scores updated successfully.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Assignment: <?php echo htmlspecialchars($assignment['title']); ?></title>
    <link rel="stylesheet" href="./styles/assignment.css">
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
        <h1><?php echo htmlspecialchars($assignment['title']); ?></h1>
        <p><strong>Subject:</strong> <?php echo htmlspecialchars($assignment['subject_name']); ?></p>
        <p><strong>Due Date:</strong> <?php echo htmlspecialchars($assignment['due_date']); ?></p>
        <h2>Instructions</h2>

        <?php if ($user_role === 'teacher'): ?>
            <form method="POST">
                <textarea name="instructions" rows="6"
                    cols="80"><?php echo htmlspecialchars($assignment['instructions']); ?></textarea><br>
                <button type="submit" name="save_instructions">Save Changes</button>
            </form>
            <?php if (!empty($updateMessage))
                echo "<p style='color:green;'>$updateMessage</p>"; ?>
        <?php else: ?>
            <p><?php echo nl2br(htmlspecialchars($assignment['instructions'] ?? 'No instructions provided.')); ?></p>
        <?php endif; ?>

        <?php if ($user_role === 'student'): ?>
            <h2>Your Submission</h2>
            <?php
            // Show existing submission if any
            $stmt = $conn->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
            $stmt->execute([$assignment_id, $student_id]);
            $existingSub = $stmt->fetch();
            if ($existingSub):
                ?>
                <p>You submitted on <?php echo htmlspecialchars($existingSub['submitted_at']); ?>:</p>
                <a href="<?php echo htmlspecialchars($existingSub['file_path']); ?>" target="_blank" download>Download your
                    submission</a>
            <?php else: ?>
                <p>You have not submitted yet.</p>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="submission_file" required accept=".pdf,.doc,.docx,.txt" />
                <button type="submit" name="submit_assignment">Submit Assignment</button>
            </form>
            <?php if ($submissionMessage): ?>
                <p><?php echo htmlspecialchars($submissionMessage); ?></p>
            <?php endif; ?>

        <?php elseif ($user_role === 'teacher'): ?>
            <h2>Submissions</h2>
            <form method="POST">
                <table border="1" cellpadding="8" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Submission Status</th>
                            <th>Submitted At</th>
                            <th>Download</th>
                            <th>Score</th> <!-- New column -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student):
                            $sub = $submissions[$student['student_id']] ?? null;
                            $currentScore = $sub['score'] ?? '';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td><?php echo $sub ? '<span style="color:green;">Submitted</span>' : '<span style="color:red;">Not Submitted</span>'; ?>
                                </td>
                                <td><?php echo $sub ? htmlspecialchars($sub['submitted_at']) : '-'; ?></td>
                                <td>
                                    <?php if ($sub): ?>
                                        <a href="<?php echo htmlspecialchars($sub['file_path']); ?>" target="_blank"
                                            download>Download</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="number" min="0" step="1" name="scores[<?php echo $student['student_id']; ?>]"
                                        value="<?php echo htmlspecialchars($currentScore); ?>" />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" name="save_scores">Save Scores</button>
            </form>
            <?php if (!empty($updateMessage))
                echo "<p style='color:green;'>$updateMessage</p>"; ?>
            </table>
        <?php endif; ?>
    </div>
    <script src="./scripts/dashboard.js"></script>
</body>

</html>