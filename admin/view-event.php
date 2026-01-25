<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Control Panel</title>
</head>
<body>

<?php

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
        $eventid = filter_input(INPUT_POST, 'eventid', FILTER_SANITIZE_STRING);
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
                echo "<h1>Event Viewer</h1>\n";
               
               $stmt = $conn->prepare("SELECT name, start_time, end_time, code FROM events WHERE id = '" . $eventid . "'");
               $stmt->execute();
               $result = $stmt->fetchAll();
               $index = 0;
               
               if (count($result) > 0)
               {
                   print "<p>Event Name: " . $result[0]['name'] . "</p>";
                   print "<p>Event ID: " . $eventid . "</p>";
                   
                   $stmt = $conn->prepare("SELECT filename, upload_date, latitude, longitude FROM uploaded_images WHERE event_id = '" . $eventid . "'");
                   $stmt->execute();
                   $result = $stmt->fetchAll();
                   $index = 0;
    
                   echo "<table style='border: solid 1px black;'>";
                   echo "<tr><th>Filename</th><th>Upload Date</th><th>Latitude</th><th>Longitude</th></tr>";
                   while($index < count($result))
                   {
                       print "    <tr>\n";
                       print "        <td style='width:150px;border:1px solid black;'>" . $result[$index]['filename'] . "</td>\n";
                       print "        <td style='width:150px;border:1px solid black;'>" . $result[$index]['upload_date'] . "</td>\n";
                       print "        <td style='width:150px;border:1px solid black;'>" . $result[$index]['latitude'] . "</td>\n";
                       print "        <td style='width:150px;border:1px solid black;'>" . $result[$index]['longitude'] . "</td>\n";
                       print "    </tr>\n";
                       $index = $index + 1;
                   }
                   echo "</table>";
                   
                   if ( $index == 0 )
                   {
                       print "<p>No images found in event.</p>\n";
                   }
               }
               else
               {
                   print "<p>No event found with ID: " . $eventid . "</p>";
               }
               
               echo "<h2>Return to Admin Panel</h2>
<form method=\"POST\" action=\"panel.php\">
    <input type=\"hidden\" name=\"username\" value=\"" . $username . "\" required>
    <input type=\"hidden\" name=\"password\" value=\"" . $password . "\" required>
    <button type=\"submit\">Admin Panel</button>
</form>";
                
               
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

