<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_email = $_POST['email'];
    $teacher_name = $_POST['teacher'];
    $complaint = $_POST['complaint'];
    $status = "Pending";
    $proof_path = NULL;

    if (!empty($_FILES['proof']['name'])) {
        $upload_dir = "uploads/";
        $file_name = time() . "_" . basename($_FILES['proof']['name']);
        $proof_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['proof']['tmp_name'], $proof_path)) {
            echo json_encode(["success" => false]);
            exit();
        }
    }

    $stmt = $conn->prepare("INSERT INTO complaints (student_email, teacher_name, complaint, proof_files, created_at, status) VALUES (?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param("sssss", $student_email, $teacher_name, $complaint, $proof_path, $status);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false]);
    }

    $stmt->close();
}
?>