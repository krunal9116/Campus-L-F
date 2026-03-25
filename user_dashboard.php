<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];
require_once __DIR__ . '/config.php';

// Get user data
$user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE username = '" . mysqli_real_escape_string($conn, $username) . "'"));
if (!$user_data) {
    session_destroy();
    header("Location: index.php");
    exit();
}
$user_id = $user_data['id'];

// Profile photo
$has_photo = !empty($user_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $user_data['profile_photo'] : '';

// Unread chat count
$unread_q = "SELECT COUNT(*) as count FROM messages m 
            JOIN conversations c ON m.conversation_id = c.id 
            WHERE (c.user1_id = '$user_id' OR c.user2_id = '$user_id') 
            AND m.sender_id != '$user_id' AND m.is_read = 0";
$unread_result = mysqli_query($conn, $unread_q);
$unread_count = $unread_result ? mysqli_fetch_assoc($unread_result)['count'] : 0;

// Notifications: recent claim status updates
$notif_q = "SELECT c.id, c.status, c.claim_date, i.item_name 
            FROM claims c JOIN items i ON c.item_id = i.id 
            WHERE c.user_id = '$user_id' AND c.status IN ('approved','rejected')
            ORDER BY c.claim_date DESC LIMIT 10";
$notif_result = mysqli_query($conn, $notif_q);
$notifications = [];
while ($n = mysqli_fetch_assoc($notif_result)) {
    $notifications[] = $n;
}
$notif_count = count($notifications);
$notif_key = $notif_count > 0 ? implode(',', array_column($notifications, 'id')) : 'none';
$stored_key = $user_data['notif_seen_key'] ?? '';
$notif_seen = ($stored_key === $notif_key);
$seen_ids = ($stored_key && $stored_key !== 'none') ? explode(',', $stored_key) : [];

$query = "SELECT * FROM items ORDER BY date_reported DESC LIMIT 10";
$result = mysqli_query($conn, $query);
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
    <title>User Dashboard - Campus Find</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            background-color: #159f35;
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
            font-size: 28px;
        }

        /* Hamburger - Left Side */
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
            background-color: #e6f8e6;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            left: 0;
            top: 45px;
            background-color: white;
            width: 160px;
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
            background-color: #ff1900;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
        }

        /* Avatar - Right Side */
        .bell-wrap {
            position: relative;
        }

        .bell-icon {
            font-size: 24px;
            cursor: pointer;
            transition: transform 0.2s;
            user-select: none;
        }

        .bell-icon:hover {
            transform: scale(1.15);
        }

        .bell-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background: #ff1900;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 50%;
            min-width: 18px;
            text-align: center;
        }

        .notif-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 12px);
            right: -10px;
            width: 290px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            overflow: hidden;
        }

        .notif-dropdown.open {
            display: block;
        }

        .notif-header {
            background: #159f35;
            color: white;
            padding: 12px 16px;
            font-weight: 600;
            font-size: 14px;
        }

        .notif-item {
            padding: 11px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notif-item:last-child {
            border-bottom: none;
        }

        .notif-dot {
            font-size: 20px;
            flex-shrink: 0;
        }

        .notif-text {
            line-height: 1.5;
            flex: 1;
        }

        .notif-text strong {
            color: #333;
            display: block;
            font-size: 13px;
        }

        .notif-status {
            font-size: 12px;
            font-weight: 600;
        }

        .notif-time {
            font-size: 11px;
            color: #aaa;
        }

        .notif-empty {
            padding: 20px;
            text-align: center;
            color: #999;
            font-size: 13px;
        }

        .nav-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background-color: white;
            color: #159f35;
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

        .welcome-box {
            background-color: #159f35;
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 2px solid black;
        }

        .welcome-box h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .welcome-box p {
            font-size: 16px;
        }

        .section-title {
            font-size: 22px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #159f35;
            display: inline-block;
        }

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
            border: 1px solid #000000;
            cursor: pointer;
            position: relative;
        }

        .card:hover {
            background-color: #e6f8e6;
            border: 2px solid #159f35;
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
            background-color: #ff1900;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 50%;
            min-width: 22px;
            text-align: center;
        }

        .recent-items {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #000000;
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
            <!-- LEFT: Hamburger + Title -->
            <div class="nav-left">
                <div class="menu-container">
                    <button class="hamburger" id="hamburgerBtn">
                        <div></div>
                        <div></div>
                        <div></div>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="my_reports.php">My Reports</a>
                        <a href="settings.php">Settings</a>
                        <a href="#" onclick="logoutNow()">Logout</a>
                    </div>
                </div>
                <h1>Campus-Find</h1>
            </div>

            <!-- RIGHT: Bell + Chat + Avatar -->
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
                <!-- Notification Bell -->
                <div class="bell-wrap" id="bellWrap">
                    <span class="bell-icon" onclick="toggleNotif()" title="Notifications">🔔</span>
                    <?php if ($notif_count > 0 && !$notif_seen) { ?>
                        <span class="bell-badge" id="bellBadge"><?php echo $notif_count; ?></span>
                    <?php } ?>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">🔔 Notifications</div>
                        <?php if (empty($notifications)) { ?>
                            <div class="notif-empty">No notifications yet</div>
                        <?php } else {
                            foreach ($notifications as $n) {
                                $dot = $n['status'] === 'approved' ? '✅' : '❌';
                                $label = $n['status'] === 'approved' ? 'Approved' : 'Rejected';
                                $color = $n['status'] === 'approved' ? '#159f35' : '#e53935';
                                $time_ago = date('d M Y', strtotime($n['claim_date']));
                                $is_seen = in_array((string) $n['id'], $seen_ids);
                                ?>
                                <div class="notif-item" style="<?php echo $is_seen ? 'opacity:0.7;' : ''; ?>">
                                    <span class="notif-dot"><?php echo $dot; ?></span>
                                    <div class="notif-text">
                                        <strong><?php echo htmlspecialchars($n['item_name']); ?></strong>
                                        <span class="notif-status"
                                            style="color:<?php echo $color; ?>;"><?php echo $label; ?></span>
                                        <div class="notif-time">
                                            <?php echo $time_ago; ?>         <?php if ($is_seen)
                                                            echo ' &nbsp;✓ <span style="color:#aaa;">Seen</span>'; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php }
                        } ?>
                    </div>
                </div>

                <a href="messages.php" class="chat-nav-icon" title="Chat">
                    💬
                    <?php if ($unread_count > 0) { ?>
                        <span class="chat-badge"><?php echo $unread_count; ?></span>
                    <?php } ?>
                </a>

                <a href="profile.php" class="nav-avatar" title="My Profile">
                    <?php if ($has_photo) { ?>
                        <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile">
                    <?php } else { ?>
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    <?php } ?>
                </a>
            </div>
        </div>

        <div class="container">

            <div class="welcome-box">
                <h1>Hello, <?php echo htmlspecialchars($username); ?>!</h1>
                <p>Welcome to Campus-Find. A Campus Lost & Found Management System. Report or find your lost items
                    easily.</p>
            </div>

            <h2 class="section-title">Quick Actions</h2>

            <div class="cards-container">
                <a href="report_lost.php" class="card">
                    <div class="icon">📢</div>
                    <h3>Report Lost Item</h3>
                    <p>Lost something? Report it here</p>
                </a>
                <a href="report_found.php" class="card">
                    <div class="icon">🕵️‍♀️</div>
                    <h3>Report Found Item</h3>
                    <p>Found something? Let others know</p>
                </a>
                <a href="search_items.php" class="card">
                    <div class="icon">🔎</div>
                    <h3>Search Items</h3>
                    <p>Search for lost or found items</p>
                </a>
                <a href="claim_item.php" class="card">
                    <div class="icon">✋</div>
                    <h3>Claimed Item</h3>
                    <p>See all the claimed items</p>
                </a>
            </div>

            <h2 class="section-title">Recent Lost & Found Items</h2>

            <div class="recent-items">
                <?php if (mysqli_num_rows($result) > 0) { ?>
                    <table>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                        <?php while ($row = mysqli_fetch_assoc($result)) {
                            $type = strtolower($row['report_type']);

                            $claim_check = "SELECT * FROM claims WHERE item_id = '" . $row['id'] . "' AND user_id = '$user_id'";
                            $claim_result_check = mysqli_query($conn, $claim_check);
                            $this_user_claimed = (mysqli_num_rows($claim_result_check) > 0);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo date('d M Y', strtotime($row['date_reported'])); ?></td>
                                <td>
                                    <?php
                                    if ($type == 'received') {
                                        echo '<span class="status received">🟢 Received</span>';
                                    } else if ($this_user_claimed) {
                                        echo '<span class="status claimed">🟡 Claimed</span>';
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

    <!-- Footer -->
    <footer style="text-align:center;padding:18px;margin-top:30px;font-size:13px;color:#888;border-top:1px solid #eee;">
        📧 Contact us: <a href="mailto:campusfind3@gmail.com"
            style="color:#159f35;font-weight:600;text-decoration:none;">campusfind3@gmail.com</a>
        &nbsp;|&nbsp; Campus Find — Lost &amp; Found Management System
    </footer>

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
        };

        setTimeout(function () {
            document.getElementById('loadingScreen').style.display = 'none';
            document.getElementById('mainContent').style.display = 'block';
        }, 1300);

        function logoutNow() {
            document.getElementById('mainContent').style.display = 'none';
            document.getElementById('loadingScreen').style.display = 'flex';
            setTimeout(function () { window.location.href = "logout.php"; }, 1300);
        }
        function getBellBadge() { return document.getElementById('bellBadge'); }
        function toggleNotif() {
            document.getElementById('notifDropdown').classList.toggle('open');
            var badge = getBellBadge();
            if (badge) badge.style.display = 'none';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'msg_ajax/mark_notif_seen.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('key=<?php echo $notif_key; ?>');
        }
        document.addEventListener('click', function (e) {
            var wrap = document.getElementById('bellWrap');
            if (wrap && !wrap.contains(e.target)) {
                document.getElementById('notifDropdown').classList.remove('open');
            }
        });

        // Real-time notification polling (every 30 seconds)
        function pollNotifications() {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', 'msg_ajax/poll_notifications.php', true);
            xhr.onload = function () {
                try {
                    var data = JSON.parse(xhr.responseText);
                    // Update bell badge
                    var bellBadgeEl = document.getElementById('bellBadge');
                    if (data.notifications > 0) {
                        if (!bellBadgeEl) {
                            var wrap = document.getElementById('bellWrap');
                            if (wrap) {
                                bellBadgeEl = document.createElement('span');
                                bellBadgeEl.id = 'bellBadge';
                                bellBadgeEl.className = 'bell-badge';
                                wrap.appendChild(bellBadgeEl);
                            }
                        }
                        if (bellBadgeEl) { bellBadgeEl.textContent = data.notifications; bellBadgeEl.style.display = ''; }
                    } else {
                        if (bellBadgeEl) bellBadgeEl.style.display = 'none';
                    }
                    // Update chat badge
                    var chatBadge = document.querySelector('.chat-badge');
                    if (chatBadge && data.chat > 0) chatBadge.textContent = data.chat;
                } catch (e) { }
            };
            xhr.send();
        }
        setInterval(pollNotifications, 30000);
    </script>
    <?php mysqli_close($conn); ?>
</body>

</html>