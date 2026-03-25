<?php
ob_start();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['messages' => []]);
    exit();
}

require_once __DIR__ . '/../config.php';

if (!$conn) {
    echo json_encode(['messages' => [], 'error' => 'DB connection failed']);
    exit();
}

$username = $_SESSION['username'];
$user_result = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");

if (!$user_result || mysqli_num_rows($user_result) == 0) {
    echo json_encode(['messages' => [], 'error' => 'User not found']);
    exit();
}

$user_id = mysqli_fetch_assoc($user_result)['id'];

$convo_id = isset($_GET['convo_id']) ? (int) $_GET['convo_id'] : 0;
$last_id = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;

if ($convo_id == 0) {
    echo json_encode(['messages' => [], 'error' => 'No convo_id']);
    exit();
}

// Verify user belongs to this conversation
$check = mysqli_query($conn, "SELECT * FROM conversations WHERE id = '$convo_id' AND (user1_id = '$user_id' OR user2_id = '$user_id')");

if (!$check || mysqli_num_rows($check) == 0) {
    echo json_encode(['messages' => [], 'error' => 'Not authorized']);
    exit();
}

// Mark as read
mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE conversation_id = '$convo_id' AND sender_id != '$user_id' AND is_read = 0");

// Get messages
$messages_query = "SELECT * FROM messages WHERE conversation_id = '$convo_id' AND id > '$last_id' ORDER BY created_at ASC";
$result = mysqli_query($conn, $messages_query);

if (!$result) {
    echo json_encode(['messages' => [], 'error' => mysqli_error($conn)]);
    exit();
}

$messages = [];
while ($row = mysqli_fetch_assoc($result)) {
    $messages[] = [
        'id' => (int) $row['id'],
        'sender_id' => (int) $row['sender_id'],
        'message' => $row['message'],
        'is_read' => (int) $row['is_read'],
        'is_system' => isset($row['is_system']) ? (int) $row['is_system'] : 0,
        'created_at' => $row['created_at']
    ];
}

echo json_encode(['messages' => $messages]);

mysqli_close($conn);
?>