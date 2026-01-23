<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
</head>
<body>

<?php

include "../code_generator.php";
include "../mysqlinfo.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the name and email fields are set
    if (isset($_POST['username']) && isset($_POST['password'])) {
        // Sanitize the data to prevent XSS attacks
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

        // Now, you can store or use the data as required
        echo '<p>Name: ' . htmlspecialchars($username) . '<br>';
        echo 'Email: ' . htmlspecialchars($password) . '<br></p>';
    } else {
        echo '<p>Name and email are required fields.</p>';
        echo '<p><a href="login.html">Login</a></p>';
    }
} else {
    echo '<p>Invalid request method.</p>';
    echo '<p><a href="login.html">Login</a></p>';
}

/*
$pdo = new PDO(
    "mysql:host=localhost;dbname=your_database;charset=utf8mb4",
    "your_user",
    "your_password",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
*/

?>

</body>
</html>

