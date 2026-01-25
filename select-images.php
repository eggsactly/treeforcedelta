<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Image Upload</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 400px;
            margin: 80px auto;
            padding: 24px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        h1 {
            margin-bottom: 20px;
            font-size: 1.4rem;
        }
        
        form {
            margin-bottom: 32px;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            font-size: 1rem;
            margin-bottom: 12px;
            text-transform: uppercase;
            max-width: 376px;
        }

        input[type="file"] {
            width: 100%;
            margin-bottom: 16px;
            font-size: 1rem;
        }

        button {
            width: 100%;
            padding: 14px;
            font-size: 1rem;
            cursor: pointer;
            border: none;
            border-radius: 6px;
            background: #0066cc;
            color: white;
        }

        button:active {
            background: #004c99;
        }
        
        .admin-link {
            display: block;
            margin-top: 16px;
            text-decoration: none;
            color: #0066cc;
        }
    </style>
</head>
<body>
<div class="container">

<?php
include "code_generator.php";
include "mysqlinfo.php";

date_default_timezone_set('America/Phoenix');

$code = strtoupper(trim($_POST['code']));
$id = strtoupper(trim($_POST['id']));
$codelength = strlen($code);

if ($codelength == 8)
{
    try
    {
        $currentDateTime = date("Y-m-d H:i:s");
        
        $conn = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            "$dbusername",
            "$dbpassword",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $conn->prepare("SELECT id, name FROM events WHERE start_time <= \"" . $currentDateTime . "\" AND end_time >= \"". $currentDateTime . "\" AND code = \"" . $code . "\"");
        $stmt->execute();

        $result = $stmt->fetchAll();
        
        if(count($result) > 0)
        {
            $eventID = $result[0]["id"];
            print "<h1>Upload Tree Photos</h1>
    <p>Please select images from your device to upload.</p>
        <form action=\"upload.php\" method=\"POST\" enctype=\"multipart/form-data\">
        <input 
            type=\"file\" 
            name=\"images[]\" 
            accept=\"image/\" 
            capture=\"environment\"
            required
        />
        <input type=\"hidden\" name=\"code\" value=\"" . $code . "\" />
        <input type=\"hidden\" name=\"id\" value=\"" . $eventID . "\" />
        <button type=\"submit\">Upload Images</button>
        </form>";
        }
        else
        {
            print "
    <h1>Upload Tree Photos</h1>
    <p>Invalid event code, code may be mistyped, the event may not have not started, or it may have ended.</p>
    <p>Please try again.</p>

    <!-- Participant path -->
    <form action=\"select-images.php\" method=\"POST\">
        <input
            type=\"text\"
            name=\"code\"
            placeholder=\"Enter Event Code\"
            required
        >
        <button type=\"submit\">Continue</button>
    </form>

    <!-- Admin path -->
    <a class=\"admin-link\" href=\"admin/login.html\">
        Admin Login
    </a>
    
    <p>This site does not use cookies. Some functions, such as logging in, may require manual intervention or may need to be repeated regularly.</p>
";
        }
        
    }
    catch(PDOException $e) {
        echo "<p>MySql Connection failed: " . $e->getMessage() . "</p>";
    }

}
else
{
    print "
    <h1>Upload Tree Photos</h1>
    <p>You typed in a " . $codelength . " character long code.</p>
    <p>Event codes are 8 characters long. Please verify your code is correct.</p>
    <!-- Participant path -->
    <form action=\"select-images.php\" method=\"POST\">
        <input
            type=\"text\"
            name=\"code\"
            placeholder=\"Enter Event Code\"
            required
        >
        <button type=\"submit\">Continue</button>
    </form>

    <!-- Admin path -->
    <a class=\"admin-link\" href=\"admin/login.html\">
        Admin Login
    </a>
    
    <p>This site does not use cookies. Some functions, such as logging in, may require manual intervention or may need to be repeated regularly.</p>
";
}

    
    
?>
</div>
</body>
</html>


