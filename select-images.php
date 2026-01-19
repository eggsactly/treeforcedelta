<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Image Upload</title>
</head>
<body>

<?php
session_start();

$code = strtoupper(trim($_POST['code']));

$stmt = $pdo->prepare("
    SELECT id, start_time, end_time
    FROM events
    WHERE code = :code
      AND is_active = 1
");

$stmt->execute([':code' => $code]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    print"<p>Invalid event code.</p>";
}
else
{

    $now = new DateTime();
    $start = new DateTime($event['start_time']);
    $end = new DateTime($event['end_time']);

    if ($now < $start || $now > $end) 
    {
        print "<p>Event is not active.</p>";
    }
    else
    {
        // Grant upload permission
        $_SESSION['event_id'] = $event['id'];
        print"    <form action=\"upload.php\" method=\"POST\" enctype=\"multipart/form-data\">
    <input 
        type=\"file\" 
        name=\"image\" 
        accept=\"image/*\" 
        capture=\"environment\"
        required
    >
    <button type=\"submit\">Upload Image</button>
    </form>";
    }
    
    
?>

</body>
</html>


