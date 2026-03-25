<?php
ob_start();
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Auto-create msg_ajax directory and files on first run
if (!is_dir(__DIR__ . '/msg_ajax')) {
    require_once __DIR__ . '/_create_msg_ajax.php';
}

$username = $_SESSION['username'];
require_once __DIR__ . '/config.php';

$user_query = "SELECT * FROM users WHERE username = '$username'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
if (!$user_data) { session_destroy(); header("Location: index.php"); exit(); }
$user_id = $user_data['id'];

$has_photo = !empty($user_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $user_data['profile_photo'] : '';

// ========================
// GET CONVERSATIONS
// ========================
$convos_query = "SELECT c.*,
                    CASE WHEN c.user1_id = '$user_id' THEN u2.username ELSE u1.username END as other_username,
                    CASE WHEN c.user1_id = '$user_id' THEN u2.id      ELSE u1.id      END as other_user_id,
                    CASE WHEN c.user1_id = '$user_id' THEN u2.profile_photo ELSE u1.profile_photo END as other_photo,
                    CASE WHEN c.user1_id = '$user_id' THEN u2.role    ELSE u1.role    END as other_role,
                    i.item_name,
                    i.report_type as item_type,
                    (SELECT message    FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
                    (SELECT COUNT(*)   FROM messages WHERE conversation_id = c.id AND sender_id != '$user_id' AND is_read = 0) as unread_count
                FROM conversations c
                JOIN users u1 ON c.user1_id = u1.id
                JOIN users u2 ON c.user2_id = u2.id
                LEFT JOIN items i ON c.item_id = i.id
                WHERE c.user1_id = '$user_id' OR c.user2_id = '$user_id'
                ORDER BY last_message_time DESC";
$convos_result = mysqli_query($conn, $convos_query);

$all_convos = [];
$seen_other_users = []; // deduplicate: one convo per other user, prefer item_id=NULL
$raw_convos = [];
if ($convos_result && mysqli_num_rows($convos_result) > 0) {
    while ($c = mysqli_fetch_assoc($convos_result)) {
        $raw_convos[] = $c;
    }
}
// First pass: add item_id=NULL convos (Direct Support)
foreach ($raw_convos as $c) {
    if ($c['item_id'] === null) {
        $seen_other_users[$c['other_user_id']] = true;
        $all_convos[] = $c;
    }
}
// Second pass: add item-specific convos only if no general convo for that user
foreach ($raw_convos as $c) {
    if ($c['item_id'] !== null && empty($seen_other_users[$c['other_user_id']])) {
        $seen_other_users[$c['other_user_id']] = true;
        $all_convos[] = $c;
    }
}

// Active conversation
$active_convo_id = isset($_GET['convo']) ? (int) $_GET['convo'] : 0;
$active_convo = null;

if ($active_convo_id > 0) {
    $convo_query = "SELECT c.*,
                        CASE WHEN c.user1_id='$user_id' THEN u2.username ELSE u1.username END as other_username,
                        CASE WHEN c.user1_id='$user_id' THEN u2.id       ELSE u1.id       END as other_user_id,
                        CASE WHEN c.user1_id='$user_id' THEN u2.profile_photo ELSE u1.profile_photo END as other_photo,
                        CASE WHEN c.user1_id='$user_id' THEN u2.role     ELSE u1.role     END as other_role,
                        i.item_name, i.report_type as item_type
                    FROM conversations c
                    JOIN users u1 ON c.user1_id = u1.id
                    JOIN users u2 ON c.user2_id = u2.id
                    LEFT JOIN items i ON c.item_id = i.id
                    WHERE c.id = '$active_convo_id'
                      AND (c.user1_id = '$user_id' OR c.user2_id = '$user_id')";
    $convo_result = mysqli_query($conn, $convo_query);

    if ($convo_result && mysqli_num_rows($convo_result) > 0) {
        $active_convo = mysqli_fetch_assoc($convo_result);
        mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE conversation_id = '$active_convo_id' AND sender_id != '$user_id'");
    }
}

// Total unread
$unread_query = "SELECT COUNT(*) as count FROM messages m
                 JOIN conversations c ON m.conversation_id = c.id
                 WHERE (c.user1_id='$user_id' OR c.user2_id='$user_id')
                 AND m.sender_id != '$user_id' AND m.is_read = 0";
$unread_result = mysqli_query($conn, $unread_query);
$total_unread = ($unread_result) ? mysqli_fetch_assoc($unread_result)['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="responsive.css">
    <link rel="stylesheet" href="dark-mode.css">
    <script src="dark-mode.js"></script>
    <title>Admin Chat - Campus Find</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-msg-style.css">
</head>

<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <a href="admin_dashboard.php" class="back-btn">← Dashboard</a>
            <h1>💬 Chat <span class="admin-badge">ADMIN</span></h1>
            <?php if ($total_unread > 0): ?>
                <span
                    style="background:#e74c3c; color:white; padding:2px 8px; border-radius:50%; font-size:12px; font-weight:700;"><?php echo $total_unread; ?></span>
            <?php endif; ?>
        </div>
        <div class="nav-right">
            <a href="admin_profile.php" class="nav-avatar" title="My Profile">
                <?php if ($has_photo): ?>
                    <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- CHAT LAYOUT -->
    <div class="chat-container">

        <!-- SIDEBAR -->
        <div class="chat-sidebar">
            <div class="sidebar-header">
                <h3>💬 All Conversations</h3>
                <span style="font-size:12px; color:#888;"><?php echo count($all_convos); ?> chat(s)</span>
            </div>

            <div class="sidebar-search">
                <input type="text" id="searchChat" placeholder="🔍 Search conversations or users..."
                    oninput="handleSearch(this.value)">
            </div>

            <!-- New chat user search results (hidden until typing) -->
            <div class="user-search-results" id="userSearchResults" style="display:none;">
                <?php
                $all_users = [];
                $ur = mysqli_query($conn, "SELECT id, username, profile_photo FROM users WHERE role='user' ORDER BY username ASC");
                while ($u = mysqli_fetch_assoc($ur)) {
                    $all_users[] = $u;
                }
                foreach ($all_users as $u):
                    $pp = !empty($u['profile_photo']) ? 'uploads/profile_photos/' . htmlspecialchars($u['profile_photo']) : '';
                    ?>
                    <a href="admin_open_convo.php?user_id=<?php echo $u['id']; ?>" class="user-search-item"
                        data-username="<?php echo strtolower(htmlspecialchars($u['username'])); ?>">
                        <div class="user-search-avatar">
                            <?php if ($pp && file_exists($pp)): ?>
                                <img src="<?php echo $pp; ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"
                                    alt="">
                            <?php else: ?>
                                <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div><?php echo htmlspecialchars($u['username']); ?></div>
                            <div class="user-search-label">Click to open / start chat</div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="convo-list">
                <?php if (count($all_convos) > 0):
                    foreach ($all_convos as $convo):
                        $is_active = ($convo['id'] == $active_convo_id) ? 'active' : '';
                        $first_letter = strtoupper(substr($convo['other_username'], 0, 1));
                        $pp = !empty($convo['other_photo']) ? 'uploads/profile_photos/' . htmlspecialchars($convo['other_photo']) : '';
                        $msg_time = '';
                        if (!empty($convo['last_message_time'])) {
                            $today = date('Y-m-d');
                            $msg_date = date('Y-m-d', strtotime($convo['last_message_time']));
                            $msg_time = ($today == $msg_date)
                                ? date('h:i A', strtotime($convo['last_message_time']))
                                : date('d M', strtotime($convo['last_message_time']));
                        }
                        ?>
                        <a href="admin_messages.php?convo=<?php echo $convo['id']; ?>" class="convo-item <?php echo $is_active; ?>"
                            data-name="<?php echo strtolower(htmlspecialchars($convo['other_username'])); ?>">

                            <div class="convo-avatar">
                                <?php if ($pp && file_exists($pp)): ?>
                                    <img src="<?php echo $pp; ?>" alt="">
                                <?php else: ?>
                                    <?php echo $first_letter; ?>
                                <?php endif; ?>
                            </div>

                            <div class="convo-info">
                                <div class="name"><?php echo htmlspecialchars($convo['other_username']); ?></div>
                                <?php if (!empty($convo['item_name'])): ?>
                                    <div class="item-label">📦 <?php echo htmlspecialchars($convo['item_name']); ?></div>
                                <?php else: ?>
                                    <div class="item-label">🛠️ Direct Support</div>
                                <?php endif; ?>
                                <div class="last-msg">
                                    <?php echo !empty($convo['last_message']) ? htmlspecialchars($convo['last_message']) : 'No messages yet'; ?>
                                </div>
                            </div>

                            <div class="convo-meta">
                                <div class="time"><?php echo $msg_time; ?></div>
                                <?php if ($convo['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $convo['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="convo-actions" onclick="event.preventDefault(); event.stopPropagation();">
                                <button class="convo-dots"
                                    onclick="toggleConvoMenu(event,<?php echo $convo['id']; ?>)">⋮</button>
                                <div class="convo-actions-menu" id="cmenu-<?php echo $convo['id']; ?>"
                                    onclick="event.stopPropagation()">
                                    <button class="convo-action-btn"
                                        onclick="chatAction('clear',<?php echo $convo['id']; ?>)">🧹 Clear Chat</button>
                                    <button class="convo-action-btn danger"
                                        onclick="chatAction('delete',<?php echo $convo['id']; ?>)">🗑️ Delete Chat</button>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; else: ?>
                    <div class="no-convos">
                        <span>💬</span>
                        <p>No conversations yet</p>
                        <p style="font-size:11px; margin-top:5px; color:#bbb;">Chats appear when claims are made or you open
                            chat from Manage Users</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CHAT AREA -->
        <div class="chat-area">
            <?php if ($active_convo): ?>

                <div class="chat-header">
                    <div class="chat-header-avatar">
                        <?php if (!empty($active_convo['other_photo']) && file_exists('uploads/profile_photos/' . $active_convo['other_photo'])): ?>
                            <img src="uploads/profile_photos/<?php echo $active_convo['other_photo']; ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(substr($active_convo['other_username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="chat-header-info">
                        <h3><?php echo htmlspecialchars($active_convo['other_username']); ?></h3>
                        <div class="sub">
                            <?php if (!empty($active_convo['item_name'])): ?>
                                📦 <?php echo htmlspecialchars($active_convo['item_name']); ?>
                                &nbsp;·&nbsp; <?php echo ucfirst($active_convo['item_type'] ?? ''); ?>
                            <?php else: ?>
                                🛠️ Direct Support Chat
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="messages-area" id="messagesArea"></div>

                <div class="chat-input">
                    <input type="text" id="messageInput" placeholder="Type a message..." autocomplete="off"
                        onkeypress="if(event.key==='Enter')sendMessage()">
                    <button class="send-btn" onclick="sendMessage()">➤</button>
                </div>

            <?php else: ?>
                <div class="no-chat-selected">
                    <span>💬</span>
                    <h3>Select a conversation</h3>
                    <p>Pick a user from the left to start chatting</p>
                    <p style="font-size:13px; margin-top:5px;">Or open chat from <a href="admin_manage_users.php"
                            style="color:#7b9fff; font-weight:600;">Manage Users</a></p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        var convoId = <?php echo $active_convo_id; ?>;
        var userId = <?php echo $user_id; ?>;
    </script>
    <script src="admin-msg-script.js"></script>

    <?php mysqli_close($conn); ?>
</body>

</html>
