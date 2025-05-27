<?php
session_start();
require 'db.php'; // Contains $pdo

$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$user_id || !$role) {
    die("User not logged in.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM NOTIFICATIONS WHERE USER_ID = ? ORDER BY TIMESTAMP DESC");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching notifications: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Notifications</title>
  <link rel="stylesheet" href="assets/css/notifications.css">
</head>
<body>

<div class="notification-center">
  <h2>Your Notifications</h2>

  <?php if (count($notifications) === 0): ?>
    <p>No notifications yet.</p>
  <?php else: ?>
    <?php foreach ($notifications as $notif): ?>
      <div class="notification">
        <div class="type"><?= htmlspecialchars($notif['TYPE']) ?></div>
        <div class="content"><?= htmlspecialchars($notif['CONTENT']) ?></div>
        <div class="timestamp"><?= htmlspecialchars($notif['TIMESTAMP']) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

</body>
</html>
