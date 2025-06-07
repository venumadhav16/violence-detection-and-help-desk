<?php
session_start();
include 'db.php';

// Redirect if not a student
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Fetch student data
$name = $_SESSION['name'];
$email = $_SESSION['email'];

// Get roll number
$stmt = $conn->prepare("SELECT roll_no FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($roll_no);
$stmt->fetch();
$stmt->close();

// Fetch teacher names
$teachers = [];
$result = $conn->query("SELECT name FROM teachers");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Complaint Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #1a73e8;
            --secondary-color: #f8f9fa;
            --border-color: #e0e0e0;
            --text-primary: #202124;
            --text-secondary: #5f6368;
            --success-color: #34a853;
            --error-color: #ea4335;
        }

        body {
            background-color: #f0f2f5;
        }

        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .college-logo {
            width: 175px;
            height: 70px;
        }

        .welcome-text {
            font-size: 1.25rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .header-buttons {
            display: flex;
            gap: 1rem;
        }

        .header-btn {
            padding: 0.5rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .profile-btn {
            background-color: var(--secondary-color);
            color: var(--text-primary);
        }

        .logout-btn {
            background-color: #dc3545;
            color: white;
        }

        .profile-btn:hover {
            background-color: #e8e9ea;
        }

        .logout-btn:hover {
            background-color: #c82333;
        }

        .main-container {
            margin-top: 90px;
            padding: 2rem;
            display: flex;
            justify-content: center;
        }

        .chat-container {
            width: 100%;
            max-width: 800px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .chat-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .chat-box {
            height: 500px;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: #f8f9fa;
        }

        .message {
            max-width: 70%;
            padding: 0.8rem 1rem;
            border-radius: 1rem;
            position: relative;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .bot-message {
            background: white;
            color: var(--text-primary);
            align-self: flex-start;
            border-bottom-left-radius: 0.25rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .user-message {
            background: var(--primary-color);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 0.25rem;
        }

        .chat-input {
            display: flex;
            gap: 0.5rem;
            padding: 1rem;
            background: white;
            border-top: 1px solid var(--border-color);
        }

        #user-input {
            flex: 1;
            padding: 0.8rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        #user-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        #send-btn {
            padding: 0 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        #send-btn:hover {
            background-color: #1557b0;
        }

        #send-btn:disabled {
            background-color: #a8a8a8;
            cursor: not-allowed;
        }

        #teacher-select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.95rem;
            margin: 0.5rem 0;
            background: white;
        }

        .file-input {
            margin: 0.5rem 0;
        }

        /* Custom scrollbar */
        .chat-box::-webkit-scrollbar {
            width: 6px;
        }

        .chat-box::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .chat-box::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }

        .chat-box::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Loading animation */
        .typing-indicator {
            display: flex;
            gap: 0.3rem;
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 1rem;
            align-self: flex-start;
            margin-top: 0.5rem;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            background: #90909090;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: 200ms; }
        .typing-dot:nth-child(2) { animation-delay: 300ms; }
        .typing-dot:nth-child(3) { animation-delay: 400ms; }

        @keyframes typing {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-section">
            <img src="https://highereducationplus.com/wp-content/uploads/2023/09/narayana.jpg" alt="College Logo" class="college-logo">
        </div>
        <div class="welcome-text">
            Welcome,  <?= htmlspecialchars($name) ?>!
        </div>
        <div class="header-buttons">
            <a href="profile.php" class="header-btn profile-btn">Profile</a>
            <a href="logout.php" class="header-btn logout-btn">Logout</a>
        </div>
    </header>

    <div class="main-container">
        <div class="chat-container">
            <div class="chat-header">
                Student Complaint Portal
            </div>
            <div class="chat-box" id="chat-box"></div>
            <div class="chat-input">
                <input type="text" id="user-input" placeholder="Type your message here..." disabled>
                <button id="send-btn" disabled>Send</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
    // Get DOM elements
    const chatBox = document.getElementById("chat-box");
    const userInput = document.getElementById("user-input");
    const sendBtn = document.getElementById("send-btn");

    // Initialize variables
    let step = 0;
    let complaintData = {};
    const userData = {
        name: "<?= htmlspecialchars($name) ?>",
        email: "<?= htmlspecialchars($email) ?>",
        roll_no: "<?= htmlspecialchars($roll_no) ?>",
        teachers: <?= json_encode($teachers) ?>
    };

    // Function to show typing indicator
    function showTypingIndicator() {
        const indicator = document.createElement("div");
        indicator.className = "typing-indicator";
        for(let i = 0; i < 3; i++) {
            const dot = document.createElement("div");
            dot.className = "typing-dot";
            indicator.appendChild(dot);
        }
        chatBox.appendChild(indicator);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Function to remove typing indicator
    function removeTypingIndicator() {
        const indicator = chatBox.querySelector(".typing-indicator");
        if (indicator) {
            indicator.remove();
        }
    }

    // Function to display bot messages
    function botMessage(msg) {
        showTypingIndicator();
        setTimeout(() => {
            removeTypingIndicator();
            const div = document.createElement("div");
            div.className = "message bot-message";
            div.innerText = msg;
            chatBox.appendChild(div);
            chatBox.scrollTop = chatBox.scrollHeight;
        }, 1000);
    }

    // Function to display user messages
    function userMessage(msg) {
        const div = document.createElement("div");
        div.className = "message user-message";
        div.innerText = msg;
        chatBox.appendChild(div);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Function to validate complaint length
    function validateComplaint(text) {
        const words = text.trim().split(/\s+/);
        return words.length >= 3;
    }

    // Function to show error message
    function showError(message) {
        const div = document.createElement("div");
        div.className = "message error-message";
        div.innerText = message;
        chatBox.appendChild(div);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Function to handle next step
    function nextStep() {
        switch (step) {
            case 0:
                botMessage(`Hello, ${userData.name}! I'm here to help you file a complaint.`);
                setTimeout(() => {
                    botMessage("Please select a teacher from the list below:");
                    showTeacherOptions();
                }, 1500);
                break;

            case 1:
                botMessage("Please describe your complaint in detail (minimum 3 words):");
                userInput.disabled = false;
                sendBtn.disabled = false;
                break;

            case 2:
                const complaint = userInput.value.trim();
                if (!validateComplaint(complaint)) {
                    showError("Please provide a more detailed complaint (minimum 3 words). Type OK to proceed");
                    step--;
                    return;
                }
                complaintData.complaint = complaint;
                userInput.value = "";
                userInput.disabled = true;
                sendBtn.disabled = true;
                botMessage("Please upload your proof file (required).");
                showFileUpload();
                break;

            case 3:
                submitComplaint();
                break;
        }
        step++;
    }

    // Function to show teacher options
    function showTeacherOptions() {
        const selectContainer = document.createElement("div");
        selectContainer.className = "select-container";
        
        const select = document.createElement("select");
        select.id = "teacher-select";
        
        // Add default option
        const defaultOption = document.createElement("option");
        defaultOption.value = "";
        defaultOption.textContent = "-- Select a teacher --";
        defaultOption.selected = true;
        defaultOption.disabled = true;
        select.appendChild(defaultOption);

        // Add teacher options
        userData.teachers.forEach((teacher) => {
            const option = document.createElement("option");
            option.value = teacher;
            option.textContent = teacher;
            select.appendChild(option);
        });

        // Handle teacher selection
        select.addEventListener("change", () => {
            complaintData.teacher = select.value;
            userMessage(`Selected teacher: ${select.value}`);
            selectContainer.remove();
            nextStep();
        });

        selectContainer.appendChild(select);
        chatBox.appendChild(selectContainer);
    }

    // Function to handle file upload
    function showFileUpload() {
        const fileContainer = document.createElement("div");
        fileContainer.className = "file-upload";

        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.accept = ".jpg,.jpeg,.png,.pdf,.doc,.docx";
        fileInput.required = true;

        // Handle file selection
        fileInput.addEventListener("change", () => {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    showError("File size must be less than 5MB");
                    return;
                }

                complaintData.file = file;
                userMessage(`File selected: ${file.name}`);
                fileContainer.remove();
                nextStep();
            }
        });

        fileContainer.appendChild(fileInput);
        chatBox.appendChild(fileContainer);
    }

    // Function to submit complaint
    function submitComplaint() {
        botMessage("Submitting your complaint...");

        const formData = new FormData();
        formData.append("teacher", complaintData.teacher);
        formData.append("complaint", complaintData.complaint);
        formData.append("email", userData.email);
        formData.append("proof", complaintData.file);

        fetch("process_complaint.php", {
            method: "POST",
            body: formData,
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                botMessage("Your complaint has been submitted successfully! 🎉");
                
                // Disable input after successful submission
                userInput.disabled = true;
                sendBtn.disabled = true;
            } else {
                showError(data.message || "There was an error submitting your complaint.");
                botMessage("Please try again or contact support if the issue persists.");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            showError("An error occurred while submitting your complaint.");
            botMessage("Please try again or contact support if the issue persists.");
        });
    }

    // Event listener for send button
    sendBtn.addEventListener("click", () => {
        if (!userInput.value.trim()) return;
        userMessage(userInput.value);
        nextStep();
    });

    // Event listener for enter key
    userInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter" && !e.shiftKey && !sendBtn.disabled) {
            e.preventDefault();
            sendBtn.click();
        }
    });

    // Event listener for input validation
    userInput.addEventListener("input", () => {
        sendBtn.disabled = !userInput.value.trim();
    });

    // Start the chat
    nextStep();
});
    </script>
</body>
</html>