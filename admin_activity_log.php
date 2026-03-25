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

$admin_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE username = '$username'"));
if (!$admin_data) { session_destroy(); header("Location: index.php"); exit(); }
$admin_id = $admin_data['id'];
$has_photo = !empty($admin_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $admin_data['profile_photo'] : '';

// Filters
$filter_action = isset($_GET['action_type']) ? mysqli_real_escape_string($conn, $_GET['action_type']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where_parts = [];
if ($filter_action)
    $where_parts[] = "action = '$filter_action'";
if ($search)
    $where_parts[] = "(admin_name LIKE '%$search%' OR details LIKE '%$search%' OR action LIKE '%$search%')";
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Pagination
$per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM activity_log $where"))['c'];
$total_pages = max(1, ceil($total / $per_page));
if ($page > $total_pages)
    $page = $total_pages;
$offset = ($page - 1) * $per_page;

$logs = mysqli_query($conn, "SELECT * FROM activity_log $where ORDER BY created_at DESC LIMIT $offset, $per_page");

// Distinct action types for filter dropdown
$action_types_res = mysqli_query($conn, "SELECT DISTINCT action FROM activity_log ORDER BY action");

// Unread chat
$unread_count = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) as c FROM messages m JOIN conversations cv ON m.conversation_id=cv.id
     WHERE (cv.user1_id='$admin_id' OR cv.user2_id='$admin_id') AND m.sender_id!='$admin_id' AND m.is_read=0"
))['c'] ?? 0;
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
    <title>Activity Log - Campus Find Admin</title>
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
            text-decoration: none;
        }

        .nav-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 24px;
            color: #1a1a2e;
            margin-bottom: 20px;
        }

        .filters-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #ddd;
            padding: 18px 20px;
            margin-bottom: 20px;
        }

        .filters-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .filters-row input,
        .filters-row select {
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
        }

        .filters-row input {
            flex: 1;
            min-width: 200px;
        }

        .btn {
            padding: 9px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }

        .btn-dark {
            background: #1a1a2e;
            color: white;
        }

        .btn-dark:hover {
            background: #0f3460;
        }

        .btn-gray {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        .btn-gray:hover {
            background: #545b62;
        }

        .table-wrap {
            background: white;
            border-radius: 15px;
            border: 1px solid #ddd;
            overflow: hidden;
            margin-bottom: 20px;
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
            font-weight: 600;
        }

        td {
            padding: 12px 15px;
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

        .action-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-approve {
            background: #d4edda;
            color: #155724;
        }

        .badge-reject {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-delete {
            background: #fff3cd;
            color: #856404;
        }

        .badge-update {
            background: #cce5ff;
            color: #004085;
        }

        .badge-login {
            background: #e2d9f3;
            color: #4a235a;
        }

        .badge-other {
            background: #e9ecef;
            color: #555;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: #aaa;
        }

        .empty-state span {
            font-size: 60px;
            display: block;
            margin-bottom: 15px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
        }

        .pagination a,
        .pagination span {
            padding: 7px 13px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #ddd;
        }

        .pagination a {
            background: white;
            color: #333;
        }

        .pagination a:hover {
            background: #f0f2f5;
            border-color: #1a1a2e;
            color: #1a1a2e;
        }

        .pagination span.active {
            background: #1a1a2e;
            color: white;
            border-color: #1a1a2e;
        }

        .pagination span.disabled {
            background: #f0f0f0;
            color: #ccc;
        }

        .summary-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .summary-pill {
            background: white;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 13px;
            color: #555;
        }

        .summary-pill strong {
            color: #1a1a2e;
        }
    </style>
</head>

<body>
    <div class="navbar">
        <div class="nav-left">
            <a href="admin_dashboard.php" class="back-btn">← Dashboard</a>
            <h1>Campus-Find <span class="admin-badge">ADMIN</span></h1>
        </div>
        <div class="nav-right">
            <a href="admin_messages.php" class="chat-nav-icon">💬
                <?php if ($unread_count > 0): ?>
                    <span class="chat-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_profile.php" class="nav-avatar">
                <?php if ($has_photo): ?>
                    <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="container">
        <h2 class="page-title">Admin Activity Log</h2>

        <div class="summary-bar">
            <div class="summary-pill">📋 Total entries: <strong><?php echo $total; ?></strong></div>
            <div class="summary-pill">📄 Page: <strong><?php echo $page; ?> / <?php echo $total_pages; ?></strong></div>
        </div>

        <div class="filters-card">
            <form method="GET">
                <div class="filters-row">
                    <input type="text" name="search" placeholder="🔍 Search by admin, action, details..."
                        value="<?php echo htmlspecialchars($search); ?>">
                    <select name="action_type">
                        <option value="">All Actions</option>
                        <?php while ($at = mysqli_fetch_assoc($action_types_res)): ?>
                            <option value="<?php echo htmlspecialchars($at['action']); ?>" <?php echo ($filter_action === $at['action']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($at['action']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn btn-dark">🔍 Filter</button>
                    <a href="admin_activity_log.php" class="btn btn-gray">✕ Clear</a>
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <?php if (mysqli_num_rows($logs) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = $offset + 1;
                        while ($log = mysqli_fetch_assoc($logs)): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><strong><?php echo htmlspecialchars($log['admin_name']); ?></strong></td>
                                <td>
                                    <?php
                                    $action = $log['action'];
                                    $badge_class = 'badge-other';
                                    if (stripos($action, 'approve') !== false)
                                        $badge_class = 'badge-approve';
                                    elseif (stripos($action, 'reject') !== false)
                                        $badge_class = 'badge-reject';
                                    elseif (stripos($action, 'delete') !== false)
                                        $badge_class = 'badge-delete';
                                    elseif (stripos($action, 'update') !== false || stripos($action, 'edit') !== false)
                                        $badge_class = 'badge-update';
                                    elseif (stripos($action, 'login') !== false)
                                        $badge_class = 'badge-login';
                                    ?>
                                    <span
                                        class="action-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($action); ?></span>
                                </td>
                                <td style="max-width:350px; color:#555;"><?php echo htmlspecialchars($log['details'] ?? '—'); ?>
                                </td>
                                <td style="white-space:nowrap; color:#666; font-size:12px;">
                                    <?php echo date('d M Y', strtotime($log['created_at'])); ?><br>
                                    <span style="color:#aaa;"><?php echo date('h:i A', strtotime($log['created_at'])); ?></span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <span>🗂️</span>
                    <p>No activity logged yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $qs = ($search ? '&search=' . urlencode($search) : '') . ($filter_action ? '&action_type=' . urlencode($filter_action) : '');
                echo $page > 1 ? "<a href='?page=" . ($page - 1) . $qs . "'>← Prev</a>" : "<span class='disabled'>← Prev</span>";
                for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                    echo $i == $page ? "<span class='active'>$i</span>" : "<a href='?page=$i$qs'>$i</a>";
                }
                echo $page < $total_pages ? "<a href='?page=" . ($page + 1) . $qs . "'>Next →</a>" : "<span class='disabled'>Next →</span>";
                ?>
            </div>
        <?php endif; ?>
    </div>

    <?php mysqli_close($conn); ?>
</body>

</html>