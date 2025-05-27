<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task'])) {
    $task = trim($_POST['task']);
    if (!empty($task)) {
        $stmt = $conn->prepare("INSERT INTO STUDENT_TASKS (STUDENT_ID, TASK_TEXT) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user']['id'], $task]);
        echo "success";
    } else {
        echo "error";
    }
}
?>
