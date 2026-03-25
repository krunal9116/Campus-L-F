<?php
/**
 * Finds or creates a single admin↔user conversation (item_id=NULL) and sends a system message.
 * All admin messages (approval, rejection, direct) go into the same conversation per user.
 */
function createAdminChat($conn, $admin_id, $user_id, $item_id, $system_message)
{
    // Always use the general admin<->user conversation (item_id=NULL)
    $check = "SELECT id FROM conversations WHERE type = 'user_admin' AND item_id IS NULL
              AND ((user1_id = '$admin_id' AND user2_id = '$user_id')
                OR (user1_id = '$user_id'  AND user2_id = '$admin_id')) LIMIT 1";
    $check_result = mysqli_query($conn, $check);

    if (mysqli_num_rows($check_result) > 0) {
        $convo_id = mysqli_fetch_assoc($check_result)['id'];
    } else {
        mysqli_query($conn, "INSERT INTO conversations (user1_id, user2_id, item_id, type) VALUES ('$admin_id', '$user_id', NULL, 'user_admin')");
        $convo_id = mysqli_insert_id($conn);
    }

    $system_message = mysqli_real_escape_string($conn, $system_message);
    mysqli_query($conn, "INSERT INTO messages (conversation_id, sender_id, message, is_system) VALUES ('$convo_id', '$admin_id', '$system_message', 1)");

    return $convo_id;
}
?>