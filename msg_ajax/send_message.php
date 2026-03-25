<?php
ob_start();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

require_once __DIR__ . '/../config.php';

$username = $_SESSION['username'];
$user_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'"));
if (!$user_row) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}
$user_id = $user_row['id'];

$convo_id = isset($_POST['convo_id']) ? (int) $_POST['convo_id'] : 0;
$message = isset($_POST['message']) ? mysqli_real_escape_string($conn, trim($_POST['message'])) : '';

if (empty($message) || $convo_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

// Verify user belongs to this conversation
$check = "SELECT * FROM conversations WHERE id = '$convo_id' AND (user1_id = '$user_id' OR user2_id = '$user_id')";
if (mysqli_num_rows(mysqli_query($conn, $check)) == 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Insert message
$insert = "INSERT INTO messages (conversation_id, sender_id, message, is_system) VALUES ('$convo_id', '$user_id', '$message', 0)";

if (mysqli_query($conn, $insert)) {
    echo json_encode(['success' => true, 'message_id' => mysqli_insert_id($conn)]);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
}

mysqli_close($conn);
?>