<?php
header('Content-Type: application/json');
require 'aws/aws-autoloader.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

include "uploadinclude.php";
include "mysqlinfo.php";
include "spaces-key.php";

date_default_timezone_set('America/Phoenix');

if (!isset($_FILES['images'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No file received'
    ]);
    exit;
}

if (!isset($_POST['code'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No code provided'
    ]);
    exit;
}

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

            $error = "";

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

            $tmpName = $_FILES['images']['tmp_name'];

            if ($_FILES['images']['error'] !== UPLOAD_ERR_OK) {
                $error = "Upload error, error code: " . $_FILES['images']['error'] . ": " . codeToMessage($_FILES['images']['error']);
                echo json_encode([
                    'success' => false,
                    'error' => $error
                ]);
                exit;
            }

            if ($_FILES['images']['size'] > $maxFileSize) {
                $error = "File too large: " . $_FILES['images']['name'];
                echo json_encode([
                    'success' => false,
                    'error' => $error
                ]);
                exit;
            }

            $mimeType = finfo_file($finfo, $tmpName);
            if (!in_array($mimeType, $allowedTypes)) {
                $error = "Invalid file type: " . $_FILES['images']['name'];
                echo json_encode([
                    'success' => false,
                    'error' => $error
                ]);
                exit;
            }

            $extension = pathinfo($_FILES['images']['name'], PATHINFO_EXTENSION);
            $filename = uniqid('img_', true) . '.' . $extension;
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file($tmpName, $destination)) {
                $error = "Failed to save: " . $_FILES['images']['name'];
                echo json_encode([
                    'success' => false,
                    'error' => $error
                ]);
                exit;
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
                    echo json_encode([
                        'success' => false,
                        'error' => 'Upload failed: ' . $e2->getAwsErrorMessage()
                    ]);
                    exit;
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
            
            finfo_close($finfo);
            
            echo json_encode([
                'success' => true,
                'filename' => $_FILES['images']['name']
            ]);
        }
        else
        {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid event code.'
            ]);
            exit;
        }
        
    }
    catch(PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => 'MySql Connection failed: ' . $e->getMessage() . '.'
        ]);
        exit;
    }
}
else
{
    echo json_encode([
        'success' => false,
        'error' => 'Event codes are 8 characters long, yours was ' . $codelength . '.'
    ]);
    exit;
}

?>

