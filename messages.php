<?php
ob_start();
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: index.php");
    exit();
}
// Auto-create msg_ajax directory and files on first run
if (!is_dir(__DIR__ . '/msg_ajax')) {
    require_once __DIR__ . '/_create_msg_ajax.php';
}
require_once __DIR__ . '/msg_data.php';
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
    <title>Chat - Campus Find</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="msg-style.css">
</head>

<body>

    <!-- Navbar -->
    <div class="navbar">
        <div class="nav-left">
            <a href="user_dashboard.php" class="back-btn">← Dashboard</a>
            <h1>💬 Chat</h1>
            <?php if ($total_unread > 0) { ?>
                <span
                    style="background: #ff1900; color: white; padding: 2px 8px; border-radius: 50%; font-size: 12px; font-weight: 700;"><?php echo $total_unread; ?></span>
            <?php } ?>
        </div>

        <a href="profile.php" class="nav-avatar" title="My Profile">
            <?php if ($has_photo) { ?>
                <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile">
            <?php } else { ?>
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            <?php } ?>
        </a>
    </div>

    <!-- Chat Layout -->
    <div class="chat-container">

        <!-- Sidebar -->
        <div class="chat-sidebar">
            <div class="sidebar-header">
                <h3>💬 Conversations</h3>
                <button class="new-chat-btn" onclick="openNewChatModal()">+ New</button>
            </div>

            <div class="sidebar-search">
                <input type="text" id="searchChat" placeholder="🔍 Search conversations..."
                    onkeyup="filterConversations()">
            </div>

            <!-- Tabs -->
            <div class="sidebar-tabs">
                <div class="sidebar-tab active" onclick="switchTab('all')" id="tabAll">
                    All
                </div>
                <div class="sidebar-tab" onclick="switchTab('admin')" id="tabAdmin">
                    🛡️ Admin
                    <?php
                    $admin_unread = 0;
                    foreach ($admin_convos as $ac) {
                        $admin_unread += $ac['unread_count'];
                    }
                    if ($admin_unread > 0) {
                        echo '<span class="tab-badge">' . $admin_unread . '</span>';
                    }
                    ?>
                </div>
                <div class="sidebar-tab" onclick="switchTab('users')" id="tabUsers">
                    👥 Users
                    <?php
                    $user_unread = 0;
                    foreach ($user_convos as $uc) {
                        $user_unread += $uc['unread_count'];
                    }
                    if ($user_unread > 0) {
                        echo '<span class="tab-badge">' . $user_unread . '</span>';
                    }
                    ?>
                </div>
            </div>

            <div class="convo-list">

                <!-- ALL Tab -->
                <div class="convo-tab-content active" id="contentAll">
                    <?php
                    $all_convos = array_merge($admin_convos, $user_convos);
                    // Sort by last message time
                    usort($all_convos, function ($a, $b) {
                        return strtotime($b['last_message_time'] ?? '0') - strtotime($a['last_message_time'] ?? '0');
                    });

                    if (count($all_convos) > 0) {
                        foreach ($all_convos as $convo) {
                            echo renderConvoItem($convo, $active_convo_id, $user_id);
                        }
                    } else { ?>
                        <div class="no-convos">
                            <span>💬</span>
                            <p>No conversations yet</p>
                        </div>
                    <?php } ?>
                </div>

                <!-- ADMIN Tab -->
                <div class="convo-tab-content" id="contentAdmin">
                    <?php if (count($admin_convos) > 0) {
                        foreach ($admin_convos as $convo) {
                            echo renderConvoItem($convo, $active_convo_id, $user_id);
                        }
                    } else { ?>
                        <div class="no-convos">
                            <span>🛡️</span>
                            <p>No admin chats yet</p>
                            <p style="font-size: 11px; margin-top: 5px; color: #bbb;">Admin will contact you when they
                                update your item status</p>
                        </div>
                    <?php } ?>
                </div>

                <!-- USERS Tab -->
                <div class="convo-tab-content" id="contentUsers">
                    <?php if (count($user_convos) > 0) {
                        foreach ($user_convos as $convo) {
                            echo renderConvoItem($convo, $active_convo_id, $user_id);
                        }
                    } else { ?>
                        <div class="no-convos">
                            <span>👥</span>
                            <p>No user chats yet</p>
                            <p style="font-size: 11px; margin-top: 5px; color: #bbb;">Chat with item reporters from Search
                                Items</p>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
            <?php if ($active_convo) { ?>

                <div class="chat-header">
                    <div class="chat-header-avatar <?php echo ($active_convo['other_role'] == 'admin') ? 'admin' : ''; ?>">
                        <?php if (!empty($active_convo['other_photo'])) { ?>
                            <img src="uploads/profile_photos/<?php echo $active_convo['other_photo']; ?>" alt="">
                        <?php } else { ?>
                            <?php echo strtoupper(substr($active_convo['other_username'], 0, 1)); ?>
                        <?php } ?>
                    </div>
                    <div class="chat-header-info">
                        <h3>
                            <?php echo htmlspecialchars($active_convo['other_username']); ?>
                            <?php if ($active_convo['other_role'] == 'admin') { ?>
                                <span class="badge admin-badge">🛡️ Admin</span>
                            <?php } ?>
                        </h3>
                        <div class="header-badges">
                            <?php if (!empty($active_convo['item_name'])) { ?>
                                <span class="header-tag">📦 <?php echo htmlspecialchars($active_convo['item_name']); ?></span>
                                <?php if ($active_convo['item_type'] == 'lost') { ?>
                                    <span class="badge lost-badge">Lost</span>
                                <?php } else { ?>
                                    <span class="badge found-badge">Found</span>
                                <?php } ?>
                            <?php } else if ($active_convo['type'] == 'user_admin') { ?>
                                    <span class="header-tag">🛠️ Support / Status Update</span>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="messages-area" id="messagesArea">
                    <!-- Loaded via AJAX -->
                </div>

                <div class="chat-input">
                    <input type="text" id="messageInput" placeholder="Type a message..." autocomplete="off"
                        onkeypress="if(event.key==='Enter')sendMessage()">
                    <button class="send-btn" onclick="sendMessage()">➤</button>
                </div>

            <?php } else { ?>
                <div class="no-chat-selected">
                    <span>💬</span>
                    <h3>Select a conversation</h3>
                    <p>Admin chats appear when admin contacts you</p>
                    <p style="font-size: 13px; margin-top: 5px;">Click "+ New" to chat about an item</p>
                </div>
            <?php } ?>
        </div>

    </div>

    <!-- New Chat Modal (Only Item Chat) -->
    <div class="modal" id="newChatModal">
        <div class="modal-content">
            <h3>💬 Start New Chat</h3>
            <p class="modal-subtitle">Chat with an item reporter</p>

            <div class="modal-info">
                ℹ️ <strong>Admin chats</strong> are created automatically when an admin updates your item status or
                contacts you.
            </div>

            <a href="search_items.php" class="modal-option">🔍 Search Items to Chat with Reporter</a>

            <button class="modal-close" onclick="closeNewChatModal()">✖ Cancel</button>
        </div>
    </div>

    <script>
        var convoId = <?php echo $active_convo_id; ?>;
        var userId = <?php echo $user_id; ?>;
    </script>
    <script src="msg-script.js"></script>

    <?php mysqli_close($conn); ?>
</body>

</html>

<?php
// ========================
// HELPER FUNCTION - Render conversation item
// ========================
function renderConvoItem($convo, $active_convo_id, $user_id)
{
    $is_active = ($convo['id'] == $active_convo_id) ? 'active' : '';
    $is_admin = ($convo['other_role'] == 'admin');
    $first_letter = strtoupper(substr($convo['other_username'], 0, 1));

    $msg_time = '';
    if (!empty($convo['last_message_time'])) {
        $today = date('Y-m-d');
        $msg_date = date('Y-m-d', strtotime($convo['last_message_time']));
        if ($today == $msg_date) {
            $msg_time = date('h:i A', strtotime($convo['last_message_time']));
        } else {
            $msg_time = date('d M', strtotime($convo['last_message_time']));
        }
    }

    $html = '<a href="messages.php?convo=' . $convo['id'] . '" class="convo-item ' . $is_active . '" data-name="' . strtolower($convo['other_username']) . '">';

    // Avatar
    $html .= '<div class="convo-avatar ' . ($is_admin ? 'admin' : '') . '">';
    if (!empty($convo['other_photo'])) {
        $html .= '<img src="uploads/profile_photos/' . htmlspecialchars($convo['other_photo']) . '" alt="">';
    } else {
        $html .= $first_letter;
    }
    $html .= '</div>';

    // Info
    $html .= '<div class="convo-info">';
    $html .= '<div class="name">' . htmlspecialchars($convo['other_username']);
    if ($is_admin) {
        $html .= ' <span class="badge admin-badge">🛡️ Admin</span>';
    }
    $html .= '</div>';

    if (!empty($convo['item_name'])) {
        $html .= '<div class="item-label">📦 ' . htmlspecialchars($convo['item_name']);
        if (isset($convo['item_type']) && $convo['item_type'] == 'lost') {
            $html .= ' <span class="badge lost-badge">Lost</span>';
        } else if (isset($convo['item_type'])) {
            $html .= ' <span class="badge found-badge">Found</span>';
        }
        $html .= '</div>';
    }

    $last_msg = !empty($convo['last_message']) ? htmlspecialchars($convo['last_message']) : 'No messages yet';
    $html .= '<div class="last-msg">' . $last_msg . '</div>';
    $html .= '</div>';

    // Meta
    $html .= '<div class="convo-meta">';
    $html .= '<div class="time">' . $msg_time . '</div>';
    if ($convo['unread_count'] > 0) {
        $html .= '<span class="unread-badge">' . $convo['unread_count'] . '</span>';
    }
    $html .= '</div>';
    $html .= '<div class="convo-actions" onclick="event.preventDefault(); event.stopPropagation();">';
    $html .= '<button class="convo-dots" onclick="toggleConvoMenu(event,' . $convo['id'] . ')">⋮</button>';
    $html .= '<div class="convo-actions-menu" id="cmenu-' . $convo['id'] . '" onclick="event.stopPropagation()">';
    $html .= '<button class="convo-action-btn" onclick="chatAction(\'clear\',' . $convo['id'] . ')">🧹 Clear Chat</button>';
    $html .= '<button class="convo-action-btn danger" onclick="chatAction(\'delete\',' . $convo['id'] . ')">🗑️ Delete Chat</button>';
    $html .= '</div></div>';
    $html .= '</a>';

    return $html;
}
?>
