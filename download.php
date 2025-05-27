<?php
session_start();
require 'db.php';

// Check user login & permission as needed
if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$file_id = $_GET['file_id'] ?? 0;
if (!$file_id) {
    exit('File ID not specified.');
}

// Get file info from DB
$stmt = $conn->prepare("SELECT af.file_path, af.original_filename, a.subject_id FROM assignment_files af JOIN assignments a ON af.assignment_id = a.id WHERE af.id = ?");
$stmt->execute([$file_id]);
$file = $stmt->fetch();

if (!$file) {
    exit('File not found.');
}

// Optional: Check if user has access to this subject/file
$user_role = $_SESSION['user']['role'];
$user_id = $_SESSION['user']['id'];
$subject_id = $file['subject_id'];

// (Add your access checks here as in your main script)

$fullPath = __DIR__ . '/' . $file['file_path'];
if (!file_exists($fullPath)) {
    exit('File missing on server.');
}

// Send headers and output file for download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['original_filename']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;
