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

// ── DELETE ITEM ──────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $item_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM items WHERE id = $del_id"));
    if ($item_row) {
        require_once 'msg_ajax/log_activity.php';
        mysqli_query($conn, "DELETE FROM claims WHERE item_id = $del_id");
        // Remove image file
        if (!empty($item_row['image']) && file_exists('uploads/' . $item_row['image'])) {
            unlink('uploads/' . $item_row['image']);
        }
        mysqli_query($conn, "DELETE FROM items WHERE id = $del_id");
        logActivity($conn, $admin_id, $username, 'Delete Item', "Deleted item \"{$item_row['item_name']}\" (ID: $del_id)");
        $success_msg = "Item deleted successfully.";
    }
}

// ── UPDATE STATUS ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $upd_id = intval($_POST['item_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['report_type']);
    $allowed_types = ['lost', 'found', 'received'];
    if (in_array($new_status, $allowed_types)) {
        // Check old status before updating
        $old_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT report_type, item_name FROM items WHERE id = $upd_id"));
        $old_status = $old_row ? $old_row['report_type'] : '';
        $item_name = $old_row ? $old_row['item_name'] : '';

        mysqli_query($conn, "UPDATE items SET report_type = '$new_status' WHERE id = $upd_id");

        // If status changed FROM 'received' TO 'found' — notify approved claimant, delete their claim
        if ($old_status === 'received' && $new_status === 'found') {
            require_once 'msg_ajax/create_admin_msg.php';
            $approved_claim = mysqli_fetch_assoc(mysqli_query(
                $conn,
                "SELECT cl.id, cl.user_id FROM claims cl WHERE cl.item_id = $upd_id AND cl.status = 'approved' LIMIT 1"
            ));
            if ($approved_claim) {
                $notify_msg = "ℹ️ The item \"$item_name\" you claimed has had its status updated back to Found by the admin. Your claim has been removed and you may claim it again if you wish.";
                createAdminChat($conn, $admin_id, $approved_claim['user_id'], $upd_id, $notify_msg);
                // Delete all claims for this item so users can re-claim
                mysqli_query($conn, "DELETE FROM claims WHERE item_id = $upd_id");
            }
        }

        require_once 'msg_ajax/log_activity.php';
        logActivity($conn, $admin_id, $username, 'Update Item Status',"Changed \"$item_name\" (ID: $upd_id) status from $old_status to $new_status");
        $success_msg = "Item status updated.";
    }
}

// ── FILTERS & SEARCH ─────────────────────────────────────
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$allowed_filters = ['all', 'lost', 'found', 'received'];
if (!in_array($filter, $allowed_filters))
    $filter = 'all';

$conditions = [];
if ($filter !== 'all')
    $conditions[] = "i.report_type = '$filter'";
if ($search !== '')
    $conditions[] = "(i.item_name LIKE '%$search%' OR i.location LIKE '%$search%' OR i.category LIKE '%$search%' OR u.username LIKE '%$search%')";
$where = count($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Pagination
$per_page = 15;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$total_rows = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) as c FROM items i LEFT JOIN users u ON i.user_id = u.id $where"
))['c'];
$total_pages = max(1, ceil($total_rows / $per_page));
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $per_page;

$items_query = "SELECT i.*, u.username AS reporter,
                       (SELECT COUNT(*) FROM claims WHERE item_id = i.id) AS claim_count
                FROM items i
                LEFT JOIN users u ON i.user_id = u.id
                $where
                ORDER BY i.date_reported DESC
                LIMIT $offset, $per_page";
$items_result = mysqli_query($conn, $items_query);

// Tab counts
$c_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items"))['c'];
$c_lost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type='lost'"))['c'];
$c_found = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type='found'"))['c'];
$c_received = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type='received'"))['c'];

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
    <title>Manage Items - Campus Find Admin</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .page-header h2 {
            font-size: 24px;
            color: #1a1a2e;
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

        /* SEARCH BAR */
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

        .search-bar a:hover {
            border-color: #aaa;
        }

        /* FILTER TABS */
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 9px 20px;
            border-radius: 25px;
            border: 2px solid #ddd;
            background: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: #555;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            border-color: #1a1a2e;
            color: #1a1a2e;
        }

        .tab-btn.active {
            background: #1a1a2e;
            color: white;
            border-color: #1a1a2e;
        }

        .tab-btn.lost-tab.active {
            background: #dc2626;
            border-color: #dc2626;
        }

        .tab-btn.found-tab.active {
            background: #2563eb;
            border-color: #2563eb;
        }

        .tab-btn.recv-tab.active {
            background: #15803d;
            border-color: #15803d;
        }

        .tab-count {
            background: rgba(255, 255, 255, 0.25);
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 11px;
            margin-left: 4px;
        }

        .tab-btn:not(.active) .tab-count {
            background: #eee;
            color: #444;
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
            padding: 13px 15px;
            text-align: left;
            font-size: 13px;
            white-space: nowrap;
        }

        td {
            padding: 13px 15px;
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

        .item-img {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #ddd;
        }

        .item-img-ph {
            width: 52px;
            height: 52px;
            border-radius: 8px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: #aaa;
        }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge-lost {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }

        .badge-found {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #2563eb;
        }

        .badge-received {
            background: #d4edda;
            color: #155724;
            border: 1px solid #28a745;
        }

        .claim-badge {
            background: #fff3cd;
            color: #856404;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 4px;
        }

        /* STATUS DROPDOWN */
        .status-form {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-select {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 12px;
            background: white;
            cursor: pointer;
        }

        .btn-update {
            padding: 5px 12px;
            background: #1a1a2e;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-update:hover {
            opacity: 0.85;
        }

        /* DELETE */
        .btn-delete {
            padding: 6px 12px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
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

        <div class="page-header">
            <h2>📋 Manage Items</h2>
            <span style="color:#888; font-size:13px;">
                Showing <?php echo mysqli_num_rows($items_result); ?> of <?php echo $total_rows; ?> item(s)
            </span>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- SEARCH -->
        <form method="GET" class="search-bar">
            <input type="hidden" name="filter" value="<?php echo $filter; ?>">
            <input type="text" name="search" placeholder="🔍 Search by item name, category, location, reporter..."
                value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">Search</button>
            <?php if ($search): ?>
                <a href="?filter=<?php echo $filter; ?>">✕ Clear</a>
            <?php endif; ?>
        </form>

        <!-- FILTER TABS -->
        <div class="filter-tabs">
            <a href="?filter=all&search=<?php echo urlencode($search); ?>"
                class="tab-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                📦 All <span class="tab-count"><?php echo $c_all; ?></span>
            </a>
            <a href="?filter=lost&search=<?php echo urlencode($search); ?>"
                class="tab-btn lost-tab <?php echo $filter === 'lost' ? 'active' : ''; ?>">
                🔴 Lost <span class="tab-count"><?php echo $c_lost; ?></span>
            </a>
            <a href="?filter=found&search=<?php echo urlencode($search); ?>"
                class="tab-btn found-tab <?php echo $filter === 'found' ? 'active' : ''; ?>">
                🔵 Found <span class="tab-count"><?php echo $c_found; ?></span>
            </a>
            <a href="?filter=received&search=<?php echo urlencode($search); ?>"
                class="tab-btn recv-tab <?php echo $filter === 'received' ? 'active' : ''; ?>">
                🟢 Received <span class="tab-count"><?php echo $c_received; ?></span>
            </a>
        </div>

        <!-- TABLE -->
        <div class="table-wrap">
            <?php if (mysqli_num_rows($items_result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Image</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Reporter</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Change Status</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = ($page - 1) * $per_page + 1;
                        while ($row = mysqli_fetch_assoc($items_result)):
                            $type = strtolower($row['report_type']);
                            $img_path = !empty($row['image']) ? 'uploads/' . $row['image'] : '';
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>

                                <!-- Image -->
                                <td>
                                    <?php if ($img_path && file_exists($img_path)): ?>
                                        <img src="<?php echo $img_path; ?>" class="item-img" alt="Item"
                                            onclick="zoomImage(this.src)" style="cursor:zoom-in;">
                                    <?php else: ?>
                                        <div class="item-img-ph">📦</div>
                                    <?php endif; ?>
                                </td>

                                <!-- Name -->
                                <td>
                                    <strong><?php echo htmlspecialchars($row['item_name']); ?></strong>
                                    <?php if ($row['claim_count'] > 0): ?>
                                        <span class="claim-badge">👥 <?php echo $row['claim_count']; ?> claim(s)</span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['description'])): ?>
                                        <br><span
                                            style="font-size:11px; color:#888;"><?php echo htmlspecialchars(mb_strimwidth($row['description'], 0, 60, '…')); ?></span>
                                    <?php endif; ?>
                                </td>

                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>

                                <!-- Reporter -->
                                <td>
                                    <span style="color:#6f42c1; font-weight:600;">
                                        👤 <?php echo htmlspecialchars($row['reporter'] ?? '—'); ?>
                                    </span>
                                </td>

                                <!-- Date -->
                                <td style="white-space:nowrap;">
                                    <?php echo date('d M Y', strtotime($row['date_reported'])); ?>
                                </td>

                                <!-- Current status badge -->
                                <td>
                                    <span class="badge badge-<?php echo $type; ?>">
                                        <?php
                                        if ($type === 'lost')
                                            echo '🔴 Lost';
                                        elseif ($type === 'found')
                                            echo '🔵 Found';
                                        elseif ($type === 'received')
                                            echo '🟢 Received';
                                        ?>
                                    </span>
                                </td>

                                <!-- Change status -->
                                <td>
                                    <form method="POST" class="status-form">
                                        <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                                        <select name="report_type" class="status-select">
                                            <option value="lost" <?php echo $type === 'lost' ? 'selected' : ''; ?>>🔴 Lost
                                            </option>
                                            <option value="found" <?php echo $type === 'found' ? 'selected' : ''; ?>>🔵 Found
                                            </option>
                                            <option value="received" <?php echo $type === 'received' ? 'selected' : ''; ?>>🟢
                                                Received</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn-update">Save</button>
                                    </form>
                                </td>

                                <!-- Delete -->
                                <td>
                                    <a href="?delete_id=<?php echo $row['id']; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page; ?>"
                                        class="btn-delete" onclick="return confirm('Delete this item? This cannot be undone.')">
                                        🗑 Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <span>📭</span>
                    <p>No items found<?php echo $search ? " for \"$search\"" : ''; ?>.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <a href="?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>"
                    class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">← Prev</a>

                <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                    <a href="?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"
                        class="page-btn <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>

                <a href="?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>"
                    class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next →</a>
            </div>
        <?php endif; ?>

    </div>

    <?php mysqli_close($conn); ?>
    <!-- Image Zoom Overlay -->
    <div id="zoomOverlay"
        style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;justify-content:center;align-items:center;cursor:zoom-out;"
        onclick="this.style.display='none'">
        <img id="zoomImg" src=""
            style="max-width:90%;max-height:90%;border-radius:10px;box-shadow:0 0 40px rgba(0,0,0,0.5);">
    </div>
    <script>
        function zoomImage(src) { var o = document.getElementById('zoomOverlay'); document.getElementById('zoomImg').src = src; o.style.display = 'flex'; }
    </script>
</body>

</html>