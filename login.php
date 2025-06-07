<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userType = $_POST['user_type'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Server-side validation
    $errors = [];
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($userType === 'student') {
        $roll_no = $_POST['roll_no'];
        if (strlen($roll_no) !== 10) {
            $errors[] = "Roll number must be exactly 10 characters";
        }
    }

    if (empty($errors)) {
        if ($userType === 'student') {
            $stmt = $conn->prepare("SELECT name, password FROM students WHERE email = ? AND roll_no = ?");
            $stmt->bind_param('ss', $email, $roll_no);
        } elseif ($userType === 'teacher') {
            $stmt = $conn->prepare("SELECT name, password FROM teachers WHERE email = ?");
            $stmt->bind_param('s', $email);
        } elseif ($userType === 'admin') {
            $stmt = $conn->prepare("SELECT name, password FROM admins WHERE email = ?");
            $stmt->bind_param('s', $email);
        }

        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($name, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_type'] = $userType;
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;

                if ($userType === 'student') {
                    header("Location: student_home.php");
                } elseif ($userType === 'teacher') {
                    header("Location: teacher_home.php");
                } elseif ($userType === 'admin') {
                    header("Location: admin_home.php");
                }
                exit();
            } else {
                $errors[] = "Invalid password";
            }
        } else {
            $errors[] = "No user found";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpdesk Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0056b3;
            --secondary-color: #003d80;
            --error-color: #dc3545;
            --success-color: #28a745;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background-color: blue;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo img {
            height: 80px;
            width: 250px;
        }

        .header-title {
            font-size: 2rem;
            color: white;
            font-weight: bold;
            text-align: left;
            flex-grow: 1;
            margin-left: 200px;
        }

        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        input, select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,86,179,0.1);
        }

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }

        .error-message {
            color: var(--error-color);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .submit-btn:hover {
            background-color: var(--secondary-color);
        }

        .invalid {
            border-color: var(--error-color);
        }

        @media (max-width: 768px) {
            .header-title {
                font-size: 1.5rem;
            }

            .login-container {
                padding: 1.5rem;
            }

        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            color: white;
            background-color: var(--error-color);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <img src="https://highereducationplus.com/wp-content/uploads/2023/09/narayana.jpg" alt="Helpdesk Logo">
        </div>
        <h1 class="header-title">College Campus Safety And Help Desk</h1>
        <div style="width: 50px;"></div> <!-- Spacer for balance -->
    </header>

    <div class="main-container">
        <div class="login-container">
            <?php if (!empty($errors)): ?>
                <div class="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="" novalidate>
                <div class="form-group">
                    <label for="user_type">User Type</label>
                    <select name="user_type" id="user_type" required>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" required>
                    <div class="error-message" id="email-error">Please enter a valid email address</div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" required>
                        <i class="toggle-password fas fa-eye" id="togglePassword"></i>
                    </div>
                    <div class="error-message" id="password-error">Password must be at least 8 characters</div>
                </div>

                <div class="form-group" id="roll-no-group" style="display: none;">
                    <label for="roll_no">Roll Number</label>
                    <input type="text" name="roll_no" id="roll_no">
                    <div class="error-message" id="roll-error">Roll number must be exactly 10 characters</div>
                </div>

                <button type="submit" class="submit-btn">Login</button>
            </form>
        </div>
    </div>

    <script>
    const form = document.getElementById('loginForm');
    const userType = document.getElementById('user_type');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const rollNo = document.getElementById('roll_no');
    const rollNoGroup = document.getElementById('roll-no-group');
    const togglePassword = document.getElementById('togglePassword');

    // Function to toggle roll number field
    function toggleRollNumberField() {
        if (userType.value === 'student') {
            rollNoGroup.style.display = 'block';
            rollNo.required = true;
        } else {
            rollNoGroup.style.display = 'none';
            rollNo.required = false;
        }
    }

    // Run on page load in case "Student" is already selected
    document.addEventListener('DOMContentLoaded', toggleRollNumberField);

    // Show/hide roll number field based on user type change
    userType.addEventListener('change', toggleRollNumberField);

    // Toggle password visibility
    togglePassword.addEventListener('click', () => {
        const type = password.type === 'password' ? 'text' : 'password';
        password.type = type;
        togglePassword.classList.toggle('fa-eye');
        togglePassword.classList.toggle('fa-eye-slash');
    });

    // Validation functions
    const validateEmail = (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    const validatePassword = (password) => password.length >= 8;
    const validateRollNo = (rollNo) => rollNo.length === 10;

    // Real-time validation
    email.addEventListener('input', () => {
        const isValid = validateEmail(email.value);
        email.classList.toggle('invalid', !isValid);
        document.getElementById('email-error').style.display = isValid ? 'none' : 'block';
    });

    password.addEventListener('input', () => {
        const isValid = validatePassword(password.value);
        password.classList.toggle('invalid', !isValid);
        document.getElementById('password-error').style.display = isValid ? 'none' : 'block';
    });

    rollNo.addEventListener('input', () => {
        const isValid = validateRollNo(rollNo.value);
        rollNo.classList.toggle('invalid', !isValid);
        document.getElementById('roll-error').style.display = isValid ? 'none' : 'block';
    });

    // Form submission
    form.addEventListener('submit', (e) => {
        let isValid = true;

        if (!validateEmail(email.value)) {
            email.classList.add('invalid');
            document.getElementById('email-error').style.display = 'block';
            isValid = false;
        }

        if (!validatePassword(password.value)) {
            password.classList.add('invalid');
            document.getElementById('password-error').style.display = 'block';
            isValid = false;
        }

        if (userType.value === 'student' && !validateRollNo(rollNo.value)) {
            rollNo.classList.add('invalid');
            document.getElementById('roll-error').style.display = 'block';
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
        }
    });
</script>
</body>
</html>