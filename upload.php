<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Image Upload</title>
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

require 'aws/aws-autoloader.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

include "uploadinclude.php";
include "mysqlinfo.php";
include "spaces-key.php";

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
            $eventName = $result[0]["name"];
            
            // Make the event name not have spaces
            $eventName = str_replace(' ', '_', $eventName);
            
            // ---- Upload configuration ----
            $uploadDir = __DIR__ . '/uploadedImages/';
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', ', image/heic'];
            $maxFileSize = 10 * 1024 * 1024; // 10MB

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $uploaded = 0;
            $errors = [];

            $s3 = NULL;
            $canLoadAWS = isset($access_key) and extension_loaded('mbstring');
            if ($canLoadAWS)
            {
                $s3 = new Aws\S3\S3Client([
                    'version'     => 'latest',
                    'region'      => 'sfo3',
                    'endpoint'    => 'https://sfo3.digitaloceanspaces.com',
                    'credentials' => [
                        'key'    => $access_key,
                        'secret' => $secret_key,
                    ],
                ]);
            }

            // ---- Process each image ----
            foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {

                if ($_FILES['images']['error'][$index] !== UPLOAD_ERR_OK) {
                    $errors[] = "Upload error on file #" . ($index + 1) . ", error code: " . $_FILES['images']['error'][$index] . ": " . codeToMessage($_FILES['images']['error'][$index]);
                    continue;
                }

                if ($_FILES['images']['size'][$index] > $maxFileSize) {
                    $errors[] = "File too large: " . $_FILES['images']['name'][$index];
                    continue;
                }

                $mimeType = finfo_file($finfo, $tmpName);
                if (!in_array($mimeType, $allowedTypes)) {
                    $errors[] = "Invalid file type: " . $_FILES['images']['name'][$index];
                    continue;
                }

                $extension = pathinfo($_FILES['images']['name'][$index], PATHINFO_EXTENSION);
                $filename = uniqid('img_', true) . '.' . $extension;
                $destination = $uploadDir . $filename;

                if (!move_uploaded_file($tmpName, $destination)) {
                    $errors[] = "Failed to save: " . $_FILES['images']['name'][$index];
                    continue;
                }
                
                $coordinates = getImageGpsCoordinates($destination);
                
                if($s3 != NULL)
                {
                    // Transfer file to the digital ocean bucket 
                    try {
                        $result = $s3->putObject([
                            'Bucket'     => 'tcb-drone',
                            'Key'        => 'treeforcedelta-uploads/' . $eventName . '/' . $filename,
                            'SourceFile' => $destination,
                            'ACL'        => 'public-read', 
                            'ContentType'=> mime_content_type($destination),
                        ]);

                        # Once the file is transfered to the DO bucket, delete it
                        unlink($destination);

                    } catch (AwsException $e2) {
                        throw new RuntimeException(
                            'Upload failed: ' . $e2->getAwsErrorMessage()
                        );
                        print "<p>Failed to transfer upload to Digital Ocean Bucket: " . $e2->getAwsErrorMessage() . "</p>";
                    }
                }

                $sql = "INSERT INTO uploaded_images (event_id, filename, upload_date)
                      VALUES ('$eventID', '$filename', '$currentDateTime')";
                      
                if ($coordinates != NULL)
                {
                    $sql = "INSERT INTO uploaded_images (event_id, filename, upload_date, latitude, longitude)
                      VALUES ('$eventID', '$filename', '$currentDateTime', '". $coordinates['latitude'] ."', '" . $coordinates['longitude'] . "')";
                }      
                
                $conn->exec($sql);

                $uploaded++;
            }
            print "<h1>Upload Tree Photos</h1>\n";
            
            print "<p>" . $uploaded . " images uploaded. Thank you.</p>\n";

            $index = 0;
            
            if ( count($errors) > 0 )
            {
                print "<p>Errors during upload:</p>";
            }
            while($index < count($errors))
            {
                print "<p>" . $errors[$index] . "</p>";
                $index = $index + 1;
            }

            print "<form action=\"select-images.php\" method=\"POST\" enctype=\"multipart/form-data\">
        <input type=\"hidden\" name=\"code\" value=\"" . $code . "\" />
        <input type=\"hidden\" name=\"id\" value=\"" . $eventID . "\" />
        <button type=\"submit\">Select More Images</button>
        </form>";

            finfo_close($finfo);
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
