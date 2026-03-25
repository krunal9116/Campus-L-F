<?php
ob_start();
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false]);
    exit();
}

require_once __DIR__ . '/../config.php';
$username = mysqli_real_escape_string($conn, $_SESSION['username']);
$key = mysqli_real_escape_string($conn, $_POST['key'] ?? '');

if ($key) {
    mysqli_query($conn, "UPDATE users SET notif_seen_key = '$key' WHERE username = '$username'");
}
echo json_encode(['success' => true]);
mysqli_close($conn);
?>