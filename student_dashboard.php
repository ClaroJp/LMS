<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require 'db.php';

// Fetch student info
$stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$student = $stmt->fetch();

// Redirect if student profile incomplete
if (!$student || empty($student['course']) || empty($student['year_level'])) {
    header("Location: student_details.php");
    exit;
}

$message = '';
$message_type = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject_code'])) {
    $code = strtoupper(trim($_POST['subject_code']));

    if (empty($code)) {
        $message = "Please enter a subject invite code.";
        $message_type = 'error';
    } else {
        // Check if code exists and is valid (not expired)
        $stmt = $conn->prepare("SELECT * FROM subject_invites WHERE code = ? AND expires_at > NOW()");
        $stmt->execute([$code]);
        $invite = $stmt->fetch();

        if (!$invite) {
            $message = "Invalid or expired invite code.";
            $message_type = 'error';
        } else {
            $subject_id = $invite['subject_id'];

            // Check if student already enrolled in this subject
            $stmt = $conn->prepare("SELECT * FROM student_subjects WHERE student_id = ? AND subject_id = ?");
            $stmt->execute([$_SESSION['user']['id'], $subject_id]);
            if ($stmt->rowCount() > 0) {
                $message = "You are already enrolled in this subject.";
                $message_type = 'error';
            } else {
                // Enroll student
                $insert = $conn->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)");
                $insert->execute([$_SESSION['user']['id'], $subject_id]);
                $message = "Successfully enrolled in the subject!";
                $message_type = 'success';
            }
        }
    }
}

// Fetch enrolled subjects for this student
$stmt = $conn->prepare("
    SELECT sub.* 
    FROM subjects sub
    JOIN student_subjects ss ON sub.id = ss.subject_id
    WHERE ss.student_id = ?
");
$stmt->execute([$_SESSION['user']['id']]);
$enrolledSubjects = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="./styles/dashboard.css" />
</head>

<body>
    <button id="menuBtn" class="menu-btn" aria-label="Toggle menu">☰ Menu</button>

    <div class="sidebar">
        <h2>LMS</h2>
        <ul>
            <li><a href="student_dashboard.php">🏠 Home</a></li>
            <li><a href="todo.php">📝 To-Do List</a></li>
            <li><a href="subjects.php">My Subjects</a></li>
            <li><a href="chat.php">💬 Chat</a></li>
            <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>
        </ul>
    </div>


    <div class="main-content">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user']['username']); ?>!</h1>
        <p>
            Course: <?php echo htmlspecialchars($student['course']); ?> |
            Year: <?php echo htmlspecialchars($student['year_level']); ?>
        </p>

        <?php if ($message): ?>
            <p class="<?php echo $message_type === 'success' ? 'msg-success' : 'msg-error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php endif; ?>

        <div class="section">
            <h2>➕ Join a Subject</h2>
            <form method="POST" class="join-subject-form">
                <label for="subject_code">Enter Subject Invite Code:</label><br />
                <input type="text" id="subject_code" name="subject_code" maxlength="6" required />
                <button type="submit">Join</button>
            </form>
        </div>

        <div class="section">
            <h2>📚 Your Subjects</h2>
            <?php if (count($enrolledSubjects) === 0): ?>
                <p>You are not enrolled in any subjects yet.</p>
            <?php else: ?>
                <div class="subject-cards">
                    <?php foreach ($enrolledSubjects as $subject): ?>
                        <a href="subject.php?id=<?php echo $subject['id']; ?>" class="subject-card">
                            <h3><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                            <p><?php echo htmlspecialchars($subject['description']); ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="./scripts/dashboard.js"></script>
</body>

</html>