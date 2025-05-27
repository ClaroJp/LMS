<?php
require_once 'db.php'; // Ensure this returns a PDO connection

$query = "SELECT title, due_date, status FROM assignments";
$stmt = $conn->prepare($query);
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusToProgress = [
  "Not Started" => 0,
  "In Progress" => 50,
  "Completed" => 100
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Assignment Progress Tracker</title>
  <link rel="stylesheet" href="./styles/student-progress.css">
  <style>
    body {
      font-family: Arial, sans-serif;
    }
    .container {
      max-width: 900px;
      margin: 50px auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1em;
    }
    th, td {
      border: 1px solid #ccc;
      padding: 10px;
      text-align: left;
    }
    .progress-bar {
      background-color: #eee;
      height: 25px;
      width: 100%;
      position: relative;
    }
    .progress-fill {
      background-color: #4caf50;
      height: 100%;
      text-align: center;
      color: white;
      white-space: nowrap;
      overflow: hidden;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>📈 Assignment Progress Tracker</h1>
    <table>
      <thead>
        <tr>
          <th>Assignment</th>
          <th>Due Date</th>
          <th>Status</th>
          <th>Progress</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assignments as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['due_date']) ?></td>
            <td><?= htmlspecialchars($row['status']) ?></td>
            <td>
              <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $statusToProgress[$row['status']] ?? 0 ?>%">
                  <?= $statusToProgress[$row['status']] ?? 0 ?>%
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>
