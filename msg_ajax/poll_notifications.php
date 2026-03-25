<?php
ob_start();
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['count' => 0]);
    exit();
}

require_once __DIR__ . '/../config.php';
$username = $_SESSION['username'];
$role = $_SESSION['role'];

$user_data = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT id FROM users WHERE username = '" . mysqli_real_escape_string($conn, $username) . "' LIMIT 1"
));
if (!$user_data) {
    echo json_encode(['count' => 0]);
    exit();
}
$user_id = $user_data['id'];

if ($role === 'admin') {
    $pending = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) as c FROM claims WHERE status='pending' AND countdown_end <= '" . date('Y-m-d H:i:s') . "'"
    ))['c'];
    $new_reports = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) as c FROM items WHERE date_reported >= NOW() - INTERVAL 1 DAY"
    ))['c'];
    $unread_chat = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) as c FROM messages m JOIN conversations cv ON m.conversation_id=cv.id
         WHERE (cv.user1_id='$user_id' OR cv.user2_id='$user_id') AND m.sender_id!='$user_id' AND m.is_read=0"
    ))['c'];

    // Check if admin has already seen these notifications
    $admin_data = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT notif_seen_key FROM users WHERE id='$user_id' LIMIT 1"
    ));
    $admin_stored_key = $admin_data['notif_seen_key'] ?? '';
    $admin_notif_key = "p{$pending}_r{$new_reports}";
    $notif_unseen = ($admin_stored_key !== $admin_notif_key) ? ($pending + $new_reports) : 0;

    echo json_encode([
        'notifications' => intval($notif_unseen),
        'chat' => intval($unread_chat),
        'pending_claims' => intval($pending),
        'new_reports' => intval($new_reports)
    ]);
} else {
    // User: only count approved/rejected that haven't been "seen" yet
    $user_data = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT notif_seen_key FROM users WHERE id='$user_id' LIMIT 1"
    ));
    $stored_key = $user_data['notif_seen_key'] ?? '';

    $notif_q = mysqli_query(
        $conn,
        "SELECT c.id FROM claims c WHERE c.user_id='$user_id' AND c.status IN ('approved','rejected') ORDER BY c.claim_date DESC LIMIT 10"
    );
    $ids = [];
    while ($r = mysqli_fetch_assoc($notif_q)) {
        $ids[] = $r['id'];
    }
    $notif_key = count($ids) > 0 ? implode(',', $ids) : 'none';
    $notif_unseen = ($stored_key !== $notif_key) ? count($ids) : 0;

    $unread_chat = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT COUNT(*) as c FROM messages m JOIN conversations cv ON m.conversation_id=cv.id
         WHERE (cv.user1_id='$user_id' OR cv.user2_id='$user_id') AND m.sender_id!='$user_id' AND m.is_read=0"
    ))['c'];
    echo json_encode([
        'notifications' => intval($notif_unseen),
        'chat' => intval($unread_chat)
    ]);
}
mysqli_close($conn);
?>