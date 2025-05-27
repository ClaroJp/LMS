<?php

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
require 'db.php';

$subject_id = $_GET['id'] ?? 0;
if (!$subject_id)
    die("Subject not specified.");

$stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ?");
$stmt->execute([$subject_id]);
$subject = $stmt->fetch();
if (!$subject)
    die("Subject not found.");

$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];

// Access check
if ($user_role === 'student') {
    $stmt = $conn->prepare("SELECT * FROM student_subjects WHERE student_id = ? AND subject_id = ?");
    $stmt->execute([$user_id, $subject_id]);
    if ($stmt->rowCount() === 0)
        die("You are not enrolled in this subject.");
} elseif ($user_role === 'teacher') {
    if ($subject['teacher_id'] != $user_id)
        die("You do not have access to this subject.");
} else {
    die("Access denied.");
}

$assignmentMessage = '';
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
    $title = trim($_POST['assignment_title'] ?? '');
    $due_date = $_POST['assignment_due_date'] ?? '';
    $uploadOk = true;
    $description = trim($_POST['assignment_description'] ?? '');


    if ($title === '') {
        $assignmentMessage = "Assignment title cannot be empty.";
        $uploadOk = false;
    }

    if ($due_date === '') {
        $assignmentMessage = "Due date is required.";
        $uploadOk = false;
    }

    if (!isset($_FILES['assignment_file']) || $_FILES['assignment_file']['error'] !== UPLOAD_ERR_OK) {
        $assignmentMessage = "Please upload a valid assignment file.";
        $uploadOk = false;
    }

    // If all okay, process file upload
    if ($uploadOk) {

        $uploadDir = __DIR__ . '/assets/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $original_filename = basename($_FILES['assignment_file']['name']);
        $sanitized_filename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $original_filename);
        $targetFileName = time() . '_' . $sanitized_filename;
        $targetFile = $uploadDir . $targetFileName;

        if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $targetFile)) {
            $uploaded_file_path = 'assets/' . $targetFileName;

            $description = ''; // Optional - use $_POST['description'] if applicable
            $sql = "INSERT INTO assignments (subject_id, title, description, due_date) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$subject_id, $title, $description, $due_date]);

            $assignment_id = $conn->lastInsertId();
            $sql = "INSERT INTO assignment_files (assignment_id, file_path, original_filename) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$assignment_id, $uploaded_file_path, $original_filename]);

            $assignmentMessage = "Assignment uploaded successfully.";

            // Refresh assignments list
            $stmt = $conn->prepare("
    SELECT a.*, af.file_path, af.original_filename
    FROM assignments a
    LEFT JOIN assignment_files af ON a.id = af.assignment_id
    WHERE a.subject_id = ?
");
            $stmt->execute([$subject_id]);
            $assignments = $stmt->fetchAll();

        } else {
            $assignmentMessage = "Failed to upload file.";
        }
    }


}

// --- HANDLE STUDENT REMOVAL ---
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student_id'])) {
    $remove_student_id = intval($_POST['remove_student_id']);

    // Delete student enrollment
    $delStmt = $conn->prepare("DELETE FROM student_subjects WHERE subject_id = ? AND student_id = ?");
    $delStmt->execute([$subject_id, $remove_student_id]);

    header("Location: subject.php?id=" . $subject_id);
    exit;
}

// Handle invite code generation (only for teacher)
$inviteMessage = '';
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invite'])) {
    function generateCode($length = 6)
    {
        return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
    }

    // Generate unique code
    do {
        $code = generateCode();
        $check = $conn->prepare("SELECT * FROM subject_invites WHERE code = ?");
        $check->execute([$code]);
    } while ($check->rowCount() > 0);

    $expires_at = date('Y-m-d H:i:s', strtotime('+2 days'));

    $insert = $conn->prepare("INSERT INTO subject_invites (subject_id, code, expires_at) VALUES (?, ?, ?)");
    $insert->execute([$subject_id, $code, $expires_at]);

    $inviteMessage = "New invite code: <strong>$code</strong> (Expires in 2 days)";
}

// Handle new announcement submission (teacher only)
$announceMessage = '';
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_announcement'])) {
    $content = trim($_POST['announcement_content'] ?? '');
    if ($content !== '') {
        $insertAnn = $conn->prepare("INSERT INTO announcements (subject_id, content, created_at) VALUES (?, ?, NOW())");
        $insertAnn->execute([$subject_id, $content]);
        $announceMessage = "Announcement posted successfully.";
    } else {
        $announceMessage = "Announcement content cannot be empty.";
    }
}

// Fetch latest valid invite for display
$stmt = $conn->prepare("SELECT * FROM subject_invites WHERE subject_id = ? AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
$stmt->execute([$subject_id]);
$currentInvite = $stmt->fetch();

// Fetch assignments
$stmt = $conn->prepare("SELECT * FROM assignments WHERE subject_id = ?");
$stmt->execute([$subject_id]);
$assignments = $stmt->fetchAll();

// Fetch announcements
$stmt = $conn->prepare("SELECT * FROM announcements WHERE subject_id = ? ORDER BY created_at DESC");
$stmt->execute([$subject_id]);
$announcements = $stmt->fetchAll();

// Fetch students if teacher
$students = [];
if ($user_role === 'teacher') {
    $stmt = $conn->prepare("
        SELECT s.* FROM students s
        JOIN student_subjects ss ON s.user_id = ss.student_id
        WHERE ss.subject_id = ?
    ");
    $stmt->execute([$subject_id]);
    $students = $stmt->fetchAll();
}
// Handle AJAX student removal
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_remove_student_id'])) {
    $remove_student_id = intval($_POST['ajax_remove_student_id']);
    $delStmt = $conn->prepare("DELETE FROM student_subjects WHERE subject_id = ? AND student_id = ?");
    $success = $delStmt->execute([$subject_id, $remove_student_id]);

    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove student']);
    }
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo htmlspecialchars($subject['subject_name']); ?></title>
    <link rel="stylesheet" href="./styles/subject_dashboard.css" />
</head>

<body>
    <button id="menuBtn" class="menu-btn" aria-label="Toggle menu">☰ Menu</button>

    <div class="sidebar">
        <h2>LMS</h2>
        <ul>
            <?php if ($user_role === 'teacher'): ?>
                <li><a href="teacher_dashboard.php">🏠 Home</a></li>
                <li><a href="todo.php">📝 To-Do List</a></li>
                <li><a href="courses.php">📚 Subjects</a></li>
                <li><a href="create_subject.php">➕ Create Subject</a></li>
                <li><a href="chat.php">💬 Chat</a></li>
            <?php else: ?>
                <li><a href="student_dashboard.php">🏠 Home</a></li>
                <li><a href="todo.php">📝 To-Do List</a></li>
                <li><a href="courses.php">📚 Subjects</a></li>
                <li><a href="chat.php">💬 Chat</a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>
        </ul>
    </div>

    <div class="main-content" style="display: flex; gap: 30px;">
        <div style="flex: 1; max-width: 50%;">
            <h1><?php echo htmlspecialchars($subject['subject_name']); ?></h1>

            <?php if ($user_role === 'teacher'): ?>
                <section class="section">
                    <h2>Invite Code</h2>
                    <?php if ($currentInvite): ?>
                        <p>Current invite code: <strong><?php echo htmlspecialchars($currentInvite['code']); ?></strong>
                            (Expires at <?php echo htmlspecialchars($currentInvite['expires_at']); ?>)</p>
                    <?php else: ?>
                        <p>No active invite code.</p>
                    <?php endif; ?>

                    <form method="POST" style="margin-top:15px;">
                        <button type="submit" name="generate_invite" class="button">Generate New Invite Code</button>
                    </form>
                    <?php if ($inviteMessage): ?>
                        <p style="color: #4CAF50;"><?php echo $inviteMessage; ?></p>
                    <?php endif; ?>
                </section>

                <section class="section" style="margin-top: 20px;">
                    <h2>Make Announcement</h2>
                    <form method="POST">
                        <textarea name="announcement_content" rows="4" style="width: 100%; padding: 10px; font-size: 14px;"
                            placeholder="Write your announcement here..."></textarea>
                        <button type="submit" name="new_announcement" class="button">Post Announcement</button>
                    </form>
                    <?php if ($announceMessage): ?>
                        <p
                            style="color: <?php echo (strpos($announceMessage, 'successfully') !== false) ? '#4CAF50' : '#e03e23'; ?>;">
                            <?php echo $announceMessage; ?>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="section" style="margin-top: 20px;">
                <h2>Announcements</h2>
                <?php if (count($announcements) === 0): ?>
                    <p>No announcements yet.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($announcements as $ann): ?>
                            <li><strong><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($ann['created_at']))); ?>:</strong>
                                <?php echo nl2br(htmlspecialchars($ann['content'])); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <div style="flex: 1; max-width: 40%;">
            <section class="section">
                <h2>Assignments</h2>
                <?php if (count($assignments) === 0): ?>
                    <p>No assignments yet.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($assignments as $a): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($a['title']); ?></strong> - Due:
                                <?php echo htmlspecialchars($a['due_date']); ?><br>
                                <?php if (!empty($a['file_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($a['file_path']); ?>" target="_blank" download>
                                        Download: <?php echo htmlspecialchars($a['original_filename']); ?>
                                    </a>

                                <?php else: ?>
                                    No file uploaded.
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <?php if ($user_role === 'teacher'): ?>
                <section class="section" style="margin-top: 20px;">
                    <h2>Add New Assignment</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <label>
                            Title:<br />
                            <input type="text" name="assignment_title" required
                                style="width: 100%; padding: 8px; margin-bottom: 10px;">
                        </label><br />
                        <label>
                            <label>
                                Description:<br />
                                <textarea name="assignment_description" rows="3"
                                    style="width: 100%; padding: 8px; margin-bottom: 10px;"></textarea>
                            </label><br />

                            Due Date:<br />
                            <input type="date" name="assignment_due_date" required
                                style="padding: 8px; margin-bottom: 10px;">
                        </label><br />
                        <label>
                            Assignment File:<br />
                            <input type="file" name="assignment_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip" required
                                style="margin-bottom: 10px;">
                        </label><br />
                        <button type="submit" name="add_assignment" class="button">Upload Assignment</button>
                    </form>
                    <?php if ($assignmentMessage): ?>
                        <p
                            style="color: <?php echo strpos($assignmentMessage, 'successfully') !== false ? '#4CAF50' : '#e03e23'; ?>">
                            <?php echo htmlspecialchars($assignmentMessage); ?>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($user_role === 'teacher'): ?>
                <section class="section" style="margin-top: 20px;">
                    <h2>Enrolled Students</h2>
                    <?php if (count($students) === 0): ?>
                        <p>No students enrolled yet.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($students as $student): ?>
                                <li id="student-<?php echo $student['user_id']; ?>">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    <button onclick="removeStudent(<?php echo $student['user_id']; ?>)"
                                        style="margin-left: 10px; background: #e03e23; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                                        Remove
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <script src="./scripts/dashboard.js"></script>
</body>

</html>