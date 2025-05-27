<?php
session_start();
require 'db.php'; // already in root, no need for ../

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND role = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_id, $role]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($notifications);
