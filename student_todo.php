<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

require 'db.php';

$stmt = $conn->prepare("SELECT * FROM STUDENT_TASKS WHERE STUDENT_ID = ? ORDER BY CREATED_AT DESC");
$stmt->execute([$_SESSION['user']['id']]);
$tasks = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>To-Do List</title>
    <link rel="stylesheet" href="./styles/todo.css">
</head>
<body>
    <div class="todo-container">
        <h2>📝 To-Do List</h2>
        <form id="task-form">
            <input type="text" id="task-text" placeholder="Enter new task" required>
            <button type="submit">Add Task</button>
        </form>

        <ul id="task-list">
            <?php foreach ($tasks as $task): ?>
                <li data-id="<?= $task['TASK_ID']; ?>">
                    <?= htmlspecialchars($task['TASK_TEXT']); ?>
                    <button class="delete-btn">🗑️</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <script src="./scripts/todo.js"></script>
</body>
</html>
