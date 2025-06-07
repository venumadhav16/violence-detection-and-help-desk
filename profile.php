<?php
session_start();
include 'db.php';

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Fetch complaints for the logged-in student
$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT teacher_name, complaint, proof_files, created_at, status FROM complaints WHERE student_email = ? ORDER BY created_at DESC");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_complaint'])) {
    $student_email = $_SESSION['email'];
    $teacher_name = $_POST['teacher_name'];
    $complaint = $_POST['complaint'];
    $proof_files = $_POST['proof_files'];
    $current_date = date('Y-m-d H:i:s');
    $status = 'Pending';

    try {
        // Insert the resent complaint
        $stmt = $conn->prepare("INSERT INTO complaints (student_email, teacher_name, complaint, proof_files, created_at, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $student_email, $teacher_name, $complaint, $proof_files, $current_date, $status);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Complaint has been resent successfully.";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("Failed to resend complaint");
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Error resending complaint: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    // Redirect to prevent form resubmission
    header("Location: profile.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - Complaints</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .top-bar a {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
        }

        .complaint {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fafafa;
        }

        .complaint-header {
            font-size: 16px;
            color: #555;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .complaint-details {
            font-size: 14px;
            color: #444;
        }

        .complaint-details span {
            display: block;
            margin: 5px 0;
        }

        .proof-files a {
            text-decoration: none;
            color: #007bff;
            margin-right: 5px;
        }

        .status {
            font-weight: bold;
            color: #007bff;
        }

        @media (max-width: 600px) {
            .complaint {
                padding: 10px;
            }

            .complaint-header {
                font-size: 14px;
            }

            .complaint-details {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Your Complaints</h1>
        <div class="top-bar">
            <a href="student_home.php">Back to Home</a>
            <a href="logout.php">Logout</a>
        </div>

        <?php while ($row = $result->fetch_assoc()): ?>
<div class="complaint">
    <div class="complaint-header">
        Roll Number: <strong><?php echo htmlspecialchars($email); ?></strong>
    </div>
    <div class="complaint-details">
        <span><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['name']); ?></span>
        <span><strong>Teacher:</strong> <?php echo htmlspecialchars($row['teacher_name']); ?></span>
        <span><strong>Complaint:</strong> <?php echo htmlspecialchars($row['complaint']); ?></span>
        <span class="proof-files">
            <strong>Proof Files:</strong>
            <?php
            $files = explode(',', $row['proof_files']);
            foreach ($files as $file) {
                echo "<a href='$file' target='_blank'>View File</a> ";
            }
            ?>
        </span>
        <span><strong>Status:</strong> <span class="status"><?php echo htmlspecialchars($row['status']); ?></span></span>
        <span><strong>Date:</strong> <?php echo htmlspecialchars($row['created_at']); ?></span>
        
        <!-- Resend Form -->
        <div class="resend-container">
            <form method="POST" class="resend-form">
                <input type="hidden" name="teacher_name" value="<?php echo htmlspecialchars($row['teacher_name']); ?>">
                <input type="hidden" name="complaint" value="<?php echo htmlspecialchars($row['complaint']); ?>">
                <input type="hidden" name="proof_files" value="<?php echo htmlspecialchars($row['proof_files']); ?>">
                <input type="hidden" name="resend_complaint" value="1">
                <button type="submit" class="resend-btn" onclick="return confirm('Are you sure you want to resend this complaint?')">
                    Resend Complaint
                </button>
            </form>
        </div>
    </div>
</div>
<?php endwhile; ?>

    </div>
</body>
</html>
