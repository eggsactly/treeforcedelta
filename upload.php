<?php
// Database configuration
$host = "localhost";
$dbname = "your_database";
$username = "your_user";
$password = "your_password";

// Upload configuration
$uploadDir = "uploads/";
$maxFileSize = 5 * 1024 * 1024; // 5 MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Validate file upload
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    die("File upload failed.");
}

$file = $_FILES['image'];

// Validate file size
if ($file['size'] > $maxFileSize) {
    die("File is too large.");
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    die("Invalid file type.");
}

// Generate a safe unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid("img_", true) . "." . $extension;
$destination = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    die("Failed to save file.");
}

// Insert into database
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare(
        "INSERT INTO uploaded_images (filename, upload_date)
         VALUES (:filename, NOW())"
    );

    $stmt->execute([
        ':filename' => $filename
    ]);

    echo "Image uploaded successfully.";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

