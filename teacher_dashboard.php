<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

require 'db.php';

// Fetch teacher info
$stmt = $conn->prepare("SELECT * FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$teacher = $stmt->fetch();

// Redirect if profile is incomplete
if (!$teacher || empty($teacher['department']) || empty($teacher['expertise'])) {
    header("Location: teacher_details.php");
    exit;
}

// Fetch subjects
$stmt = $conn->prepare("SELECT * FROM subjects WHERE teacher_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$subjects = $stmt->fetchAll();

// Handle material upload
$uploadMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['material_file']['tmp_name'];
        $fileName = basename($_FILES['material_file']['name']);
        $uploadDir = 'uploads/materials/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $destPath = $uploadDir . uniqid() . '-' . $fileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $subject_id = $_POST['subject_id'] ?? 0;
            if ($subject_id && in_array($subject_id, array_column($subjects, 'id'))) {
                $stmt = $conn->prepare("INSERT INTO materials (subject_id, file_path, uploaded_at) VALUES (?, ?, NOW())");
                $stmt->execute([$subject_id, $destPath]);
                $uploadMessage = "Material uploaded successfully.";
            } else {
                $uploadMessage = "Invalid subject selected.";
                unlink($destPath);
            }
        } else {
            $uploadMessage = "Error moving uploaded file.";
        }
    } else {
        $uploadMessage = "Please select a file to upload.";
    }
}

// Fetch student progress per subject
$studentsProgress = [];
foreach ($subjects as $subject) {
    $stmt = $conn->prepare("
        SELECT s.student_id, s.first_name, s.last_name,
               IFNULL(sp.progress, 'Not started') AS progress
        FROM students s
        JOIN student_subjects ss ON s.student_id = ss.student_id
        LEFT JOIN student_progress sp ON sp.student_id = s.student_id AND sp.subject_id = ss.subject_id
        WHERE ss.subject_id = ?
    ");
    $stmt->execute([$subject['id']]);
    $studentsProgress[$subject['id']] = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="./styles/dashboard.css">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        .upload-message {
            color: green;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <button id="menuBtn" class="menu-btn" aria-label="Toggle menu">☰ Menu</button>

    <div class="sidebar">
        <h2>LMS</h2>
        <ul>
            <li><a href="teacher_dashboard.php">🏠 Home</a></li>
            <li><a href="todo.php">📝 To-Do List</a></li>
            <li><a href="courses.php">📚 Subjects</a></li>
            <li><a href="create_subject.php">➕ Create Subject</a></li>
            <li><a href="notifications.php">🔔 Notifications</a></li>
            <li><a href="student_progress.php">📈 Assignment Progress</a></li>
            <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user']['username']); ?>!</h1>
        <p>
            Department: <?php echo htmlspecialchars($teacher['department']); ?> |
            Expertise: <?php echo htmlspecialchars($teacher['expertise']); ?>
        </p>

        <div class="section">
            <h2>📚 Your Subjects</h2>
            <?php if (count($subjects) === 0): ?>
                <p>You have not created any subjects yet.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($subjects as $subject): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong><br>
                            <a href="subject.php?id=<?php echo $subject['id']; ?>">➡️ Go to Subject Dashboard</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>📤 Upload Materials</h2>
            <form method="POST" enctype="multipart/form-data">
                <label for="subject_id">Select Subject:</label>
                <select name="subject_id" id="subject_id" required>
                    <option value="" disabled selected>-- Choose Subject --</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select><br><br>
                <input type="file" name="material_file" required><br><br>
                <button type="submit" name="upload_material" class="button">Upload</button>
            </form>
            <?php if ($uploadMessage): ?>
                <p class="upload-message"><?php echo htmlspecialchars($uploadMessage); ?></p>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>📊 Track Student Progress</h2>
            <?php if (count($subjects) === 0): ?>
                <p>No subjects, no students to track.</p>
            <?php else: ?>
                <?php foreach ($subjects as $subject): ?>
                    <h3><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                    <?php if (empty($studentsProgress[$subject['id']])): ?>
                        <p>No students enrolled yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Progress Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentsProgress[$subject['id']] as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['progress']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>💬 Message Center</h2>
            <p>This feature is coming soon! Stay tuned.</p>
        </div>
    </div>

    <script src="./scripts/dashboard.js"></script>
</body>
</html>
