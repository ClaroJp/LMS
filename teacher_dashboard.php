<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

require 'db.php';

// CSRF token generation for upload form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Fetch teacher info
$stmt = $conn->prepare("SELECT * FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$teacher = $stmt->fetch();

// Redirect if teacher profile is incomplete
if (!$teacher || empty($teacher['department']) || empty($teacher['expertise'])) {
    header("Location: teacher_details.php");
    exit;
}

// Fetch subjects for this teacher
$stmt = $conn->prepare("SELECT * FROM subjects WHERE teacher_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$subjects = $stmt->fetchAll();

// Fetch uploaded materials grouped by subject
$materialsBySubject = [];
if (count($subjects) > 0) {
    $subjectIds = array_column($subjects, 'id');
    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $stmt = $conn->prepare("SELECT * FROM materials WHERE subject_id IN ($placeholders) ORDER BY uploaded_at DESC");
    $stmt->execute($subjectIds);
    $materials = $stmt->fetchAll();
    foreach ($materials as $mat) {
        $materialsBySubject[$mat['subject_id']][] = $mat;
    }
}

// Handle material upload
$uploadMessage = '';
$uploadError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $uploadMessage = "Invalid form submission.";
        $uploadError = true;
    } elseif (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadMessage = "Please select a file to upload.";
        $uploadError = true;
    } else {
        $fileTmpPath = $_FILES['material_file']['tmp_name'];
        $fileName = basename($_FILES['material_file']['name']);
        // Sanitize filename
        $fileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileName);

        $uploadDir = 'uploads/materials/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destPath = $uploadDir . uniqid() . '-' . $fileName;

        $subject_id = (int) ($_POST['subject_id'] ?? 0);
        $validSubjectIds = array_column($subjects, 'id');

        if (!in_array($subject_id, $validSubjectIds, true)) {
            $uploadMessage = "Invalid subject selected.";
            $uploadError = true;
        } elseif (move_uploaded_file($fileTmpPath, $destPath)) {
            // Save upload record to DB
            $stmt = $conn->prepare("INSERT INTO materials (subject_id, file_path, uploaded_at) VALUES (?, ?, NOW())");
            if ($stmt->execute([$subject_id, $destPath])) {
                $uploadMessage = "Material uploaded successfully.";
                // Update materials list
                $materialsBySubject[$subject_id][] = [
                    'subject_id' => $subject_id,
                    'file_path' => $destPath,
                    'uploaded_at' => date('Y-m-d H:i:s'),
                ];
            } else {
                $uploadMessage = "Database error saving material.";
                $uploadError = true;
                unlink($destPath);
            }
        } else {
            $uploadMessage = "Error moving uploaded file.";
            $uploadError = true;
        }
    }
}

// Fetch enrolled students with progress (simplified demo)
$studentsProgress = [];
foreach ($subjects as $subject) {
    $stmt = $conn->prepare("
        SELECT s.user_id, s.first_name, s.last_name,
            IFNULL(sp.progress, 'Not started') AS progress
        FROM students s
        JOIN student_subjects ss ON s.user_id = ss.student_id
        LEFT JOIN student_progress sp ON sp.student_id = s.user_id AND sp.subject_id = ss.subject_id
        WHERE ss.subject_id = ?
    ");
    $stmt->execute([$subject['id']]);
    $studentsProgress[$subject['id']] = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="./styles/dashboard.css" />
</head>
<style>
    /* Your Subjects section */

.section {
    background-color: #fff;
    padding: 1.5rem 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.section h2 {
    font-size: 1.6rem;
    margin-bottom: 1rem;
    color: #2c3e50;
}

.section p {
    font-size: 1rem;
    color: #666;
}

.section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.section ul li {
    background: #f7f9fc;
    border-left: 4px solid #3498db;
    padding: 1rem 1.2rem;
    margin-bottom: 0.8rem;
    border-radius: 6px;
    transition: background-color 0.2s ease;
}

.section ul li:hover {
    background-color: #e8f0fe;
}

.section ul li strong {
    font-size: 1.1rem;
    color: #34495e;
    display: block;
    margin-bottom: 0.3rem;
}

.section ul li a {
    font-weight: 500;
    color: #2980b9;
    text-decoration: none;
    font-size: 0.95rem;
}

.section ul li a:hover {
    text-decoration: underline;
}

</style>
<body>
    <button id="menuBtn" class="menu-btn" aria-label="Toggle menu">☰ Menu</button>

    <div class="sidebar">
        <h2>LMS</h2>
        <ul>
            <li><a href="teacher_dashboard.php">🏠 Home</a></li>
            <li><a href="todo.php">📝 To-Do List</a></li>
            <li><a href="subjects.php">My Subjects</a></li>
            <li><a href="create_subject.php">➕ Create Subject</a></li>
            <li><a href="materials.php">📤 Materials</a></li>
            <li><a href="chat.php">💬 Chat</a></li>
            <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>

        </ul>
    </div>

    <div class="main-content">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user']['username']); ?>!</h1>
        <p>
            Department: <?php echo htmlspecialchars($teacher['department']); ?> |
            Expertise: <?php echo htmlspecialchars($teacher['expertise']); ?>
        </p>

        <section class="section" aria-label="Your Subjects">
            <h2>📚 Your Subjects</h2>
            <?php if (count($subjects) === 0): ?>
                <p>You have not created any subjects yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($subjects as $subject): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                            <br>
                            <a href="subject.php?id=<?php echo $subject['id']; ?>">➡️ Go to Subject Dashboard</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <script src="./scripts/dashboard.js"></script>
</body>

</html>