<?php
session_start();
require_once 'db.php'; // This defines $conn

header('Content-Type: application/json');

if (!isset($_SESSION['user']['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (empty($_GET['partner'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Partner parameter missing']);
    exit;
}

$me = trim($_SESSION['user']['username']);
$partner = trim($_GET['partner']);

try {
    // Use the existing PDO instance from db.php
    $stmt = $conn->prepare("
        SELECT sender, receiver, message, timestamp
        FROM messages
        WHERE (sender = :me AND receiver = :partner)
           OR (sender = :partner AND receiver = :me)
        ORDER BY timestamp ASC
    ");
    $stmt->execute([
        ':me' => $me,
        ':partner' => $partner
    ]);
    $messages = $stmt->fetchAll();

    // Mark messages sent by current user
    foreach ($messages as &$msg) {
        $msg['sent'] = ($msg['sender'] === $me);
    }

    echo json_encode($messages);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
