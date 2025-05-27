<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
if ($_SESSION['user']['role'] !== 'teacher' && $_SESSION['user']['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require 'db.php';

$isTeacher = $_SESSION['user']['role'] === 'teacher';
$user_role = $_SESSION['user']['role'];

// Fetch subjects for teacher
$subjects = [];
if ($isTeacher) {
    $stmt = $conn->prepare("SELECT * FROM subjects WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $subjects = $stmt->fetchAll();
} else {
    // Fetch subjects student is enrolled in
    $stmt = $conn->prepare("
        SELECT sub.* FROM subjects sub
        JOIN student_subjects ss ON sub.id = ss.subject_id
        WHERE ss.student_id = ?
    ");
    $stmt->execute([$_SESSION['user']['id']]);
    $subjects = $stmt->fetchAll();
}

// CSRF token for upload form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Upload logic (for teachers only)
$uploadMessage = '';
$uploadError = false;

if ($isTeacher && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $uploadMessage = "Invalid form submission.";
        $uploadError = true;
    } elseif (!isset($_FILES['material_file']) || $_FILES['material_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadMessage = "Please select a file to upload.";
        $uploadError = true;
    } else {
        $fileTmpPath = $_FILES['material_file']['tmp_name'];
        $fileName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['material_file']['name']));
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
            $stmt = $conn->prepare("INSERT INTO materials (subject_id, file_path, uploaded_at) VALUES (?, ?, NOW())");
            if ($stmt->execute([$subject_id, $destPath])) {
                $uploadMessage = "Material uploaded successfully.";
            } else {
                unlink($destPath);
                $uploadMessage = "Database error saving material.";
                $uploadError = true;
            }
        } else {
            $uploadMessage = "Error moving uploaded file.";
            $uploadError = true;
        }
    }
}

// Fetch all uploaded materials grouped by subject
$subjectIds = array_column($subjects, 'id');
$materialsBySubject = [];

if (count($subjectIds)) {
    $placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
    $stmt = $conn->prepare("SELECT * FROM materials WHERE subject_id IN ($placeholders) ORDER BY uploaded_at DESC");
    $stmt->execute($subjectIds);
    $materials = $stmt->fetchAll();
    foreach ($materials as $mat) {
        $materialsBySubject[$mat['subject_id']][] = $mat;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Learning Materials</title>
    <link rel="stylesheet" href="./styles/materials.css">
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
        <h1><?php echo $isTeacher ? "Upload & Manage Materials" : "Download Learning Materials"; ?></h1>

        <?php if ($isTeacher): ?>
            <section class="section" aria-label="Upload Materials">
                <h2>📤 Upload Materials</h2>
                <form method="POST" enctype="multipart/form-data">
                    <label for="subject_id">Select Subject:</label>
                    <select name="subject_id" id="subject_id" required>
                        <option value="" disabled selected>-- Choose Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select><br><br>
                    <label for="material_file">Choose file:</label>
                    <input type="file" name="material_file" id="material_file" required><br><br>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <button type="submit" name="upload_material" class="button">Upload</button>
                </form>
                <?php if ($uploadMessage): ?>
                    <p class="upload-message <?php echo $uploadError ? 'error' : 'success'; ?>">
                        <?php echo htmlspecialchars($uploadMessage); ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="section" aria-label="Materials List">
            <h2>🗂 Available Materials</h2>
            <?php foreach ($subjects as $subject): ?>
                <h3><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                <?php if (empty($materialsBySubject[$subject['id']])): ?>
                    <p>No materials uploaded yet.</p>
                <?php else: ?>
                    <ul class="material-list">
                        <?php foreach ($materialsBySubject[$subject['id']] as $mat): ?>
                            <li>
                                <a href="<?php echo htmlspecialchars($mat['file_path']); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo basename($mat['file_path']); ?>
                                </a>
                                <small>(uploaded at <?php echo htmlspecialchars($mat['uploaded_at']); ?>)</small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    </div>

    <script src="./scripts/dashboard.js"></script>
</body>
</html>
