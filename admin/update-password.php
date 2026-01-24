<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Control Panel</title>
</head>
<body>

<?php

include "../code_generator.php";
include "../mysqlinfo.php";

class TableRows extends RecursiveIteratorIterator {
  function __construct($it) {
    parent::__construct($it, self::LEAVES_ONLY);
  }

  function current() {
    return "" . parent::current(). "";
  }

  function beginChildren() {
  }

  function endChildren() {
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the name and email fields are set
    if (isset($_POST['username']) && isset($_POST['password'])) {
        // Sanitize the data to prevent XSS attacks
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
        try
        {
            $conn = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                "$dbusername",
                "$dbpassword",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $stmt = $conn->prepare("SELECT passwordhash FROM admins WHERE username = \"" . $username . "\"");
            $stmt->execute();

            $count = 0;
            $passhash = "";

            $result = $stmt->setFetchMode(PDO::FETCH_ASSOC);
            foreach(new TableRows(new RecursiveArrayIterator($stmt->fetchAll())) as $k=>$v) {
                if ( $count == 0) 
                {
                    $passhash = $v;
                }
                $count = $count + 1;
            }
            
            if($count > 0 and password_verify($password, $passhash))
            {
                echo "<h1>Admin Panel</h1>\n";
                echo "<h2>Change Password</h2>\n";
                echo "<p>Password must be 8 characters or longer.</p>
<form method=\"POST\" action=\"update-password-finish.php\">
    <input type=\"hidden\" name=\"username\" value=\"" . $username . "\" required>
    <input type=\"hidden\" name=\"password\" value=\"" . $password . "\" required>
    <label>New Password</label><br>
    <input type=\"password\" name=\"newpassword1\" required><br><br>
    <label>Repeat New Password</label><br>
    <input type=\"password\" name=\"newpassword2\" required><br><br>
    <button type=\"submit\">Change Password</button>
</form>";
                echo "<h2>Go Back?</h2>
<form method=\"POST\" action=\"panel.php\">
    <input type=\"hidden\" name=\"username\" value=\"" . $username . "\" required>
    <input type=\"hidden\" name=\"password\" value=\"" . $password . "\" required>
    <button type=\"submit\">Admin Panel</button>
</form>";
                
            }
            else
            {
                echo "<p>Incorrect username or password.</p>\n";
                echo '<p><a href="login.html">Login</a></p>';
            }
            

            // Close connection
            $conn = null;
        }
        catch(PDOException $e) {
            echo "<p>MySql Connection failed: " . $e->getMessage() . "</p>";
        }
    } else {
        echo '<p>Name and email are required fields.</p>\n';
        echo '<p><a href="login.html">Login</a></p>\n';
    }
} else {
    echo '<p>Invalid request method.</p>\n';
    echo '<p><a href="login.html">Login</a></p>\n';
}

?>

</body>
</html>

