<?php
session_start();

include ../code_generator.php 

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$pdo = new PDO(
    "mysql:host=localhost;dbname=your_database;charset=utf8mb4",
    "your_user",
    "your_password",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$message = '';
$eventCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = new DateTime($_POST['start_time']);
    $end   = new DateTime($_POST['end_time']);

    $duration = $end->getTimestamp() - $start->getTimestamp();

    if ($duration <= 0 || $duration > 8 * 3600) {
        $message = "Event duration must be greater than 0 and not exceed 8 hours.";
    } else {
        do {
            $eventCode = generateEventCode();
            $stmt = $pdo->prepare("SELECT id FROM events WHERE code = :code");
            $stmt->execute([':code' => $eventCode]);
        } while ($stmt->fetch());

        $stmt = $pdo->prepare("
            INSERT INTO events (code, start_time, end_time)
            VALUES (:code, :start, :end)
        ");

        $stmt->execute([
            ':code'  => $eventCode,
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s')
        ]);

        $message = "Event created successfully.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Control Panel</title>
</head>
<body>

<h2>Admin Control Panel</h2>

<?php if ($message): ?>
<p><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<form method="POST">
    <label>Event Start</label><br>
    <input type="datetime-local" name="start_time" required><br><br>

    <label>Event End</label><br>
    <input type="datetime-local" name="end_time" required><br><br>

    <button type="submit">Create Event</button>
</form>

<?php if ($eventCode): ?>
<h3>Event Code</h3>
<p><strong><?php echo htmlspecialchars($eventCode); ?></strong></p>
<?php endif; ?>

<br>
<a href="logout.php">Logout</a>

</body>
</html>

