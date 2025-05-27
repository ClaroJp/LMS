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

// Handle student removal (teacher)
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student_id'])) {
    $remove_student_id = intval($_POST['remove_student_id']);
    $delStmt = $conn->prepare("DELETE FROM student_subjects WHERE subject_id = ? AND student_id = ?");
    $delStmt->execute([$subject_id, $remove_student_id]);
    header("Location: subject.php?id=" . $subject_id);
    exit;
}

// Invite code generation (teacher)
$inviteMessage = '';
if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invite'])) {
    function generateCode($length = 6)
    {
        return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
    }
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

// New announcement (teacher)
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

// Add assignment (teacher)
$assignmentMessage = '';

if ($user_role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_assignment'])) {
    $title = trim($_POST['assignment_title'] ?? '');
    $due_date = $_POST['assignment_due'] ?? '';
    $file = $_FILES['assignment_file'] ?? null;

    if ($title && $due_date && $file && $file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        $filename = time() . '_' . basename($file['name']);
        $filePath = $uploadDir . $filename;

        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $stmt = $conn->prepare("INSERT INTO assignments (subject_id, title, file_path, due_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$subject_id, $title, $filePath, $due_date]);

            // Redirect to avoid duplicate insertion on page refresh
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $assignmentMessage = "Failed to upload file.";
        }
    } else {
        $assignmentMessage = "Please fill all fields and upload a valid file.";
    }
}


// Fetch data
$stmt = $conn->prepare("SELECT * FROM subject_invites WHERE subject_id = ? AND expires_at > NOW() ORDER BY expires_at DESC LIMIT 1");
$stmt->execute([$subject_id]);
$currentInvite = $stmt->fetch();

$stmt = $conn->prepare("SELECT * FROM assignments WHERE subject_id = ?");
$stmt->execute([$subject_id]);
$assignments = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM announcements WHERE subject_id = ? ORDER BY created_at DESC");
$stmt->execute([$subject_id]);
$announcements = $stmt->fetchAll();

$students = [];
if ($user_role === 'teacher') {
    $stmt = $conn->prepare("
    SELECT s.user_id, s.first_name, s.last_name 
    FROM students s
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
<style>
    .section {
        margin-top: 2rem;
    }

    .section h2 {
        font-size: 1.6rem;
        margin-bottom: 1rem;
        border-bottom: 2px solid #2980b9;
        padding-bottom: 0.3rem;
        color: #2980b9;
    }

    .section ul {
        list-style-type: none;
        padding-left: 0;
        margin-bottom: 0;
    }

    .section ul li {
        background: #f9f9f9;
        padding: 0.8rem 1rem;
        margin-bottom: 0.6rem;
        border-radius: 6px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        font-size: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .section ul li a {
        color: #2980b9;
        text-decoration: none;
        font-weight: 600;
        margin-right: 1rem;
    }

    .section ul li a:hover,
    .section ul li a:focus {
        text-decoration: underline;
    }

    .section ul li button {
        background-color: #e74c3c;
        border: none;
        color: white;
        padding: 0.3rem 0.7rem;
        border-radius: 4px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .section ul li button:hover,
    .section ul li button:focus {
        background-color: #c0392b;
        outline: none;
    }

    .section ul li span {
        margin-left: auto;
        color: #555;
        font-size: 0.9rem;
    }
</style>

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
                    <form method="POST">
                        <button type="submit" name="generate_invite" class="button">Generate New Invite Code</button>
                    </form>
                    <?php if ($inviteMessage): ?>
                        <p style="color: #4CAF50;"><?php echo $inviteMessage; ?></p>
                    <?php endif; ?>
                </section>

                <section class="section" style="margin-top: 20px;">
                    <h2>Make Announcement</h2>
                    <form method="POST">
                        <textarea name="announcement_content" rows="4"
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

                <section class="section" style="margin-top: 20px;">
                    <h2>Add Assignment</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="text" name="assignment_title" placeholder="Assignment Title" required><br><br>
                        <input type="date" name="assignment_due" required><br><br>
                        <input type="file" name="assignment_file" accept=".pdf,.doc,.docx,.txt" required><br><br>
                        <button type="submit" name="add_assignment" class="button">Upload Assignment</button>
                    </form>
                    <?php if ($assignmentMessage): ?>
                        <p
                            style="color: <?php echo (strpos($assignmentMessage, 'successfully') !== false) ? '#4CAF50' : '#e03e23'; ?>;">
                            <?php echo $assignmentMessage; ?>
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
                                <a href="assignment.php?id=<?php echo $a['id']; ?>">
                                    <?php echo htmlspecialchars($a['title']); ?>
                                </a> - Due: <?php echo htmlspecialchars($a['due_date']); ?><br>
                                <a href="<?php echo htmlspecialchars($a['file_path']); ?>" target="_blank" download>Download
                                    Assignment</a>

                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ($user_role === 'teacher'): ?>
                <section class="section" style="margin-top: 20px;">
                    <h2>Enrolled Students</h2>
                    <?php if (count($students) === 0): ?>
                        <p>No students enrolled yet.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($students as $student): ?>
                                <li id="student-<?php echo $student['user_id']; ?>">
                                    <a href="student.php?id=<?php echo $student['user_id']; ?>">
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </a>
                                    <button onclick="removeStudent(<?php echo $student['user_id']; ?>)"
                                        style="margin-left: 10px;">Remove</button>
                                </li>
                            <?php endforeach; ?>

                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </div>
    <script src="./scripts/remove.js"></script>
    <script src="./scripts/dashboard.js"></script>
</body>

</html>