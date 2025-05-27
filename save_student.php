<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $gender = $_POST['gender'];
    $birthdate = $_POST['birthdate'];
    $address = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    $course = trim($_POST['course']);
    $year_level = (int)$_POST['year_level'];
    $section = trim($_POST['section']);

    // Insert student profile
    $stmt = $conn->prepare("INSERT INTO students (user_id, first_name, last_name, gender, birthdate, address, contact_number, course, year_level, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $first_name, $last_name, $gender, $birthdate, $address, $contact_number, $course, $year_level, $section]);

    header("Location: student_dashboard.php");
    exit;
} else {
    header("Location: student_details.php");
    exit;
}
