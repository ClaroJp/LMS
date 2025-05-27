<?php
session_start();
require 'db.php'; // Your DB connection

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['user']['username'];

// Fetch all users with last_activity and role
$stmt = $conn->prepare("SELECT id, username, role, last_activity FROM users");
$stmt->execute();
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pass full users list to JS
$usersJson = json_encode($allUsers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>LMS Chat</title>
    <link rel="stylesheet" href="./styles/chat.css">
</head>
<body>
<div id="page-wrapper">
    <div class="sidebar" role="navigation" aria-label="Main navigation">
        <h2>LMS</h2>
        <ul>
            <li><a href="student_dashboard.php">🏠 Home</a></li>
            <li><a href="todo.php">📝 To-Do List</a></li>
            <li><a href="courses.php">📚 Courses</a></li>
            <li><a href="chat.php" aria-current="page">💬 Chat</a></li>
            <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div id="chat-container" role="main" aria-label="Chat interface">
            <div id="users-list">
                <input type="text" id="user-search" placeholder="Search users..." autocomplete="off" aria-label="Search users" />
                <ul id="users-ul" role="list" aria-live="polite" aria-relevant="additions removals">
                    <!-- JS will render users here -->
                </ul>
            </div>
            <div id="chat-window" style="display:none;">
                <div id="chat-header">
                    <button id="back-to-users-btn" aria-label="Back to user list">← Back</button>
                    <h2 id="chat-with">Chat</h2>
                </div>
                <div id="messages" role="log" aria-live="polite" aria-relevant="additions">
                    <!-- Messages will appear here -->
                </div>
                <div id="input-container">
                    <input type="text" id="message-input" placeholder="Type your message..." autocomplete="off" aria-label="Message input" />
                    <button id="send-btn">Send</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
  const username = <?php echo json_encode($username); ?>;
  const allUsers = <?php echo $usersJson; ?>;
</script>
<script src="http://localhost:3000/socket.io/socket.io.js"></script>
<script src="./scripts/chat.js" defer></script>
</body>
</html>
