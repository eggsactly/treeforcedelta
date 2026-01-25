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

date_default_timezone_set('America/Phoenix');

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

class TableRowsEvents extends RecursiveIteratorIterator {
  public $lastValue = "";
  
  function __construct($it) {
    parent::__construct($it, self::LEAVES_ONLY);
  }
  
  function current() {
    if (parent::current() != $lastValue){
      return "        <td style='width:150px;border:1px solid black;'>" . parent::current(). "</td>\n";
    }
    else
    {
      return "";
    }
    $lastValue = parent::current();
  }

  function beginChildren() {
    echo "    <tr>\n";
  }

  function endChildren() {
    echo "    </tr>" . "\n";
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
                
                echo "<h2>Change User Password</h2>
<form method=\"POST\" action=\"update-password.php\">
    <input type=\"hidden\" name=\"username\" value=\"" . $username . "\" required>
    <input type=\"hidden\" name=\"password\" value=\"" . $password . "\" required>
    <button type=\"submit\">Change My Password</button>
</form>";
                
                echo "<h2>Create New Event</h2>
                <p>Users can only submit photos with an event code within the start and end times of an event.</p>
<form method=\"POST\" action=\"create-event.php\">
    <input type=\"hidden\" name=\"username\" value=\"" . $username . "\" required>
    <input type=\"hidden\" name=\"password\" value=\"" . $password . "\" required>
    <button type=\"submit\">Create New Event</button>
</form>";

               echo "<p>Time in Tucson: " . date('Y-m-d H:i:s') . "</p>\n";
               echo "<h2>Upcoming Events</h2>\n";
               
               $stmt = $conn->prepare("SELECT id, name, start_time, end_time, code FROM events WHERE start_time > '" . date('Y-m-d H:i:s') . "'");
               $stmt->execute();
               $result = $stmt->fetchAll();
               $index = 0;
               echo "<table style='border: solid 1px black;'>";
               echo "<tr><th>ID</th><th>Name</th><th>Start Time</th><th>End Time</th><th>Access Code</th></tr>";
               while($index < count($result))
               {
                   print "    <tr>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result[$index]['id'] . "</td>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result[$index]['name'] . "</td>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result[$index]['start_time'] . "</td>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result[$index]['end_time'] . "</td>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result[$index]['code'] . "</td>\n";
                   print "    </tr>\n";
                   $index = $index + 1;
               }
               echo "</table>";
               
               if($index == 0)
               {
                   echo "<p>No upcomming events scheduled.</p>";
               }
               
               echo "<h2>Past and Current Events</h2>\n";
               
               $stmt2 = $conn->prepare("SELECT id, name, start_time, end_time, code FROM events WHERE start_time < '" . date('Y-m-d H:i:s') . "'");
               $stmt2->execute();
               
               
               $result2 = $stmt2->fetchAll();
               
               $index = 0;
               
               echo "<table style='border: solid 1px black;'>";
               echo "<tr><th>ID</th><th>Name</th><th>Start Time</th><th>End Time</th><th>Access Code</th><th>Image List</th></tr>";
               while($index < count($result2))
               {
                   print "    <tr>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result2[$index]['id'] . "</td>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result2[$index]['name'] . "</td>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result2[$index]['start_time'] . "</td>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result2[$index]['end_time'] . "</td>\n";
                   print "        <td style='width:150px;border:1px solid black;'>" . $result2[$index]['code'] . "</td>\n";
                   print "        <td style='width:150px;border:1px solid black;'><form method=\"POST\" action=\"view-event.php\"><input type=\"hidden\" name=\"username\" value=\"" . $username . "\" required /><input type=\"hidden\" name=\"password\" value=\"" . $password . "\" required /><input type=\"hidden\" name=\"eventid\" value=\"" . $result2[$index]['id'] . "\" /><button type=\"submit\">View Image List</button></form></td>\n";
                   print "    </tr>\n";
                   $index = $index + 1;
               }
               echo "</table>";
               
               if($index == 0)
               {
                   echo "<p>No past events.</p>";
               }
               
               echo "<h2>Log Out</h2>
<form method=\"POST\" action=\"../index.html\">
    <button type=\"submit\">Log Out</button>
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

