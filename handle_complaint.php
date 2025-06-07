<?php
session_start();
include 'db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaint_id = $_POST['complaint_id'];
    $action = $_POST['action']; // Either 'accept' or 'reject'

    // Validate the action
    if (!in_array($action, ['accept', 'reject'])) {
        echo "Invalid action.";
        exit();
    }

    // Update the complaint status in the database
    $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $action, $complaint_id);

    if ($stmt->execute()) {
        echo "Complaint has been " . ucfirst($action) . "ed.";
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();

    // Redirect back to the teacher home page
    header("Location: teacher_home.php");
    exit();
}
?>
