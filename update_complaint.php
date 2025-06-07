<?php
session_start();
include 'db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $complaint_id = $_GET['id'];

    // Only Accept or Reject are allowed actions
    if ($action === 'accept' || $action === 'reject') {
        // Update complaint status in the database
        $status = ($action === 'accept') ? 'Accepted' : 'Rejected';

        // Update the status of the complaint in the database
        $stmt = $conn->prepare("UPDATE complaints SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $complaint_id);
        if ($stmt->execute()) {
            // Notify the student (Optional: You can add an email or message notification system here)
            echo "Complaint status updated to " . $status . ".";
            
            // Redirect to the teacher's home page
            header("Location: teacher_home.php");
            exit();
        } else {
            echo "Error updating complaint status.";
        }
    } else {
        echo "Invalid action.";
    }
} else {
    echo "Invalid request.";
}
?>
