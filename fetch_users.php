<?php
session_start();
require_once 'db.php'; // This gives you $conn (PDO instance)

header('Content-Type: application/json');

if (!isset($_SESSION['user']['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$currentUser = $_SESSION['user']['username'];

try {
    // Use the existing PDO connection $conn from db.php
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE username != :current");
    $stmt->execute(['current' => $currentUser]);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($users);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
