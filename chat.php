<?php
session_start();
require 'db.php'; // Your DB connection

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['user']['username'];
$user_role = $_SESSION['user']['role'];

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
        <button id="menuBtn" class="menu-btn" aria-label="Toggle menu">☰ Menu</button>
        <div class="sidebar">
            <h2>LMS</h2>
            <ul>
                <?php if ($user_role === 'teacher'): ?>
                    <li><a href="teacher_dashboard.php">🏠 Home</a></li>
                    <li><a href="todo.php">📝 To-Do List</a></li>
                    <li><a href="subjects.php">My Subjects</a></li>
                    <li><a href="materials.php">📤 Materials</a></li>
                    <li><a href="create_subject.php">➕ Create Subject</a></li>
                    <li><a href="chat.php">💬 Chat</a></li>
                <?php else: ?>
                    <li><a href="student_dashboard.php">🏠 Home</a></li>
                    <li><a href="todo.php">📝 To-Do List</a></li>
                    <li><a href="subjects.php">My Subjects</a></li>
                    <li><a href="materials.php">📤 Materials</a></li>
                    <li><a href="chat.php">💬 Chat</a></li>
                <?php endif; ?>
                <li><a href="logout.php" class="logout-link">🚪 Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div id="chat-container" role="main" aria-label="Chat interface">
                <div id="users-list">
                    <input type="text" id="user-search" placeholder="Search users..." autocomplete="off"
                        aria-label="Search users" />
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
                        <input type="text" id="message-input" placeholder="Type your message..." autocomplete="off"
                            aria-label="Message input" />
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
    <script src="./scripts/dashboard.js"></script>
</body>

</html>