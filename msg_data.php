<?php
$username = $_SESSION['username'];
require_once __DIR__ . '/config.php';

$user_query = "SELECT * FROM users WHERE username = '$username'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
if (!$user_data) {
    session_destroy();
    header("Location: index.php");
    exit();
}
$user_id = $user_data['id'];

$has_photo = !empty($user_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $user_data['profile_photo'] : '';

if (isset($_GET['item_chat'])) {
    $item_id_chat = (int) $_GET['item_chat'];
    $item_query = "SELECT user_id FROM items WHERE id = '$item_id_chat'";
    $item_result = mysqli_query($conn, $item_query);
    if ($item_result && mysqli_num_rows($item_result) > 0) {
        $item_owner_id = mysqli_fetch_assoc($item_result)['user_id'];
        if ($item_owner_id != $user_id) {
            $check = "SELECT id FROM conversations WHERE item_id = '$item_id_chat' AND type = 'user_user' AND ((user1_id = '$user_id' AND user2_id = '$item_owner_id') OR (user1_id = '$item_owner_id' AND user2_id = '$user_id')) LIMIT 1";
            $check_result = mysqli_query($conn, $check);
            if ($check_result && mysqli_num_rows($check_result) > 0) {
                $convo_id = mysqli_fetch_assoc($check_result)['id'];
            } else {
                $insert = "INSERT INTO conversations (user1_id, user2_id, item_id, type) VALUES ('$user_id', '$item_owner_id', '$item_id_chat', 'user_user')";
                mysqli_query($conn, $insert);
                $convo_id = mysqli_insert_id($conn);
            }
            header("Location: messages.php?convo=$convo_id");
            exit();
        }
    }
}

$uid = $user_id;
$convos_query = "SELECT c.*,
    CASE WHEN c.user1_id = '$uid' THEN u2.username ELSE u1.username END as other_username,
    CASE WHEN c.user1_id = '$uid' THEN u2.id ELSE u1.id END as other_user_id,
    CASE WHEN c.user1_id = '$uid' THEN u2.profile_photo ELSE u1.profile_photo END as other_photo,
    CASE WHEN c.user1_id = '$uid' THEN u2.role ELSE u1.role END as other_role,
    i.item_name, i.report_type as item_type,
    (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
    (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != '$uid' AND is_read = 0) as unread_count
    FROM conversations c
    JOIN users u1 ON c.user1_id = u1.id
    JOIN users u2 ON c.user2_id = u2.id
    LEFT JOIN items i ON c.item_id = i.id
    WHERE c.user1_id = '$uid' OR c.user2_id = '$uid'
    ORDER BY last_message_time DESC";
$convos_result = mysqli_query($conn, $convos_query);

$admin_convos = [];
$user_convos = [];
if ($convos_result && mysqli_num_rows($convos_result) > 0) {
    while ($convo = mysqli_fetch_assoc($convos_result)) {
        if ($convo['type'] == 'user_admin') {
            $admin_convos[] = $convo;
        } else {
            $user_convos[] = $convo;
        }
    }
}

$active_convo_id = isset($_GET['convo']) ? (int) $_GET['convo'] : 0;
$active_convo = null;

if ($active_convo_id > 0) {
    $convo_query = "SELECT c.*,
        CASE WHEN c.user1_id = '$uid' THEN u2.username ELSE u1.username END as other_username,
        CASE WHEN c.user1_id = '$uid' THEN u2.id ELSE u1.id END as other_user_id,
        CASE WHEN c.user1_id = '$uid' THEN u2.profile_photo ELSE u1.profile_photo END as other_photo,
        CASE WHEN c.user1_id = '$uid' THEN u2.role ELSE u1.role END as other_role,
        i.item_name, i.report_type as item_type
        FROM conversations c
        JOIN users u1 ON c.user1_id = u1.id
        JOIN users u2 ON c.user2_id = u2.id
        LEFT JOIN items i ON c.item_id = i.id
        WHERE c.id = '$active_convo_id' AND (c.user1_id = '$uid' OR c.user2_id = '$uid')";
    $convo_result = mysqli_query($conn, $convo_query);
    if ($convo_result && mysqli_num_rows($convo_result) > 0) {
        $active_convo = mysqli_fetch_assoc($convo_result);
        $mark_read = "UPDATE messages SET is_read = 1 WHERE conversation_id = '$active_convo_id' AND sender_id != '$uid'";
        mysqli_query($conn, $mark_read);
    }
}

$unread_query = "SELECT COUNT(*) as count FROM messages m JOIN conversations c ON m.conversation_id = c.id WHERE (c.user1_id = '$uid' OR c.user2_id = '$uid') AND m.sender_id != '$uid' AND m.is_read = 0";
$unread_result = mysqli_query($conn, $unread_query);
$total_unread = ($unread_result) ? mysqli_fetch_assoc($unread_result)['count'] : 0;
