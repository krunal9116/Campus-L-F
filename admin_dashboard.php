<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
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

// Profile photo
$has_photo = !empty($admin_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $admin_data['profile_photo'] : '';

// Stats
$total_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items"))['c'];
$total_lost = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type = 'lost'"))['c'];
$total_found = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type = 'found'"))['c'];
$total_claimed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims"))['c'];
$total_received = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type = 'received'"))['c'];
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role = 'user'"))['c'];

// Unread chat count
$unread_q = "SELECT COUNT(*) as count FROM messages m 
            JOIN conversations c ON m.conversation_id = c.id 
            WHERE (c.user1_id = '$admin_id' OR c.user2_id = '$admin_id') 
            AND m.sender_id != '$admin_id' AND m.is_read = 0";
$unread_result = mysqli_query($conn, $unread_q);
$unread_count = $unread_result ? mysqli_fetch_assoc($unread_result)['count'] : 0;

// Pending claims count (use PHP time to match how countdown_end was stored)
$php_now = date('Y-m-d H:i:s');
$pending_claims = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE status = 'pending' AND countdown_end <= '$php_now'"))['c'];

// New reports in last 24 hours
$new_reports = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE date_reported >= NOW() - INTERVAL 1 DAY"))['c'];
$notif_count = $pending_claims + $new_reports;
$admin_notif_key = "p{$pending_claims}_r{$new_reports}";
$admin_stored_key = $admin_data['notif_seen_key'] ?? '';
$admin_notif_seen = ($admin_stored_key === $admin_notif_key);

// Recent items
$recent_query = "SELECT i.*, u.username as reporter FROM items i LEFT JOIN users u ON i.user_id = u.id ORDER BY i.date_reported DESC LIMIT 10";
$recent_result = mysqli_query($conn, $recent_query);

// Chart data: items reported per day (last 7 days)
$chart_labels = [];
$chart_lost = [];
$chart_found = [];
$chart_claims = [];
for ($d = 6; $d >= 0; $d--) {
    $date = date('Y-m-d', strtotime("-$d days"));
    $label = date('d M', strtotime("-$d days"));
    $chart_labels[] = $label;
    $chart_lost[] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type='lost' AND DATE(date_reported)='$date'"))['c'];
    $chart_found[] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM items WHERE report_type='found' AND DATE(date_reported)='$date'"))['c'];
    $chart_claims[] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM claims WHERE DATE(claim_date)='$date'"))['c'];
}
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
    <title>Admin Dashboard - Campus Find</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>
        .loading-screen {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #f0f2f5;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            z-index: 9999;
        }

        .loading-screen img {
            width: 300px;
            height: auto;
        }

        .loading-text {
            color: #159f35;
            margin-top: 20px;
            font-size: 18px;
            font-weight: 500;
        }

        .main-content {
            display: none;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f0f2f5;
        }

        /* ======================== */
        /* NAVBAR */
        /* ======================== */
        .navbar {
            background-color: #1a1a2e;
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
            font-size: 26px;
        }

        .admin-badge {
            background-color: #e74c3c;
            color: white;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 10px;
            vertical-align: middle;
        }

        /* Hamburger */
        .menu-container {
            position: relative;
            display: inline-block;
        }

        .hamburger {
            background-color: transparent;
            border: none;
            cursor: pointer;
            padding: 10px;
        }

        .hamburger div {
            width: 25px;
            height: 3px;
            background-color: white;
            margin: 5px 0;
            border-radius: 2px;
        }

        .hamburger:hover div {
            background-color: #ccc;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            left: 0;
            top: 45px;
            background-color: white;
            width: 180px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 100;
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 15px;
            color: #271b1b;
            text-decoration: none;
            font-size: 14px;
            border-bottom: 1px solid #eee;
            text-align: center;
        }

        .dropdown-menu a:hover {
            background-color: #f5f5f5;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
            color: #ff1900;
        }

        .dropdown-menu a:last-child:hover {
            background-color: #ffeaea;
        }

        .menu-badge {
            background-color: #e74c3c;
            color: white;
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 50%;
            margin-left: 5px;
        }

        /* Chat Nav Icon */
        .chat-nav-icon {
            position: relative;
            font-size: 24px;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .chat-nav-icon:hover {
            transform: scale(1.15);
        }

        .chat-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
        }

        /* Bell icon */
        .bell-icon {
            position: relative;
            font-size: 24px;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .bell-icon:hover {
            transform: scale(1.15);
        }

        .bell-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: #f39c12;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
        }

        /* Notification dropdown */
        .notif-wrap {
            position: relative;
        }

        .notif-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 40px;
            background: white;
            border-radius: 12px;
            width: 280px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 200;
            overflow: hidden;
        }

        .notif-dropdown.open {
            display: block;
        }

        .notif-header {
            background: #1a1a2e;
            color: white;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
        }

        .notif-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
            color: #333;
            text-decoration: none;
            display: block;
        }

        .notif-item:hover {
            background: #f9fafb;
        }

        .notif-item:last-child {
            border-bottom: none;
        }

        /* Avatar */
        .nav-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: white;
            color: #1a1a2e;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .nav-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .nav-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ======================== */
        /* CONTAINER */
        /* ======================== */
        .container {
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Welcome Box */
        .welcome-box {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 2px solid #0f3460;
        }

        .welcome-box h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .welcome-box p {
            font-size: 16px;
            color: #ccc;
        }

        .section-title {
            font-size: 22px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #1a1a2e;
            display: inline-block;
        }

        /* ======================== */
        /* STATS CARDS */
        /* ======================== */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            flex: 1;
            min-width: 160px;
            border: 1px solid #000;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        /* ======================== */
        /* QUICK ACTIONS */
        /* ======================== */
        .cards-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            width: 250px;
            text-decoration: none;
            color: #333;
            border: 1px solid #000;
            cursor: pointer;
            position: relative;
            transition: transform 0.2s;
        }

        .card:hover {
            background-color: #eef2ff;
            border: 2px solid #1a1a2e;
            transform: translateY(-3px);
        }

        .card .icon {
            font-size: 50px;
            margin-bottom: 15px;
        }

        .card h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .card p {
            font-size: 14px;
            color: #666;
        }

        .card-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background-color: #e74c3c;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 50%;
            min-width: 22px;
            text-align: center;
        }

        /* ======================== */
        /* RECENT ITEMS TABLE */
        /* ======================== */
        .recent-items {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #000;
        }

        .recent-items table {
            width: 100%;
            border-collapse: collapse;
        }

        .recent-items th,
        .recent-items td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .recent-items th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .recent-items tr:hover {
            background-color: #f5f5f5;
        }

        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            min-width: 110px;
            text-align: center;
        }

        .status.lost {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status.found {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }

        .status.claimed {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .status.received {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .reporter-tag {
            font-size: 12px;
            color: #6f42c1;
            background-color: #f0e6ff;
            padding: 2px 8px;
            border-radius: 10px;
        }

        /* Charts */
        .charts-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            border: 1px solid #000;
            flex: 1;
            min-width: 280px;
        }

        .chart-card h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f0f0;
        }

        .chart-card canvas {
            max-height: 220px;
        }
    </style>
</head>

<body>

    <div class="loading-screen" id="loadingScreen">
        <img src="animations/log-in_out.gif" alt="Loading...">
        <p class="loading-text">Please wait...</p>
    </div>

    <div class="main-content" id="mainContent">

        <!-- ======================== -->
        <!-- NAVBAR -->
        <!-- ======================== -->
        <div class="navbar">
            <!-- LEFT -->
            <div class="nav-left">
                <div class="menu-container">
                    <button class="hamburger" id="hamburgerBtn">
                        <div></div>
                        <div></div>
                        <div></div>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="admin_reports.php">Reports</a>
                        <a href="admin_activity_log.php">Activity Log</a>
                        <a href="admin_settings.php">Settings</a>
                        <a href="#" onclick="logoutNow()">Logout</a>
                    </div>
                </div>
                <h1>Campus-Find <span class="admin-badge">ADMIN</span></h1>
            </div>

            <!-- RIGHT -->
            <div class="nav-right">
                <label class="switch">
                    <input class="switch__input" id="darkModeBtn" type="checkbox" role="switch" />
                    <span class="switch__icon">
                        <span class="switch__icon-part switch__icon-part--1"></span>
                        <span class="switch__icon-part switch__icon-part--2"></span>
                        <span class="switch__icon-part switch__icon-part--3"></span>
                        <span class="switch__icon-part switch__icon-part--4"></span>
                        <span class="switch__icon-part switch__icon-part--5"></span>
                        <span class="switch__icon-part switch__icon-part--6"></span>
                        <span class="switch__icon-part switch__icon-part--7"></span>
                        <span class="switch__icon-part switch__icon-part--8"></span>
                        <span class="switch__icon-part switch__icon-part--9"></span>
                        <span class="switch__icon-part switch__icon-part--10"></span>
                        <span class="switch__icon-part switch__icon-part--11"></span>
                    </span>
                    <span class="switch__sr">Dark Mode</span>
                </label>
                <!-- Bell notification -->
                <div class="notif-wrap">
                    <span class="bell-icon" onclick="toggleNotif()" title="Notifications">
                        🔔
                        <?php if ($notif_count > 0 && !$admin_notif_seen): ?>
                            <span class="bell-badge" id="bellBadge"><?php echo $notif_count; ?></span>
                        <?php endif; ?>
                    </span>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">🔔 Notifications</div>
                        <?php if ($pending_claims > 0): ?>
                            <a href="admin_claims.php?filter=pending" class="notif-item">
                                🟡 <strong><?php echo $pending_claims; ?></strong> pending claim(s) ready for review
                            </a>
                        <?php endif; ?>
                        <?php if ($new_reports > 0): ?>
                            <a href="admin_manage_items.php" class="notif-item">
                                📦 <strong><?php echo $new_reports; ?></strong> new report(s) in the last 24 hours
                            </a>
                        <?php endif; ?>
                        <?php if ($notif_count == 0): ?>
                            <div class="notif-item" style="color:#999; text-align:center;">✅ All caught up!</div>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="admin_messages.php" class="chat-nav-icon" title="Chat">
                    💬
                    <?php if ($unread_count > 0) { ?>
                        <span class="chat-badge"><?php echo $unread_count; ?></span>
                    <?php } ?>
                </a>

                <a href="admin_profile.php" class="nav-avatar" title="My Profile">
                    <?php if ($has_photo) { ?>
                        <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php } else { ?>
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    <?php } ?>
                </a>
            </div>
        </div>

        <div class="container">

            <!-- ======================== -->
            <!-- WELCOME BOX -->
            <!-- ======================== -->
            <div class="welcome-box">
                <h1>Welcome back, <?php echo $username; ?>! 👋</h1>
                <p>Admin Panel — Manage lost & found items, users, claims, and chat with users.</p>
            </div>

            <!-- ======================== -->
            <!-- STATS -->
            <!-- ======================== -->
            <h2 class="section-title">Overview</h2>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-number"><?php echo $total_items; ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔴</div>
                    <div class="stat-number"><?php echo $total_lost; ?></div>
                    <div class="stat-label">Lost</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔵</div>
                    <div class="stat-number"><?php echo $total_found; ?></div>
                    <div class="stat-label">Found</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🟡</div>
                    <div class="stat-number"><?php echo $total_claimed; ?></div>
                    <div class="stat-label">Claimed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🟢</div>
                    <div class="stat-number"><?php echo $total_received; ?></div>
                    <div class="stat-label">Received</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Users</div>
                </div>
            </div>

            <!-- ======================== -->
            <!-- QUICK ACTIONS -->
            <!-- ======================== -->
            <h2 class="section-title">Quick Actions</h2>

            <div class="cards-container">
                <a href="admin_manage_items.php" class="card">
                    <div class="icon">📋</div>
                    <h3>Manage Items</h3>
                    <p>View, edit, update item status</p>
                </a>
                <a href="admin_manage_users.php" class="card">
                    <div class="icon">👥</div>
                    <h3>Manage Users</h3>
                    <p>View and manage all users</p>
                </a>
                <a href="admin_claims.php" class="card">
                    <div class="icon">✅</div>
                    <h3>Approve Claims</h3>
                    <p>Review and approve item claims</p>
                    <?php if ($pending_claims > 0) { ?>
                        <span class="card-badge"><?php echo $pending_claims; ?></span>
                    <?php } ?>
                </a>

            </div>

            <!-- ======================== -->
            <!-- CHARTS -->
            <!-- ======================== -->
            <h2 class="section-title">Analytics (Last 7 Days)</h2>

            <div class="charts-grid">
                <div class="chart-card">
                    <h3>📦 Items Reported</h3>
                    <canvas id="itemsChart"></canvas>
                </div>
                <div class="chart-card">
                    <h3>✅ Claims Submitted</h3>
                    <canvas id="claimsChart"></canvas>
                </div>
            </div>

            <!-- ======================== -->
            <!-- RECENT ITEMS -->
            <!-- ======================== -->
            <h2 class="section-title">Recent Items</h2>

            <div class="recent-items">
                <?php if (mysqli_num_rows($recent_result) > 0) { ?>
                    <table>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Reporter</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                        <?php while ($row = mysqli_fetch_assoc($recent_result)) {
                            $type = strtolower($row['report_type']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><span class="reporter-tag">👤 <?php echo htmlspecialchars($row['reporter']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($row['date_reported'])); ?></td>
                                <td>
                                    <?php
                                    if ($type == 'received') {
                                        echo '<span class="status received">🟢 Received</span>';
                                    } else if ($type == 'lost') {
                                        echo '<span class="status lost">🔴 Lost</span>';
                                    } else if ($type == 'found') {
                                        echo '<span class="status found">🔵 Found</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } else { ?>
                    <p style="text-align: center; color: gray; padding: 20px;">No items reported yet.</p>
                <?php } ?>
            </div>

        </div>
    </div>

    <script>
        window.onpageshow = function (event) {
            if (event.persisted) { window.location.reload(); }
        };

        var hamburgerBtn = document.getElementById('hamburgerBtn');
        var dropdownMenu = document.getElementById('dropdownMenu');

        hamburgerBtn.onclick = function () {
            dropdownMenu.style.display = (dropdownMenu.style.display === 'block') ? 'none' : 'block';
        };

        document.onclick = function (e) {
            if (e.target !== hamburgerBtn && !hamburgerBtn.contains(e.target)) {
                dropdownMenu.style.display = 'none';
            }
            // Close notif dropdown if clicked outside
            var notifWrap = document.querySelector('.notif-wrap');
            if (notifWrap && !notifWrap.contains(e.target)) {
                document.getElementById('notifDropdown').classList.remove('open');
            }
        };

        function toggleNotif() {
            document.getElementById('notifDropdown').classList.toggle('open');
            var badge = document.getElementById('bellBadge');
            if (badge) badge.style.display = 'none';
            // Save to session so it persists across refreshes but resets on logout
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'msg_ajax/mark_notif_seen.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('key=<?php echo $admin_notif_key; ?>');
        }

        setTimeout(function () {
            document.getElementById('loadingScreen').style.display = 'none';
            document.getElementById('mainContent').style.display = 'block';
        }, 1300);

        function logoutNow() {
            document.getElementById('mainContent').style.display = 'none';
            document.getElementById('loadingScreen').style.display = 'flex';
            setTimeout(function () { window.location.href = "logout.php"; }, 1300);
        }

        // Real-time notification polling (every 30 seconds)
        function pollAdminNotifications() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'msg_ajax/poll_notifications.php', true);
            xhr.onload = function () {
                try {
                    var data = JSON.parse(xhr.responseText);
                    var badge = document.getElementById('bellBadge');
                    if (data.notifications > 0) {
                        if (!badge) {
                            var wrap = document.querySelector('.notif-wrap');
                            if (wrap) {
                                badge = document.createElement('span');
                                badge.id = 'bellBadge'; badge.className = 'bell-badge';
                                wrap.querySelector('.bell-icon').appendChild(badge);
                            }
                        }
                        if (badge) badge.textContent = data.notifications;
                    } else {
                        if (badge) badge.style.display = 'none';
                    }
                    var chatBadge = document.querySelector('.chat-badge');
                    if (chatBadge && data.chat > 0) chatBadge.textContent = data.chat;
                } catch (e) { }
            };
            xhr.send();
        }
        setInterval(pollAdminNotifications, 30000);

        // Charts
        var chartLabels = <?php echo json_encode($chart_labels); ?>;
        var chartLost = <?php echo json_encode($chart_lost); ?>;
        var chartFound = <?php echo json_encode($chart_found); ?>;
        var chartClaims = <?php echo json_encode($chart_claims); ?>;

        function getChartColors() {
            var dark = document.documentElement.classList.contains('dark-mode');
            return {
                gridCol:   dark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)',
                tickCol:   dark ? '#c0c8ff' : '#666',
                legendCol: dark ? '#c0c8ff' : '#333'
            };
        }

        function buildScaleOpts(c) {
            return {
                x: { ticks: { color: c.tickCol }, grid: { color: c.gridCol } },
                y: { beginAtZero: true, ticks: { stepSize: 1, color: c.tickCol }, grid: { color: c.gridCol } }
            };
        }

        var c = getChartColors();

        var itemsChart = new Chart(document.getElementById('itemsChart'), {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    { label: 'Lost', data: chartLost, backgroundColor: 'rgba(231,76,60,0.7)', borderRadius: 6 },
                    { label: 'Found', data: chartFound, backgroundColor: 'rgba(52,152,219,0.7)', borderRadius: 6 }
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'top', labels: { color: c.legendCol } } }, scales: buildScaleOpts(c) }
        });

        var claimsChart = new Chart(document.getElementById('claimsChart'), {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Claims',
                    data: chartClaims,
                    borderColor: '#159f35',
                    backgroundColor: 'rgba(21,159,53,0.1)',
                    borderWidth: 2, fill: true, tension: 0.4,
                    pointBackgroundColor: '#159f35'
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'top', labels: { color: c.legendCol } } }, scales: buildScaleOpts(c) }
        });

        // Update chart colors when dark mode is toggled
        new MutationObserver(function() {
            var nc = getChartColors();
            [itemsChart, claimsChart].forEach(function(chart) {
                chart.options.plugins.legend.labels.color = nc.legendCol;
                chart.options.scales.x.ticks.color = nc.tickCol;
                chart.options.scales.x.grid.color  = nc.gridCol;
                chart.options.scales.y.ticks.color = nc.tickCol;
                chart.options.scales.y.grid.color  = nc.gridCol;
                chart.update();
            });
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
    <?php mysqli_close($conn); ?>
</body>

</html>