<?php
session_start();

// Check if the user is an admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Include the database connection file
require_once 'db.php';

// Fetch admin details
$admin_name = $_SESSION['name'];
$admin_email = $_SESSION['email'];

// Fetch admin details from the database
$sql_admin_details = "SELECT id, name, email, contact_number, department_name FROM admins WHERE email = ?";
$stmt = $conn->prepare($sql_admin_details);
$stmt->bind_param('s', $admin_email);
$stmt->execute();
$stmt->bind_result($id, $name, $email, $admin_contact, $admin_department_name);
$stmt->fetch();
$stmt->close();

// Complaints visibility flag
$show_complaints = isset($_GET['view_complaints']);

// Fetch complaints only when "Complaints" button is clicked
$complaints = [];
if ($show_complaints) {
    $sql = "SELECT student_email,teacher_name,complaint,proof_files,created_at,status FROM complaints ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $complaints[] = $row;
        }
    }
}

function isFlaskRunning() {
    $fp = @fsockopen("127.0.0.1", 5000, $errno, $errstr, 2);
    if ($fp) {
        fclose($fp);
        return true;
    } else {
        return false;
    }
}

// Function to start the Flask app
function startFlaskApp() {
    $flask_script = 'C:/xampp/htdocs/demo/dummy.py'; // Update this to the actual path of your Flask app
    $command = "python3 $flask_script > /path/to/flask_log.txt 2>&1 &"; // Redirect output to a log file
    exec($command, $output, $status);
    return $status === 0;
}

// Attempt to start Flask app if the Violence Detection feature is accessed
if (isset($_GET['flask_app']) && !isFlaskRunning()) {
    startFlaskApp();
}

// Function to fetch Flask logs
function getFlaskLogs() {
    $log_file = 'C:/xampp/htdocs/demo/flask_log.txt'; // Update this to your actual log file path
    if (file_exists($log_file)) {
        return file_get_contents($log_file);
    } else {
        return "Log file not found.";
    }
}

// Logout functionality
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
       body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(120deg, #e0f7fa, #b2ebf2);
            color: #01579b;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 30px;
            background: #0288d1;
            color: #ffffff;
            position: relative;
        }

        
        .college-logo {
            width: 175px;
            height: 75px;
            padding: 3px;
        }

        h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-left: 400px;
    margin-right: auto;
    width: 50%;
        }
        .addnew-button {
    background-color: #4CAF50;
    color: white;
    text-decoration: none;
    padding: 10px 20px;
    border-radius: 5px;
    font-size: 16px;
    margin: 0;
    position: absolute;
    top: 30px;
    right: 120px; /* Position it to the left of logout button */
}

.addnew-button:hover {
    background-color: #45a049;
}

        .logout-button {
            background-color: #f44336;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-size: 16px;
            margin: 0;
            position: absolute;
            top: 30px;
            right: 10px;
        }

        .logout-button:hover {
            background-color: #d32f2f;
        }

        .container {
            max-width: 90%;
            margin: 20px auto;
            padding: 20px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            color: #01579b;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .container h1 {
            font-size: 2rem;
            text-align: center;
            color: #0288d1;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .table-container th, .table-container td {
            border: 1px solid #b2ebf2;
            padding: 10px;
            text-align: left;
            background: #e0f7fa;
            color: #01579b;
        }

        .table-container th {
            background-color: #4fc3f7;
        }

        .button-group {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }

        .button {
            background-color: #0288d1;
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
        }

        .button:hover {
            background-color: #0277bd;
        }

        .iframe-container iframe {
            width: 100%;
            height: 400px;
            border: none;
            border-radius: 5px;
        }

.complaints-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }

        .complaint-box {
            background: #b3e5fc;
            border-radius: 10px;
            padding: 15px 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            color: #01579b;
            flex: 1 1 calc(33% - 40px);
            box-sizing: border-box;
            min-width: 300px;
        }

        .complaint-box strong {
            color: #0277bd;
        }

        .complaint-box a {
            color: #0288d1;
            text-decoration: none;
        }

        .complaint-box a:hover {
            text-decoration: underline;
        }

        .complaints-container > .complaint-box {
            transition: transform 0.3s ease;
        }

        .complaints-container > .complaint-box:hover {
            transform: translateY(-5px);
        }

footer {
    text-align: center;
    padding: 10px;
    background: #0288d1;
    color: #ffffff;
    position: relative;
    bottom: 0;
    width: 100%;
    margin-top: auto; /* Pushes footer to the bottom if content is short */
}


    </style>
</head>
<body>
<header>
<img src="https://highereducationplus.com/wp-content/uploads/2023/09/narayana.jpg" alt="College Logo" class="college-logo">
    <h2>Welcome, <?php echo htmlspecialchars($admin_name); ?></h2>
    <a href="signup.php" class="addnew-button">Add New</a>
    <a href="logout.php" class="logout-button">Logout</a>

</header>
<div class="container">
    <h1>Admin Details</h1>
    <div class="table-container">
        <table>
            <tr>
                <th>Name</th>
                <td><?php echo htmlspecialchars($admin_name); ?></td>
            </tr>
            <tr>
                <th>Email</th>
                <td><?php echo htmlspecialchars($admin_email); ?></td>
            </tr>
            <tr>
                <th>Contact</th>
                <td><?php echo htmlspecialchars($admin_contact); ?></td>
            </tr>
            <tr>
                <th>Department</th>
                <td><?php echo htmlspecialchars($admin_department_name); ?></td>
            </tr>
        </table>
    </div>
    <div class="button-group">
        <a href="?flask_app=1" class="button">Start Detection</a>
        <a href="?view_complaints=1" class="button">View Complaints</a>
    </div>
</div>
<?php if (isset($_GET['flask_app']) || $show_complaints || isset($_GET['view_logs'])): ?>
<div class="container">
    <?php if (isset($_GET['flask_app'])): ?>
        <h1>Violence Detection</h1>
        <?php if (isFlaskRunning()): ?>
            <div class="iframe-container">
                <iframe src="http://127.0.0.1:5000"></iframe>
            </div>
        <?php else: ?>
            <p>The Flask app is not running. Please restart the server.</p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($show_complaints): ?>
<div class="container">
    <h1>Student Complaints</h1>
    <?php if (count($complaints) > 0): ?>
        <div class="complaints-container">
            <?php foreach ($complaints as $index => $complaint): ?>
                <div class="complaint-box">
                    <p><strong>Complaint #<?php echo $index + 1; ?></strong></p>
                    <p><strong>Student Email:</strong> <?php echo isset($complaint['student_email']) ? htmlspecialchars($complaint['student_email']) : 'N/A'; ?></p>
                    <p><strong>Teacher Name:</strong> <?php echo isset($complaint['teacher_name']) ? htmlspecialchars($complaint['teacher_name']) : 'N/A'; ?></p>
                    <p><strong>Complaint:</strong> <?php echo isset($complaint['complaint']) ? htmlspecialchars($complaint['complaint']) : 'N/A'; ?></p>
                    <p><strong>Proof File:</strong> 
                        <?php if (isset($complaint['proof_files']) && !empty($complaint['proof_files'])): ?>
                            <a href="<?php echo htmlspecialchars($complaint['proof_files']); ?>" target="_blank">View File</a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </p>
                    <p><strong>Date Submitted:</strong> <?php echo isset($complaint['created_at']) ? htmlspecialchars($complaint['created_at']) : 'N/A'; ?></p>
                    <p><strong>Status:</strong> <?php echo isset($complaint['status']) ? htmlspecialchars($complaint['status']) : 'N/A'; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>No complaints found.</p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['view_logs'])): ?>
        <h1>Flask Logs</h1>
        <pre><?php echo htmlspecialchars(getFlaskLogs()); ?></pre>
    <?php endif; ?>
</div>
<?php endif; ?>
<footer>
    <p>&copy; 2024 Admin Dashboard. All Rights Reserved.</p>
</footer>
</body>
</html>
