<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userType = $_POST['user_type'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    if ($userType === 'student') {
        $roll_no = $_POST['roll_no'];
        $stmt = $conn->prepare("INSERT INTO students (name, email, password, roll_no) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $name, $email, $password, $roll_no);
    } elseif ($userType === 'teacher') {
        $designation = $_POST['designation'];
        $contact_number = $_POST['contact_number'];
        $stmt = $conn->prepare("INSERT INTO teachers (name, email, password, designation, contact_number) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $name, $email, $password, $designation, $contact_number);
    } elseif ($userType === 'admin') {
        $contact_number = $_POST['contact_number'];
        $department_name = $_POST['department_name'];
        $stmt = $conn->prepare("INSERT INTO admins (name, email, password, contact_number, department_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $name, $email, $password, $contact_number, $department_name);
    }

    if ($stmt->execute()) {
        $_SESSION['user_type'] = $userType;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;

        // Redirect to respective home page
        if ($userType === 'student') {
            header("Location: student_home.php");
        } elseif ($userType === 'teacher') {
            header("Location: teacher_home.php");
        } elseif ($userType === 'admin') {
            header("Location: admin_home.php");
        }
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 400px;
            max-width: 100%;
            text-align: center;
        }

        h1 {
            font-size: 24px;
            color: #4e54c8;
            margin-bottom: 20px;
        }

        form label {
            display: block;
            font-size: 14px;
            margin: 10px 0 5px;
            text-align: left;
        }

        form input, form select, button {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        button {
            background-color: #4e54c8;
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background-color: #373c9a;
        }

        .additional-fields {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Signup Page</h1>
        <form method="POST" action="">
            <label for="user_type">User Type:</label>
            <select name="user_type" id="user_type" required>
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
                <option value="admin">Admin</option>
            </select>

            <label for="name">Name:</label>
            <input type="text" name="name" id="name" required>

            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>

            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>

            <div id="student-fields" class="additional-fields">
                <label for="roll_no">Roll Number:</label>
                <input type="text" name="roll_no" id="roll_no">
            </div>

            <div id="teacher-fields" class="additional-fields">
                <label for="designation">Designation:</label>
                <input type="text" name="designation" id="designation" placeholder="e.g., MTech, PhD">

                <label for="contact_number">Contact Number:</label>
                <input type="text" name="contact_number" id="contact_number" placeholder="e.g., 1234567890">
            </div>

            <div id="admin-fields" class="additional-fields">
                <label for="contact_number">Contact Number:</label>
                <input type="text" name="contact_number" id="admin_contact_number" placeholder="e.g., 1234567890">

                <label for="department_name">Department Name:</label>
                <input type="text" name="department_name" id="department_name" placeholder="e.g., IT, HR">
            </div>

            <button type="submit">Signup</button>
        </form>
    </div>

    <script>
        // Show/Hide additional fields based on user type
        const userTypeSelect = document.getElementById('user_type');
        const studentFields = document.getElementById('student-fields');
        const teacherFields = document.getElementById('teacher-fields');
        const adminFields = document.getElementById('admin-fields');

        userTypeSelect.addEventListener('change', () => {
            studentFields.style.display = userTypeSelect.value === 'student' ? 'block' : 'none';
            teacherFields.style.display = userTypeSelect.value === 'teacher' ? 'block' : 'none';
            adminFields.style.display = userTypeSelect.value === 'admin' ? 'block' : 'none';
        });

        // Trigger change event on page load
        userTypeSelect.dispatchEvent(new Event('change'));
    </script>
</body>
</html>
