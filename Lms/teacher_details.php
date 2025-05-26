<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    header("Location: index.php");
    exit;
}
require 'db.php';

$userId = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $gender = $_POST['gender'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';
    $address = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    $department = trim($_POST['department']);
    $expertise = trim($_POST['expertise']);

    if (!$first_name || !$last_name || !in_array($gender, ['Male', 'Female', 'Other']) || !$birthdate || !$department) {
        echo "<script>alert('Please fill in all required fields correctly');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO teachers (user_id, first_name, last_name, gender, birthdate, address, contact_number, department, expertise) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $first_name, $last_name, $gender, $birthdate, $address, $contact_number, $department, $expertise]);
        header("Location: teacher_dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Teacher Details</title>
    <link rel="stylesheet" href="./styles/studentprofile.css" />
</head>
<body>
    <div class="container">
        <h2>Complete Your Teacher Profile</h2>
        <form method="POST">
            <div>
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required />
            </div>
            <div>
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required />
            </div>
            <div>
                <label for="gender">Gender:</label>
                <select id="gender" name="gender" required>
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div>
                <label for="birthdate">Birthdate:</label>
                <input type="date" id="birthdate" name="birthdate" required />
            </div>
            <div>
                <label for="address">Address:</label>
                <input type="text" id="address" name="address" />
            </div>
            <div>
                <label for="contact_number">Contact Number:</label>
                <input type="text" id="contact_number" name="contact_number" />
            </div>
            <div>
                <label for="department">Department:</label>
                <input type="text" id="department" name="department" required />
            </div>
            <div>
                <label for="expertise">Expertise:</label>
                <input type="text" id="expertise" name="expertise" />
            </div>
            <button type="submit">Save Details</button>
        </form>
    </div>
</body>
</html>
