<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/config.php';
$admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE username = '" . mysqli_real_escape_string($conn, $_SESSION['username']) . "'"));
$admin_id = $admin['id'];

$target_user_id = intval($_GET['user_id'] ?? 0);
if (!$target_user_id) {
    header("Location: admin_manage_users.php");
    exit();
}

// Find or create a general admin<->user conversation (item_id = NULL)
$check = mysqli_query(
    $conn,
    "SELECT id FROM conversations 
     WHERE type='user_admin' AND item_id IS NULL
     AND ((user1_id=$admin_id AND user2_id=$target_user_id) OR (user1_id=$target_user_id AND user2_id=$admin_id))
     LIMIT 1"
);

if (mysqli_num_rows($check) > 0) {
    $convo_id = mysqli_fetch_assoc($check)['id'];
} else {
    mysqli_query($conn, "INSERT INTO conversations (user1_id, user2_id, item_id, type) VALUES ($admin_id, $target_user_id, NULL, 'user_admin')");
    $convo_id = mysqli_insert_id($conn);
}

mysqli_close($conn);
header("Location: admin_messages.php?convo=$convo_id");
exit();
?>
