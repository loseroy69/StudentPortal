<?php
session_start();

// Retrieve user info from session (or use defaults for demonstration)
$username = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'JohnDoe';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '12345';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Database connection configuration
$servername = "localhost";
$dbusername = "root";
$dbpassword = "";
$dbname = "wp";

// Create connection using MySQLi
$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// PROCESS FORM SUBMISSIONS
// -------------------------

// Process assignment submission (uploading answer file)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["form_type"]) && $_POST["form_type"] === "submission") {
    // Retrieve the assignment id from the form
    $assignment_id = intval($_POST["assignment_id"]);

    // Process file upload for answer file
    if (isset($_FILES["assignment_answer_file"]) && $_FILES["assignment_answer_file"]["error"] == 0) {
        // Define the target directory for answer uploads
        $target_dir = __DIR__ . "/files/answers/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $filename = basename($_FILES["assignment_answer_file"]["name"]);
        $target_file = $target_dir . $filename;
        
        // Move the uploaded file to the target directory
        if (move_uploaded_file($_FILES["assignment_answer_file"]["tmp_name"], $target_file)) {
            // Get the absolute path of the file
            $abs_path = realpath($target_file);

            // Check if a submission already exists for this student and assignment
            $stmt = $conn->prepare("SELECT assignment_id FROM submission WHERE assignment_id = ? AND student_id = ?");
            $stmt->bind_param("is", $assignment_id, $user_id);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                // Update the existing submission record
                $stmt->close();
                $stmt_update = $conn->prepare("UPDATE submission SET assignment_answer_file = ? WHERE assignment_id = ? AND student_id = ?");
                $stmt_update->bind_param("sis", $abs_path, $assignment_id, $user_id);
                $stmt_update->execute();
                $stmt_update->close();
                $submission_message = "Submission updated successfully!";
            } else {
                $stmt->close();
                // Insert a new submission record
                $stmt_insert = $conn->prepare("INSERT INTO submission (assignment_id, student_id, assignment_answer_file) VALUES (?, ?, ?)");
                $stmt_insert->bind_param("iss", $assignment_id, $user_id, $abs_path);
                $stmt_insert->execute();
                $stmt_insert->close();
                $submission_message = "Submission uploaded successfully!";
            }
        } else {
            $submission_message = "Error uploading answer file.";
        }
    } else {
        $submission_message = "No answer file uploaded or an error occurred.";
    }
}

// FETCH DATA
// ----------

// 1. Attendance data for current student
$sql = "SELECT subject, attendance FROM attendance WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$attendanceRows = '';
while($row = $result->fetch_assoc()){
    $attendanceRows .= "<tr>
                          <td>" . htmlspecialchars($row['subject']) . "</td>
                          <td>" . htmlspecialchars($row['attendance']) . "%</td>
                        </tr>";
}
$stmt->close();

// 2. Announcements from the announcement table
$sql_ann = "SELECT title, description FROM announcement";
$result_ann = $conn->query($sql_ann);
$announcementHTML = '';
if ($result_ann->num_rows > 0) {
    while ($row_ann = $result_ann->fetch_assoc()) {
        $announcementHTML .= '<div class="announcement">';
        $announcementHTML .= '<strong>' . htmlspecialchars($row_ann['title']) . '</strong>';
        $announcementHTML .= '<p>' . htmlspecialchars($row_ann['description']) . '</p>';
        $announcementHTML .= '</div>';
    }
} else {
    $announcementHTML = '<p>No announcements available</p>';
}

// 3. Fetch assignments from the assignments table
$sql_assign = "SELECT assignment_id, title, due_date, assignment_question_file FROM assignments";
$result_assign = $conn->query($sql_assign);
$assignments = []; // To store assignments for later use.
if ($result_assign->num_rows > 0) {
    while ($row = $result_assign->fetch_assoc()) {
        $assignments[$row['assignment_id']] = $row;
    }
}

// 4. Determine submission statuses for current student (submitted if a record exists)
$submissionStatuses = [];
$stmt_sub = $conn->prepare("SELECT assignment_id FROM submission WHERE student_id = ?");
$stmt_sub->bind_param("s", $user_id);
$stmt_sub->execute();
$result_sub = $stmt_sub->get_result();
while ($row = $result_sub->fetch_assoc()) {
    $submissionStatuses[$row['assignment_id']] = "submitted";
}
$stmt_sub->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard</title>
  <style>
      /* Existing styles */
      body {
          font-family: 'Segoe UI', Arial, sans-serif;
          margin: 0;
          padding: 30px;
          background: #f4f7fa;
          color: #333;
      }
      .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          background: #e8f0fe;
          padding: 15px;
          border-radius: 5px;
          margin-bottom: 30px;
      }
      .header p {
          margin: 0;
          font-size: 1.1em;
          color: #1557b0;
      }
      .header a.logout {
          background: #dc3545;
          color: #fff;
          padding: 8px 15px;
          text-decoration: none;
          border-radius: 5px;
          transition: background 0.3s;
      }
      .header a.logout:hover {
          background: #c82333;
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
      .upload-section {
          display: flex;
          gap: 10px;
          align-items: center;
          margin-top: 15px;
      }
      input[type="file"] {
          padding: 8px;
          border: 1px solid #ddd;
          border-radius: 5px;
          background: #fff;
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
      .message {
          margin-bottom: 15px;
          padding: 10px;
          border: 1px solid #ccc;
          background: #e9ecef;
          border-radius: 5px;
          font-size: 16px;
      }
  </style>
</head>
<body>
    <!-- Top Header Section with User Info and Logout -->
    <div class="header">
        <p>Welcome, <?php echo htmlspecialchars($username); ?> (ID: <?php echo htmlspecialchars($user_id); ?>)</p>
        <a class="logout" href="home.php?logout=true">Logout</a>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard">
        <h1>Student Dashboard</h1>
        
        <!-- Announcements Section -->
        <div class="section">
            <h2>Announcements</h2>
            <div class="announcement-list">
                <?php echo $announcementHTML; ?>
            </div>
        </div>
        
        <!-- Attendance Section -->
        <div class="section">
            <h2>Attendance</h2>
            <table>
                <tr>
                    <th>Subject</th>
                    <th>Attendance %</th>
                </tr>
                <?php echo $attendanceRows; ?>
            </table>
        </div>
        
        <!-- Assignments Section -->
        <div class="section">
            <h2>Assignments</h2>
            <?php
              // Display any submission message if set.
              if (isset($submission_message)) {
                  echo '<div class="message">' . htmlspecialchars($submission_message) . '</div>';
              }
            ?>
            <table>
                <tr>
                    <th>Title</th>
                    <th>Due Date</th>
                    <th>Question File</th>
                    <th>Your Answer</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($assignments as $assignment): 
                    $a_id = $assignment['assignment_id'];
                    // Determine status based on submission record existence
                    $status = isset($submissionStatuses[$a_id]) ? "submitted" : "Pending";
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                    <td><?php echo htmlspecialchars($assignment['due_date']); ?></td>
                    <td>
                        <!-- Download link for the question file -->
                        <a href="<?php echo htmlspecialchars($assignment['assignment_question_file']); ?>" download>
                            Download
                        </a>
                    </td>
                    <td>
                        <?php if ($status !== "submitted") { ?>
                        <!-- Answer upload form -->
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="form_type" value="submission">
                            <input type="hidden" name="assignment_id" value="<?php echo $a_id; ?>">
                            <input type="file" name="assignment_answer_file" required>
                            <button type="submit">Submit Answer</button>
                        </form>
                        <?php } else {
                            echo "File Uploaded";
                        } ?>
                    </td>
                    <td><?php echo htmlspecialchars($status); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>
