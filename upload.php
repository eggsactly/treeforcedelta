<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Image Upload</title>
</head>
<body>

<?php
session_start();

if (!isset($_SESSION['event_id'])) {
    die("Unauthorized upload attempt.");
}

$eventId = $_SESSION['event_id'];

// Re-check event validity
$stmt = $pdo->prepare("
    SELECT start_time, end_time, is_active
    FROM events
    WHERE id = :id
");

$stmt->execute([':id' => $eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event || !$event['is_active']) {
    die("Event disabled.");
}

$now = new DateTime();
if ($now < new DateTime($event['start_time']) ||
    $now > new DateTime($event['end_time'])) {
    die("Event expired.");
}

?>

</body>
</html>
