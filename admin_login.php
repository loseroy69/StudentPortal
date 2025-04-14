<?php
session_start();
$host = "localhost";
$user = "root";
$password = "";
$database = "wp";

// Connect to MySQL database
$conn = new mysqli($host, $user, $password, $database);

// Check for connection errors
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Query to check user credentials
    $sql = "SELECT id, name, password FROM admins WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $name, $stored_password);
        $stmt->fetch();

        // Direct comparison since passwords are stored in plain text
        if ($password === $stored_password) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $name;
            header("Location: admin_home.php");
            exit();
        } else {
            echo "Invalid email or password!";
        }
    } else {
        echo "Invalid email or password!";
    }
    
    $stmt->close();
    $conn->close();
}
?>