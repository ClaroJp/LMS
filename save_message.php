<?php
session_start();
require_once 'db.php'; // Your DB connection setup

header('Content-Type: application/json');

if (!isset($_SESSION['user']['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['to'], $data['from'], $data['text'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$to = $data['to'];
$from = $data['from'];
$text = $data['text'];

try {
    $db = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $db->prepare("INSERT INTO messages (`sender`, `receiver`, `message`, `timestamp`) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$from, $to, $text]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>
