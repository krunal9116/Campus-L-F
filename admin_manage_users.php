<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/config.php';

$admin_query = "SELECT * FROM users WHERE username = '$username'";
$admin_result = mysqli_query($conn, $admin_query);
$admin_data = mysqli_fetch_assoc($admin_result);
if (!$admin_data) { session_destroy(); header("Location: index.php"); exit(); }
$admin_id = $admin_data['id'];
$has_photo = !empty($admin_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $admin_data['profile_photo'] : '';

$success_msg = "";
$error_msg = "";

// ── DELETE USER ───────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);

    // Cannot delete yourself
    if ($del_id == $admin_id) {
        $error_msg = "You cannot delete your own account.";
    } else {
        $user_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $del_id AND role = 'user'"));
        if ($user_row) {
            // Delete profile photo file
            if (!empty($user_row['profile_photo'])) {
                $pp = 'uploads/profile_photos/' . $user_row['profile_photo'];
                if (file_exists($pp))
                    unlink($pp);
            }
            // Delete user's item images
            $user_items = mysqli_query($conn, "SELECT image FROM items WHERE user_id = $del_id");
            while ($it = mysqli_fetch_assoc($user_items)) {
                if (!empty($it['image']) && file_exists('uploads/' . $it['image'])) {
                    unlink('uploads/' . $it['image']);
                }
            }
            // Cascade delete inside a transaction so a partial failure rolls back.
            // FK checks are disabled for the duration so no constraint order issues arise.
            mysqli_begin_transaction($conn);
            $del_ok = true;
            $del_ok = $del_ok && mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
            // claims made by this user
            $del_ok = $del_ok && mysqli_query($conn, "DELETE FROM claims WHERE user_id = $del_id");
            // claims on items reported by this user
            $del_ok = $del_ok && mysqli_query($conn, "DELETE FROM claims WHERE item_id IN (SELECT id FROM items WHERE user_id = $del_id)");
            // items reported by this user
            $del_ok = $del_ok && mysqli_query($conn, "DELETE FROM items WHERE user_id = $del_id");
            // messages in conversations this user was part of
            $del_ok = $del_ok && mysqli_query($conn, "DELETE FROM messages WHERE conversation_id IN (SELECT id FROM conversations WHERE user1_id = $del_id OR user2_id = $del_id)");
            // messages sent directly by this user (may be in other conversations)
            $del_ok = $del_ok && mysqli_query($conn, "DELETE FROM messages WHERE sender_id = $del_id");
            // conversations this user participated in
            $del_ok = $del_ok && mysqli_query($conn, "DELETE FROM conversations WHERE user1_id = $del_id OR user2_id = $del_id");
            // finally delete the user record
            $del_ok = $del_ok && mysqli_query($conn, "DELETE FROM users WHERE id = $del_id");
            $del_ok = $del_ok && mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
            if ($del_ok) {
                mysqli_commit($conn);
                $success_msg = "User deleted successfully.";
            } else {
                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1"); // restore even on failure
                mysqli_rollback($conn);
                $error_msg = "Failed to delete user. Please try again.";
            }
        } else {
            $error_msg = "User not found.";
        }
    }
}

// ── SEARCH & PAGINATION ───────────────────────────────────
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$per_page = 15;
$page = max(1, intval($_GET['page'] ?? 1));

$where = "WHERE u.role = 'user'";
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (u.username LIKE '%$s%' OR u.email LIKE '%$s%')";
}

$total_rows = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users u $where"))['c'];
$total_pages = max(1, ceil($total_rows / $per_page));
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $per_page;

$users_query = "SELECT u.*,
                    (SELECT COUNT(*) FROM items WHERE user_id = u.id) AS total_reports,
                    (SELECT COUNT(*) FROM items WHERE user_id = u.id AND report_type = 'lost') AS lost_count,
                    (SELECT COUNT(*) FROM items WHERE user_id = u.id AND report_type = 'found') AS found_count,
                    (SELECT COUNT(*) FROM claims WHERE user_id = u.id) AS claim_count,
                    (SELECT COUNT(*) FROM claims WHERE user_id = u.id AND status = 'approved') AS approved_claims
                FROM users u
                $where
                ORDER BY u.id DESC
                LIMIT $offset, $per_page";
$users_result = mysqli_query($conn, $users_query);

$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role='user'"))['c'];

// Unread chat count
$unread_q = "SELECT COUNT(*) as count FROM messages m 
                 JOIN conversations c ON m.conversation_id = c.id 
                 WHERE (c.user1_id='$admin_id' OR c.user2_id='$admin_id') 
                 AND m.sender_id != '$admin_id' AND m.is_read=0";
$unread_count = mysqli_fetch_assoc(mysqli_query($conn, $unread_q))['count'] ?? 0;
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
    <script src="page-loader.js"></script>
    <title>Manage Users - Campus Find Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f0f2f5;
        }

        /* NAVBAR */
        .navbar {
            background: #1a1a2e;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .navbar h1 {
            font-size: 22px;
        }

        .admin-badge {
            background: #e74c3c;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            vertical-align: middle;
        }

        .back-btn {
            background: white;
            color: #1a1a2e;
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #dde3f0;
        }

        .chat-nav-icon {
            position: relative;
            font-size: 24px;
            text-decoration: none;
        }

        .chat-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
        }

        .nav-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: white;
            color: #1a1a2e;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
        }

        .nav-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* CONTAINER */
        .container {
            padding: 30px;
            max-width: 1300px;
            margin: 0 auto;
        }

        /* ALERTS */
        .alert {
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* STATS ROW */
        .stats-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px 25px;
            border: 1px solid #e0e0e0;
            flex: 1;
            min-width: 140px;
            text-align: center;
        }

        .stat-box .num {
            font-size: 30px;
            font-weight: 700;
            color: #1a1a2e;
        }

        .stat-box .lbl {
            font-size: 13px;
            color: #888;
            margin-top: 4px;
        }

        /* SEARCH */
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-bar input {
            flex: 1;
            min-width: 200px;
            padding: 10px 16px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }

        .search-bar input:focus {
            border-color: #1a1a2e;
        }

        .search-bar button {
            padding: 10px 22px;
            background: #1a1a2e;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .search-bar button:hover {
            opacity: 0.85;
        }

        .search-bar a {
            padding: 10px 18px;
            background: white;
            color: #555;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            text-decoration: none;
        }

        /* TABLE */
        .table-wrap {
            background: white;
            border-radius: 15px;
            border: 1px solid #e0e0e0;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #1a1a2e;
            color: white;
        }

        th {
            padding: 13px 16px;
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
        }

        td {
            padding: 13px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f9fafb;
        }

        /* Avatar */
        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #1a1a2e;
            color: white;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-name {
            font-weight: 600;
            color: #1a1a2e;
        }

        .user-email {
            font-size: 12px;
            color: #888;
        }

        /* Mini stat badges */
        .mini-stat {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            margin: 2px;
        }

        .ms-reports {
            background: #e0e7ff;
            color: #3730a3;
        }

        .ms-claims {
            background: #fef9c3;
            color: #854d0e;
        }

        .ms-approved {
            background: #dcfce7;
            color: #166534;
        }

        /* Chat button */
        .btn-chat {
            padding: 6px 14px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .btn-chat:hover {
            opacity: 0.85;
        }

        /* Delete button */
        .btn-delete {
            padding: 6px 14px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-left: 5px;
        }

        .btn-delete:hover {
            opacity: 0.85;
        }

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px 0;
            flex-wrap: wrap;
        }

        .page-btn {
            padding: 7px 14px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: #555;
        }

        .page-btn:hover {
            border-color: #1a1a2e;
            color: #1a1a2e;
        }

        .page-btn.active {
            background: #1a1a2e;
            color: white;
            border-color: #1a1a2e;
        }

        .page-btn.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        /* EMPTY */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }

        .empty-state span {
            font-size: 60px;
            display: block;
            margin-bottom: 15px;
        }

        /* Modal overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal h3 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #1a1a2e;
        }

        .modal .user-detail-row {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .modal .detail-lbl {
            width: 130px;
            font-weight: 600;
            color: #555;
        }

        .modal .detail-val {
            flex: 1;
            color: #333;
        }

        .modal .close-btn {
            margin-top: 20px;
            padding: 10px 24px;
            background: #1a1a2e;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        .modal .close-btn:hover {
            opacity: 0.85;
        }

        @media(max-width:768px) {
            .container {
                padding: 15px;
            }

            th,
            td {
                padding: 10px 10px;
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="nav-left">
            <a href="admin_dashboard.php" class="back-btn">← Dashboard</a>
            <h1>Campus-Find <span class="admin-badge">ADMIN</span></h1>
        </div>
        <div class="nav-right">
            <a href="admin_messages.php" class="chat-nav-icon" title="Chat">
                💬
                <?php if ($unread_count > 0): ?>
                    <span class="chat-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_profile.php" class="nav-avatar" title="My Profile">
                <?php if ($has_photo): ?>
                    <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="container">

        <div
            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
            <h2 style="font-size:24px; color:#1a1a2e;">👥 Manage Users</h2>
            <span style="color:#888; font-size:13px;">
                Showing <?php echo mysqli_num_rows($users_result); ?> of <?php echo $total_rows; ?> user(s)
            </span>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- STATS ROW -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="num">👥 <?php echo $total_users; ?></div>
                <div class="lbl">Total Users</div>
            </div>
            <div class="stat-box">
                <div class="num">📦
                    <?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items"))['c']; ?>
                </div>
                <div class="lbl">Total Reports</div>
            </div>
            <div class="stat-box">
                <div class="num">✋
                    <?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims"))['c']; ?>
                </div>
                <div class="lbl">Total Claims</div>
            </div>
            <div class="stat-box">
                <div class="num">🏆
                    <?php echo mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status='approved'"))['c']; ?>
                </div>
                <div class="lbl">Approved Claims</div>
            </div>
        </div>

        <!-- SEARCH -->
        <form method="GET" class="search-bar">
            <input type="text" name="search" placeholder="🔍 Search by username or email..."
                value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
            <?php if ($search): ?>
                <a href="admin_manage_users.php">✕ Clear</a>
            <?php endif; ?>
        </form>

        <!-- TABLE -->
        <div class="table-wrap">
            <?php if (mysqli_num_rows($users_result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Activity</th>
                            <th>Details</th>
                            <th>Chat</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = ($page - 1) * $per_page + 1;
                        while ($row = mysqli_fetch_assoc($users_result)):
                            $pp = !empty($row['profile_photo']) ? 'uploads/profile_photos/' . $row['profile_photo'] : '';
                            $initial = strtoupper(substr($row['username'], 0, 1));
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>

                                <!-- Avatar + name -->
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php if ($pp && file_exists($pp)): ?>
                                                <img src="<?php echo $pp; ?>" alt="avatar">
                                            <?php else: ?>
                                                <?php echo $initial; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="user-name"><?php echo htmlspecialchars($row['username']); ?></div>
                                            <div style="font-size:11px; color:#aaa;">ID: <?php echo $row['id']; ?></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Email -->
                                <td><?php echo htmlspecialchars($row['email']); ?></td>

                                <!-- Activity mini badges -->
                                <td>
                                    <span class="mini-stat ms-reports">📦 <?php echo $row['total_reports']; ?> Reports</span>
                                    <span class="mini-stat ms-claims">✋ <?php echo $row['claim_count']; ?> Claims</span>
                                    <?php if ($row['approved_claims'] > 0): ?>
                                        <span class="mini-stat ms-approved">🏆 <?php echo $row['approved_claims']; ?>
                                            Approved</span>
                                    <?php endif; ?>
                                </td>

                                <!-- View details -->
                                <td>
                                    <button class="btn-chat" style="background:#6f42c1;" onclick="showDetails(
                            <?php echo $row['id']; ?>,
                            '<?php echo htmlspecialchars(addslashes($row['username'])); ?>',
                            '<?php echo htmlspecialchars(addslashes($row['email'])); ?>',
                            <?php echo $row['total_reports']; ?>,
                            <?php echo $row['lost_count']; ?>,
                            <?php echo $row['found_count']; ?>,
                            <?php echo $row['claim_count']; ?>,
                            <?php echo $row['approved_claims']; ?>
                        )">
                                        🔍 View
                                    </button>
                                </td>

                                <!-- Chat with user -->
                                <td>
                                    <a href="admin_open_convo.php?user_id=<?php echo $row['id']; ?>" class="btn-chat">💬 Chat</a>
                                </td>

                                <!-- Delete -->
                                <td>
                                    <a href="?delete_id=<?php echo $row['id']; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>"
                                        class="btn-delete"
                                        onclick="return confirm('Delete user \'<?php echo htmlspecialchars(addslashes($row['username'])); ?>\'?\n\nThis will permanently delete their account, reports, claims, and messages.')">
                                        🗑 Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <span>👤</span>
                    <p>No users found<?php echo $search ? " for \"" . htmlspecialchars($search) . "\"" : ''; ?>.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>"
                    class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">← Prev</a>
                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                    <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"
                        class="page-btn <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
                <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>"
                    class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next →</a>
            </div>
        <?php endif; ?>

    </div>

    <!-- USER DETAIL MODAL -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal">
            <h3>👤 User Details</h3>
            <div class="user-detail-row"><span class="detail-lbl">User ID</span><span class="detail-val"
                    id="m_id">—</span></div>
            <div class="user-detail-row"><span class="detail-lbl">Username</span><span class="detail-val"
                    id="m_user">—</span></div>
            <div class="user-detail-row"><span class="detail-lbl">Email</span><span class="detail-val"
                    id="m_email">—</span></div>
            <div class="user-detail-row"><span class="detail-lbl">Total Reports</span><span class="detail-val"
                    id="m_reports">—</span></div>
            <div class="user-detail-row"><span class="detail-lbl">Lost Reports</span><span class="detail-val"
                    id="m_lost">—</span></div>
            <div class="user-detail-row"><span class="detail-lbl">Found Reports</span><span class="detail-val"
                    id="m_found">—</span></div>
            <div class="user-detail-row"><span class="detail-lbl">Total Claims</span><span class="detail-val"
                    id="m_claims">—</span></div>
            <div class="user-detail-row"><span class="detail-lbl">Approved Claims</span><span class="detail-val"
                    id="m_approved">—</span></div>
            <button class="close-btn" onclick="closeModal()">Close</button>
        </div>
    </div>

    <script>
        function showDetails(id, user, email, reports, lost, found, claims, approved) {
            document.getElementById('m_id').textContent = id;
            document.getElementById('m_user').textContent = user;
            document.getElementById('m_email').textContent = email;
            document.getElementById('m_reports').textContent = reports;
            document.getElementById('m_lost').textContent = lost;
            document.getElementById('m_found').textContent = found;
            document.getElementById('m_claims').textContent = claims;
            document.getElementById('m_approved').textContent = approved;
            document.getElementById('detailModal').classList.add('open');
        }
        function closeModal() {
            document.getElementById('detailModal').classList.remove('open');
        }
        document.getElementById('detailModal').addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
    </script>

    <?php mysqli_close($conn); ?>
</body>

</html>