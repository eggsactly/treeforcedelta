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

function exifFractionToFloat($value): ?float
{
    if (is_string($value) && strpos($value, '/') !== false) {
        [$num, $den] = explode('/', $value, 2);
        if ((float)$den === 0.0) {
            return null;
        }
        return (float)$num / (float)$den;
    }

    if (is_numeric($value)) {
        return (float)$value;
    }

    return null;
}

function exifGpsToDecimal(array $coord, string $ref): ?float
{
    if (count($coord) !== 3) {
        return null;
    }

    $degrees = exifFractionToFloat($coord[0]);
    $minutes = exifFractionToFloat($coord[1]);
    $seconds = exifFractionToFloat($coord[2]);

    if ($degrees === null || $minutes === null || $seconds === null) {
        return null;
    }

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if ($ref === 'S' || $ref === 'W') {
        $decimal *= -1;
    }

    return $decimal;
}

function getImageGpsCoordinates(string $imagePath): ?array
{
    if (!function_exists('exif_read_data')) {
        return null;
    }

    if (!is_file($imagePath)) {
        return null;
    }

    $exif = @exif_read_data($imagePath);

    if (!$exif || empty($exif['GPSLatitude']) || empty($exif['GPSLongitude']) ||
        empty($exif['GPSLatitudeRef']) || empty($exif['GPSLongitudeRef'])) {
        return null;
    }

    $lat = exifGpsToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
    $lon = exifGpsToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef']);

    if ($lat === null || $lon === null) {
        return null;
    }

    return [
        'latitude'  => $lat,
        'longitude' => $lon
    ];
}

function codeToMessage($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
            break;
        case UPLOAD_ERR_PARTIAL:
            $message = "The uploaded file was only partially uploaded";
            break;
        case UPLOAD_ERR_NO_FILE:
            $message = "No file was uploaded";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $message = "Missing a temporary folder";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $message = "Failed to write file to disk";
            break;
        case UPLOAD_ERR_EXTENSION:
            $message = "File upload stopped by extension";
            break;

        default:
            $message = "Unknown upload error";
            break;
    }
    return $message;
}

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
