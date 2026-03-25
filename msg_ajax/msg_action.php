<?php
ob_start();
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false]);
    exit();
}

require_once __DIR__ . '/../config.php';
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE username='" . mysqli_real_escape_string($conn, $_SESSION['username']) . "'"));
$me_id = $me['id'];
$action = $_POST['action'] ?? '';
$convo_id = intval($_POST['convo_id'] ?? 0);

if (!$convo_id) {
    echo json_encode(['success' => false, 'msg' => 'Invalid']);
    exit();
}

// Verify user is part of this conversation
$check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM conversations WHERE id=$convo_id AND (user1_id=$me_id OR user2_id=$me_id)"));
if (!$check) {
    echo json_encode(['success' => false, 'msg' => 'Unauthorized']);
    exit();
}

if ($action === 'clear') {
    // Delete all messages in this conversation
    mysqli_query($conn, "DELETE FROM messages WHERE conversation_id=$convo_id");
    echo json_encode(['success' => true]);
} elseif ($action === 'delete') {
    // Delete messages first, then conversation
    mysqli_query($conn, "DELETE FROM messages WHERE conversation_id=$convo_id");
    mysqli_query($conn, "DELETE FROM conversations WHERE id=$convo_id");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'msg' => 'Unknown action']);
}
mysqli_close($conn);
?>