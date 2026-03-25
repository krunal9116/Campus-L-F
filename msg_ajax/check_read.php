<?php
ob_start();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['read_ids' => []]);
    exit();
}

require_once __DIR__ . '/../config.php';

$username = $_SESSION['username'];
$user_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'"));
if (!$user_row) {
    echo json_encode(['read_ids' => []]);
    exit();
}
$user_id = $user_row['id'];

$convo_id = isset($_GET['convo_id']) ? (int) $_GET['convo_id'] : 0;

// Verify user belongs to this conversation
$check = "SELECT * FROM conversations WHERE id = '$convo_id' AND (user1_id = '$user_id' OR user2_id = '$user_id')";
if (mysqli_num_rows(mysqli_query($conn, $check)) == 0) {
    echo json_encode(['read_ids' => []]);
    exit();
}

// Get all read message IDs sent by this user
$query = "SELECT id FROM messages WHERE conversation_id = '$convo_id' AND sender_id = '$user_id' AND is_read = 1";
$result = mysqli_query($conn, $query);

$read_ids = [];
while ($row = mysqli_fetch_assoc($result)) {
    $read_ids[] = (int) $row['id'];
}

echo json_encode(['read_ids' => $read_ids]);

mysqli_close($conn);
?>