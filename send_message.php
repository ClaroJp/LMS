<?php
session_start();
require_once 'db.php'; // assumes this defines $conn as a PDO connection

header('Content-Type: application/json');

// ✅ Use consistent session structure
if (!isset($_SESSION['user']['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// ✅ Parse and validate input
$data = json_decode(file_get_contents('php://input'), true);

if (
    !$data ||
    !isset($data['receiver'], $data['message']) ||
    empty(trim($data['receiver'])) ||
    empty(trim($data['message']))
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$sender = $_SESSION['user']['username'];
$receiver = trim($data['receiver']);
$message = trim($data['message']);
$timestamp = date('Y-m-d H:i:s');

// ✅ Insert into database
try {
    $stmt = $conn->prepare("
        INSERT INTO messages (sender, receiver, message, timestamp)
        VALUES (:sender, :receiver, :message, :timestamp)
    ");
    $stmt->execute([
        ':sender' => $sender,
        ':receiver' => $receiver,
        ':message' => $message,
        ':timestamp' => $timestamp
    ]);

    echo json_encode(['success' => true, 'timestamp' => $timestamp]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
        // 'details' => $e->getMessage() // Uncomment for debugging
    ]);
}
