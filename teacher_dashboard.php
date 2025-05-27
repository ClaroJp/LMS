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
    <style>
        /* Quick styling for tables */
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background-color: #4CAF50;
            color: white;
        }

        .upload-message {
            margin-top: 10px;
            font-weight: bold;
        }

        .upload-message.success {
            color: green;
        }

        .upload-message.error {
            color: red;
        }

        .material-list {
            list-style-type: none;
            padding-left: 0;
            margin-top: 5px;
            margin-bottom: 20px;
        }

        .material-list li {
            margin-bottom: 4px;
        }

        .material-list a {
            color: #007bff;
            text-decoration: none;
        }

        .material-list a:hover {
            text-decoration: underline;
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
            <li><a href="subjects.php">My Subjects</a></li>
            <li><a href="create_subject.php">➕ Create Subject</a></li>
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

        <section class="section" aria-label="Upload Materials">
            <h2>📤 Upload Materials</h2>
            <form method="POST" enctype="multipart/form-data" aria-describedby="uploadHelp">
                <label for="subject_id">Select Subject:</label>
                <select name="subject_id" id="subject_id" required>
                    <option value="" disabled selected>-- Choose Subject --</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br><br>
                <label for="material_file">Choose file to upload:</label>
                <input type="file" name="material_file" id="material_file" required />
                <br><br>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
                <button type="submit" name="upload_material" class="button">Upload</button>
            </form>
            <?php if ($uploadMessage): ?>
                <p id="uploadHelp" class="upload-message <?php echo $uploadError ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($uploadMessage); ?>
                </p>
            <?php endif; ?>
        </section>

        <?php if (count($subjects) > 0): ?>
            <section class="section" aria-label="Uploaded Materials">
                <h2>🗂 Uploaded Materials</h2>
                <?php foreach ($subjects as $subject): ?>
                    <h3><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                    <?php if (empty($materialsBySubject[$subject['id']])): ?>
                        <p>No materials uploaded yet.</p>
                    <?php else: ?>
                        <ul class="material-list">
                            <?php foreach ($materialsBySubject[$subject['id']] as $mat): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($mat['file_path']); ?>" target="_blank"
                                        rel="noopener noreferrer">
                                        <?php echo basename($mat['file_path']); ?>
                                    </a>
                                    <small>(uploaded at <?php echo htmlspecialchars($mat['uploaded_at']); ?>)</small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <script src="./scripts/dashboard.js"></script>
</body>

</html>