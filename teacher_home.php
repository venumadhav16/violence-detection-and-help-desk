<?php
session_start();
include 'db.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// Fetch teacher name from session
$teacher_name = $_SESSION['name'];

// Initialize arrays and variables
$complaints = [];
$teacher_details = [
    'id' => 'N/A',
    'name' => 'N/A',
    'email' => 'N/A',
    'designation' => 'N/A',
];

// Get the current view type from URL parameter, default to 'all'
$view_type = isset($_GET['view']) ? $_GET['view'] : 'all';

try {
    // Prepare the base query
    $base_query = "
        SELECT c.id, c.student_email, c.complaint, c.proof_files, c.status, c.created_at
        FROM complaints c 
        WHERE c.teacher_name = ?
    ";
    
    // Add filter based on view type
    if ($view_type === 'unread') {
        $base_query .= " AND c.status = 'Pending'";
    }
    
    // Add ordering
    $base_query .= " ORDER BY c.created_at DESC";
    
    // Execute the query
    $stmt = $conn->prepare($base_query);
    $stmt->bind_param("s", $teacher_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }
    $stmt->close();

    // Fetch teacher details
    $stmt = $conn->prepare("SELECT id, name, email,  designation FROM teachers WHERE name = ?");
    $stmt->bind_param("s", $teacher_name);
    $stmt->execute();
    $stmt->bind_result($id, $name, $email, $designation);

    if ($stmt->fetch()) {
        $teacher_details = [
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'designation' => $designation
        ];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
}

// Count unread (pending) complaints
$unread_count = array_reduce($complaints, function($carry, $item) {
    return $carry + ($item['status'] === 'Pending' ? 1 : 0);
}, 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Home</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f0f9ff;
            min-height: 100vh;
            color: #1e293b;
        }

        .header-container {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            padding: 1.5rem;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .college-logo {
            width: 175px;
            height: 75px;
            padding: 3px;
        }

        h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-left: 448px;
    margin-right: auto;
    width: 50%;
        }

        .logout-btn {
            background: rgba(228, 7, 7, 0.73);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: rgba(233, 15, 33, 0.93);
            transform: translateY(-1px);
        }

        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .teacher-details {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .teacher-details h2 {
            color: #1e40af;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .vertical-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        .vertical-table th {
            text-align: left;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            color: #475569;
            font-weight: 500;
            width: 30%;
        }

        .vertical-table td {
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            color: #1e293b;
        }

        .view-toggle {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .view-btn {
            background: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            border: 2px solid transparent;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 180px;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .view-btn.active {
            background: #2563eb;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        .view-btn:not(.active) {
            color: #2563eb;
            background: white;
            border: 2px solid #e2e8f0;
        }

        .view-btn:hover:not(.active) {
            border-color: #2563eb;
            transform: translateY(-1px);
        }

        .badge {
            background: #ef4444;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
        }

        .complaints-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .complaint-box {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid #e2e8f0;
        }

        .complaint-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.1);
        }

        .unread-marker {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 12px;
            height: 12px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #ef4444;
        }

        .complaint-header {
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .complaint-timestamp {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .complaint-body {
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .complaint-label {
            color: #475569;
            font-weight: 500;
        }

        .proof-section {
            margin: 1rem 0;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }

        .proof-files {
            list-style: none;
            margin-top: 0.5rem;
        }

        .proof-files li {
            margin: 0.5rem 0;
        }

        .proof-files a {
            color: #2563eb;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .proof-files a:hover {
            text-decoration: underline;
        }

        .complaint-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.875rem;
            margin: 1rem 0;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-accepted {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .action-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 0.875rem;
        }

        .action-btn.accept {
            background: #22c55e;
            color: white;
        }

        .action-btn.accept:hover {
            background: #16a34a;
        }

        .action-btn.reject {
            background: #ef4444;
            color: white;
        }

        .action-btn.reject:hover {
            background: #dc2626;
        }

        .action-btn.disabled {
            background: #e2e8f0;
            color: #64748b;
            cursor: not-allowed;
        }

        .no-complaints {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            color: #64748b;
            grid-column: 1 / -1;
            font-size: 1.1rem;
        }

        .footer {
            text-align: center;
            padding: 2rem;
            color: #64748b;
            margin-top: 3rem;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .header-content {
                flex-direction: column;
            }

            .complaints-container {
                grid-template-columns: 1fr;
            }

            .vertical-table th {
                width: 40%;
            }
        }

        /* Notification Counter */
        .notification-counter {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(239, 68, 68, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            z-index: 1000;
        }

        .notification-icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
    </style>
</head>
<body>
    <!-- Header and teacher details sections remain the same -->
    <div class="header-container">
        <img src="https://highereducationplus.com/wp-content/uploads/2023/09/narayana.jpg" alt="College Logo" class="college-logo">
        <h1>Welcome <?php echo htmlspecialchars($teacher_name); ?></h1>
        <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
    </div>

    <div class="teacher-details">
    <h2><center> Teacher Details</h2>
    <table class="vertical-table">
        <tr>
            <th>ID</th>
            <td><?php echo htmlspecialchars($teacher_details['id']); ?></td>
        </tr>
        <tr>
            <th>Name</th>
            <td><?php echo htmlspecialchars($teacher_details['name']); ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo htmlspecialchars($teacher_details['email']); ?></td>
        </tr>
        <tr>
            <th>Designation</th>
            <td><?php echo htmlspecialchars($teacher_details['designation']); ?></td>
        </tr>
    </table>
</div>
    <!-- Add view toggle buttons -->
    <div class="view-toggle">
        <a href="?view=all">
            <button class="view-btn <?php echo $view_type === 'all' ? 'active' : ''; ?>">
                All Complaints
            </button>
        </a>
        <a href="?view=unread">
            <button class="view-btn <?php echo $view_type === 'unread' ? 'active' : ''; ?>">
                Unread Complaints
                <?php if ($unread_count > 0): ?>
                    <span class="badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </button>
        </a>
    </div>

    <!-- Complaints container -->
    <div class="complaints-container">
        <?php if (empty($complaints)): ?>
            <p class="no-complaints">No complaints found.</p>
        <?php else: ?>
            <?php foreach ($complaints as $complaint): ?>
                <div class="complaint-box">
                    <?php if ($complaint['status'] === 'Pending'): ?>
                        <div class="unread-marker"></div>
                    <?php endif; ?>
                    <div class="complaint-header">
                        Complaint by: <?php echo htmlspecialchars($complaint['student_email']); ?>
                    </div>
                    <div class="complaint-timestamp">
                        Received: <?php echo date('F j, Y, g:i a', strtotime($complaint['created_at'])); ?>
                    </div>
                    <div class="complaint-body">
                        <p><span class="complaint-label">Complaint:</span> <?php echo htmlspecialchars($complaint['complaint']); ?></p>
                    </div>
                    <div class="proof-section">
                        <p class="complaint-label">Proof Files:</p>
                        <ul class="proof-files">
                            <?php 
                            $proofs = explode(',', $complaint['proof_files']);
                            foreach ($proofs as $proof): ?>
                                <li><a href="<?php echo htmlspecialchars($proof); ?>" target="_blank">View Proof</a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="complaint-status">
                        Status: <?php echo htmlspecialchars($complaint['status']); ?>
                    </div>
                    <div class="actions">
                        <?php if ($complaint['status'] === 'Pending'): ?>
                            <a href="update_complaint.php?action=accept&id=<?php echo $complaint['id']; ?>">
                                <button class="action-btn accept">Accept</button>
                            </a>
                            <a href="update_complaint.php?action=reject&id=<?php echo $complaint['id']; ?>">
                                <button class="action-btn reject">Reject</button>
                            </a>
                        <?php else: ?>
                            <button class="action-btn disabled">Action Taken</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="footer">
        &copy; <?php echo date("Y"); ?> NARAYANA ENGINEERING COLLEGE, NELLORE. All rights reserved.
    </div>
</body>
</html>