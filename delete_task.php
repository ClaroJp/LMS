<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    $taskId = $_POST['task_id'];

    $stmt = $conn->prepare("DELETE FROM STUDENT_TASKS WHERE TASK_ID = ? AND STUDENT_ID = ?");
    $stmt->execute([$taskId, $_SESSION['user']['id']]);

    echo "deleted";
}
?>
