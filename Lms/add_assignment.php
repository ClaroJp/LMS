<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}

require 'db.php';

$teacher_id = $_SESSION['user']['id'];
$subject_id = intval($_GET['subject_id'] ?? 0);

// Check if subject belongs to logged-in teacher
$stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = ?");
$stmt->execute([$subject_id, $teacher_id]);
$subject = $stmt->fetch();

if (!$subject) {
    die("Invalid subject or access denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];

    if (!$title || !$due_date) {
        $error = "Title and due date are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO assignments (subject_id, title, description, due_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$subject_id, $title, $description, $due_date]);
        $assignment_id = $conn->lastInsertId();

        // Handle file upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/assignments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $tmpName = $_FILES['file']['tmp_name'];
            $originalName = basename($_FILES['file']['name']);
            $uniqueName = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
            $targetFile = $uploadDir . $uniqueName;

            if (move_uploaded_file($tmpName, $targetFile)) {
                $stmt = $conn->prepare("INSERT INTO assignment_files (assignment_id, file_path, original_filename) VALUES (?, ?, ?)");
                $stmt->execute([$assignment_id, $targetFile, $originalName]);
            } else {
                $uploadError = "Failed to upload file.";
            }
        }
        header("Location: teacher_dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Assignment to <?php echo htmlspecialchars($subject['subject_name']); ?></title>
</head>
<body>
<h1>Add Assignment to "<?php echo htmlspecialchars($subject['subject_name']); ?>"</h1>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php if (!empty($uploadError)) echo "<p style='color:red;'>$uploadError</p>"; ?>

<form method="POST" enctype="multipart/form-data">
    <label>Assignment Title:</label><br>
    <input type="text" name="title" required><br>
    <label>Description:</label><br>
    <textarea name="description"></textarea><br>
    <label>Due Date:</label><br>
    <input type="date" name="due_date" required><br>
    <label>Attach File (optional):</label><br>
    <input type="file" name="file"><br><br>
    <button type="submit">Add Assignment</button>
</form>
<a href="teacher_dashboard.php">Back to Dashboard</a>
</body>
</html>
