<?php
session_start();

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: admin_login.html");
    exit();
}

// Database configuration
$host = "localhost";
$user = "root";
$password = "";
$database = "wp";

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$attendance_message = "";
$announcement_message = "";
$assignment_message = "";

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["form_type"])) {

    // Process Attendance form submission
    if ($_POST["form_type"] === "attendance") {
        $student_id = intval($_POST["attendance_student_id"]);
        $subject    = trim($_POST["attendance_subject"]);
        $attendance_value = intval($_POST["attendance_value"]);

        $stmt = $conn->prepare("INSERT INTO attendance (id, subject, attendance) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $student_id, $subject, $attendance_value);
        
        if ($stmt->execute()) {
            $attendance_message = "Attendance added successfully!";
        } else {
            $attendance_message = "Error adding attendance: " . $stmt->error;
        }
        $stmt->close();
    }

    // Process Announcement form submission
    if ($_POST["form_type"] === "announcement") {
        $announcement_title = trim($_POST["announcement_title"]);
        $announcement_description = trim($_POST["announcement_description"]);

        $stmt = $conn->prepare("INSERT INTO announcement (title, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $announcement_title, $announcement_description);
        
        if ($stmt->execute()) {
            $announcement_message = "Announcement posted successfully!";
        } else {
            $announcement_message = "Error posting announcement: " . $stmt->error;
        }
        $stmt->close();
    }

    // Process Assignment form submission
    if ($_POST["form_type"] === "assignment") {
        $title = trim($_POST["assignment_title"]);
        $due_date = $_POST["due_date"];

        // Check if a file is uploaded without error
        if (isset($_FILES["assignment_question_file"]) && $_FILES["assignment_question_file"]["error"] == 0) {
            // Define the target directory (absolute path)
            $target_dir = __DIR__ . "/files/questions/";
            // Create the directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $filename = basename($_FILES["assignment_question_file"]["name"]);
            $target_file = $target_dir . $filename;

            // Move the uploaded file to the target directory
            if (move_uploaded_file($_FILES["assignment_question_file"]["tmp_name"], $target_file)) {
                // Get the absolute path of the file
                $abs_path = realpath($target_file);

                // Insert the assignment record into the database
                $stmt = $conn->prepare("INSERT INTO assignments (title, due_date, assignment_question_file) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $title, $due_date, $abs_path);
                if ($stmt->execute()) {
                    $assignment_message = "Assignment assigned successfully!";
                } else {
                    $assignment_message = "Error assigning assignment: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $assignment_message = "Error uploading file.";
            }
        } else {
            $assignment_message = "No file uploaded or there was an error with the file upload.";
        }
    }
}

// Retrieve the current admin's details from the session
$currentId = $_SESSION['user_id'];
$sql = "SELECT id, name FROM admins WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $currentId);
$stmt->execute();
$stmt->bind_result($adminId, $adminName);
$stmt->fetch();
$stmt->close();

// --- New: Fetch submission data ---
// This query joins the submission table with users and assignments to fetch student name and assignment title.
$sql_sub = "SELECT s.student_id, u.name, s.assignment_id, a.title 
            FROM submission s 
            JOIN users u ON s.student_id = u.id 
            JOIN assignments a ON s.assignment_id = a.assignment_id";
$result_sub = $conn->query($sql_sub);
$submissionRows = "";
if ($result_sub->num_rows > 0) {
    while ($row = $result_sub->fetch_assoc()) {
         $submissionRows .= "<tr>
             <td>" . htmlspecialchars($row['student_id']) . "</td>
             <td>" . htmlspecialchars($row['name']) . "</td>
             <td>" . htmlspecialchars($row['assignment_id']) . "</td>
             <td>" . htmlspecialchars($row['title']) . "</td>
         </tr>";
    }
} else {
    $submissionRows = "<tr><td colspan='4'>No submissions yet</td></tr>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Home</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Header styles */
        .header {
            background: #fff;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .header .user-info {
            font-size: 18px;
            color: #333;
        }
        .header .logout-btn {
            background: #dc3545;
            color: #fff;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .header .logout-btn:hover {
            background: #c82333;
        }
        
        /* Dashboard styles */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            padding: 30px;
            background: #f4f7fa;
            color: #333;
        }
        
        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: #1a73e8;
            margin-bottom: 30px;
            font-size: 2.2em;
            text-align: center;
        }
        
        .section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        
        .section:hover {
            transform: translateY(-5px);
        }
        
        h2 {
            color: #1557b0;
            margin-top: 0;
            font-size: 1.5em;
            border-bottom: 2px solid #e8f0fe;
            padding-bottom: 10px;
        }
        
        .form-group {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        input, textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
            flex: 1;
            min-width: 200px;
        }
        
        input[type="number"] {
            max-width: 150px;
        }
        
        input[type="date"] {
            max-width: 200px;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        button {
            padding: 10px 20px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        button:hover {
            background: #1557b0;
        }
        
        .announcement-list {
            margin-top: 20px;
        }
        
        .announcement {
            padding: 15px;
            margin: 10px 0;
            background: #f8fafc;
            border-left: 4px solid #1a73e8;
            border-radius: 5px;
        }
        
        .announcement strong {
            color: #1a73e8;
            display: block;
            margin-bottom: 5px;
        }
        
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ccc;
            background: #e9ecef;
            border-radius: 5px;
            font-size: 16px;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 15px 0;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
        }
        th {
            background: #e8f0fe;
            color: #1557b0;
            font-weight: 600;
        }
        td {
            border-bottom: 1px solid #eee;
        }
        tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <div class="user-info">
            Welcome, <?php echo htmlspecialchars($adminName); ?> (ID: <?php echo htmlspecialchars($adminId); ?>)
        </div>
        <a class="logout-btn" href="logout.php">Logout</a>
    </div>
    
    <!-- Dashboard Content -->
    <div class="dashboard">
        <h1>Admin Dashboard</h1>
        
        <!-- Attendance Section -->
        <div class="section">
            <h2>Upload Attendance</h2>
            <?php if ($attendance_message): ?>
                <div class="message"><?php echo htmlspecialchars($attendance_message); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="form_type" value="attendance">
                <div class="form-group">
                    <input type="number" name="attendance_student_id" placeholder="Student ID" required>
                    <input type="text" name="attendance_subject" placeholder="Subject" required>
                    <input type="number" name="attendance_value" placeholder="Attendance (%)" required>
                    <button type="submit">Upload</button>
                </div>
            </form>
        </div>
        
        <!-- Assignment Section -->
        <div class="section">
            <h2>Assign Assignment</h2>
            <?php if ($assignment_message): ?>
                <div class="message"><?php echo htmlspecialchars($assignment_message); ?></div>
            <?php endif; ?>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="form_type" value="assignment">
                <div class="form-group">
                    <input type="text" name="assignment_title" id="assignmentTitle" placeholder="Title" required>
                    <input type="date" name="due_date" id="dueDate" required>
                </div>
                <div class="form-group">
                    <input type="file" name="assignment_question_file" id="assignmentFile" required>
                </div>
                <button type="submit">Assign</button>
            </form>
        </div>
        
        <!-- Announcement Section -->
        <div class="section">
            <h2>Post Announcement</h2>
            <?php if ($announcement_message): ?>
                <div class="message"><?php echo htmlspecialchars($announcement_message); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="form_type" value="announcement">
                <div class="form-group">
                    <input type="text" name="announcement_title" placeholder="Announcement Title" required>
                    <textarea name="announcement_description" placeholder="Announcement Description" rows="4" required></textarea>
                    <button type="submit">Post Announcement</button>
                </div>
            </form>
        </div>
        
        <!-- Submissions Section -->
        <div class="section">
            <h2>Submissions</h2>
            <table>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Assignment ID</th>
                    <th>Assignment Title</th>
                </tr>
                <?php echo $submissionRows; ?>
            </table>
        </div>
    </div>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
