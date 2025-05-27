<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']['id'])) {
    http_response_code(403);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
$stmt->execute([$_SESSION['user']['id']]);
http_response_code(200);
