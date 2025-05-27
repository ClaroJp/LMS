<?php
session_start();
require 'db.php';

if (!isset($_SESSION['username'])) {
    http_response_code(401);
    exit(json_encode([]));
}

$username = $_SESSION['username'];

try {
    // Fetch distinct conversation partners and last message time
    $sql = "
        SELECT 
            CASE
                WHEN sender = :username THEN receiver
                ELSE sender
            END AS partner,
            MAX(timestamp) AS last_message_time
        FROM chat_messages
        WHERE sender = :username OR receiver = :username
        GROUP BY partner
        ORDER BY last_message_time DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':username' => $username]);
    $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conversations = [];

    // Fetch last message for each partner
    $messageStmt = $conn->prepare("
        SELECT sender, message 
        FROM chat_messages
        WHERE (sender = :username AND receiver = :partner)
           OR (sender = :partner AND receiver = :username)
        ORDER BY timestamp DESC
        LIMIT 1
    ");

    foreach ($partners as $row) {
        $partner = $row['partner'];

        $messageStmt->execute([
            ':username' => $username,
            ':partner' => $partner
        ]);
        $msg = $messageStmt->fetch(PDO::FETCH_ASSOC);

        $conversations[] = [
            'partner' => $partner,
            'last_message_time' => $row['last_message_time'],
            'last_message' => $msg ? $msg['message'] : '',
            'sent_by_me' => $msg && $msg['sender'] === $username
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($conversations);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]);
}
