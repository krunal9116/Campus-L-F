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
if (!$admin_data) {
    session_destroy();
    header("Location: index.php");
    exit();
}
$admin_id = $admin_data['id'];
$current_email = $admin_data['email'];
$has_photo = !empty($admin_data['profile_photo']);
$photo_path = $has_photo ? 'uploads/profile_photos/' . $admin_data['profile_photo'] : '';

$success_msg = "";
$error_msg = "";

// ========================
// CHANGE USERNAME
// ========================
if (isset($_POST['change_username'])) {
    $new_username = mysqli_real_escape_string($conn, trim($_POST['new_username']));
    if (empty($new_username)) {
        $error_msg = "Username cannot be empty!";
    } elseif ($new_username == $username) {
        $error_msg = "New username is same as current!";
    } else {
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$new_username' AND id != '$admin_id'");
        if (mysqli_num_rows($check) > 0) {
            $error_msg = "Username already taken!";
        } else {
            if (mysqli_query($conn, "UPDATE users SET username = '$new_username' WHERE id = '$admin_id'")) {
                $_SESSION['username'] = $new_username;
                $username = $new_username;
                $success_msg = "Username updated successfully!";
            } else {
                $error_msg = "Failed to update username.";
            }
        }
    }
}

// ========================
// CHANGE PASSWORD
// ========================
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_new_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_msg = "All password fields are required!";
    } elseif ($new_password !== $confirm_password) {
        $error_msg = "New passwords do not match!";
    } elseif (strlen($new_password) < 8) {
        $error_msg = "Password must be at least 8 characters!";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error_msg = "Password must contain at least 1 uppercase letter!";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error_msg = "Password must contain at least 1 lowercase letter!";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error_msg = "Password must contain at least 1 number!";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error_msg = "Password must contain at least 1 special character!";
    } else {
        if (password_verify($current_password, $admin_data['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            if (mysqli_query($conn, "UPDATE users SET password = '$hashed' WHERE id = '$admin_id'")) {
                $success_msg = "Password updated successfully!";
                $admin_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = '$admin_id'"));
            } else {
                $error_msg = "Failed to update password.";
            }
        } else {
            $error_msg = "Current password is incorrect!";
        }
    }
}

// Unread chat count for navbar
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
    <title>Admin Settings — Campus Find</title>
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

        /* ── Navbar ── */
        .navbar {
            background: #1a1f36;
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
            font-size: 24px;
            font-weight: 700;
        }

        .admin-badge {
            background: #e74c3c;
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 20px;
            vertical-align: middle;
            margin-left: 8px;
        }

        .menu-container {
            position: relative;
        }

        .hamburger {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .hamburger div {
            width: 25px;
            height: 3px;
            background: white;
            border-radius: 3px;
            transition: background 0.2s;
        }

        .hamburger:hover div {
            background: #ccc;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            background: white;
            border-radius: 10px;
            min-width: 180px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            overflow: hidden;
        }

        .dropdown-menu a {
            display: block;
            padding: 12px 18px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
            text-align: center;
        }

        .dropdown-menu a:hover {
            background: #f0f9f0;
        }

        .dropdown-menu a:last-child {
            border-bottom: none;
            color: #e53935;
        }

        .dropdown-menu a:last-child:hover {
            background: #fff0f0;
        }

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
            background: #ff1900;
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
            color: #1a1f36;
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .nav-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* ── Container ── */
        .container {
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 25px;
            border-bottom: 3px solid #1a1f36;
            padding-bottom: 10px;
            display: inline-block;
        }

        /* ── Messages ── */
        .msg {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .msg.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .msg.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* ── Cards ── */
        .settings-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 18px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        .card-header:hover {
            background: #e9ecef;
        }

        .card-header h3 {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .card-header .arrow {
            font-size: 14px;
            color: #666;
            transition: transform 0.3s;
        }

        .card-header .arrow.open {
            transform: rotate(180deg);
        }

        .card-body {
            padding: 25px;
            display: none;
        }

        .card-body.show {
            display: block;
        }

        /* ── Forms ── */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #1a1f36;
        }

        .form-group input:disabled {
            background: #f0f0f0;
            color: #999;
        }

        /* ── Buttons ── */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }

        .btn-dark {
            background: #1a1f36;
            color: white;
        }

        .btn-dark:hover {
            background: #0d1124;
        }

        .btn-blue {
            background: #007bff;
            color: white;
        }

        .btn-blue:hover {
            background: #0056b3;
        }

        .btn-gray {
            background: #6c757d;
            color: white;
        }

        .btn-gray:hover {
            background: #545b62;
        }

        /* ── Password hints ── */
        .password-hint {
            font-size: 11px;
            margin: 8px 0;
        }

        .password-hint span {
            display: block;
            margin: 3px 0;
            color: #999;
            transition: color 0.3s;
        }

        .password-hint span.valid {
            color: #159f35;
        }

        .password-hint span.invalid {
            color: #e74c3c;
        }

        /* ── Email OTP ── */
        .otp-section {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .otp-input-row {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 15px 0;
        }

        .otp-box {
            width: 45px;
            height: 45px;
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            border: 2px solid #ccc;
            border-radius: 8px;
        }

        .otp-box:focus {
            outline: none;
            border-color: #1a1f36;
        }

        .timer-text {
            font-size: 13px;
            color: #666;
            text-align: center;
            margin-top: 10px;
        }

        .timer-text span {
            font-weight: 600;
            color: #333;
        }

        .loading {
            display: none;
            text-align: center;
            margin: 10px 0;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1a1f36;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg)
            }

            100% {
                transform: rotate(360deg)
            }
        }

        .loading p {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }

        .otp-msg {
            padding: 8px;
            border-radius: 8px;
            font-size: 13px;
            margin: 10px 0;
            display: none;
        }

        .otp-msg.error {
            background: #f8d7da;
            color: #721c24;
        }

        .otp-msg.success {
            background: #d4edda;
            color: #155724;
        }

        .verified-badge {
            background: #d4edda;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .email-display {
            color: #1a1f36;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <div class="navbar">
        <div class="nav-left">
            <div class="menu-container">
                <button class="hamburger" id="hamburgerBtn">
                    <div></div>
                    <div></div>
                    <div></div>
                </button>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="admin_dashboard.php">Dashboard</a>
                    <a href="admin_claims.php">Claims</a>
                    <a href="admin_manage_items.php">Manage Items</a>
                    <a href="admin_manage_users.php">Manage Users</a>
                    <a href="admin_reports.php">Reports</a>
                    <a href="#" onclick="logoutNow()">Logout</a>
                </div>
            </div>
            <h1>Campus-Find <span class="admin-badge">ADMIN</span></h1>
        </div>
        <div class="nav-right">
            <a href="admin_messages.php" class="chat-nav-icon" title="Chat">
                💬
                <?php if ($unread_count > 0) { ?>
                    <span class="chat-badge"><?php echo $unread_count; ?></span>
                <?php } ?>
            </a>
            <a href="admin_profile.php" class="nav-avatar" title="Admin">
                <?php if ($has_photo) { ?>
                    <img src="<?php echo $photo_path; ?>?t=<?php echo time(); ?>" alt="Profile">
                <?php } else { ?>
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                <?php } ?>
            </a>
        </div>
    </div>

    <div class="container">

        <h2 class="page-title">Admin Settings</h2>

        <?php if ($success_msg) { ?>
            <div class="msg success">✅ <?php echo $success_msg; ?></div>
        <?php } ?>
        <?php if ($error_msg) { ?>
            <div class="msg error">❌ <?php echo $error_msg; ?></div>
        <?php } ?>

        <!-- 1. CHANGE USERNAME -->
        <div class="settings-card">
            <div class="card-header" onclick="toggleSection('usernameSection', this)">
                <h3>👤 Change Username</h3>
                <span class="arrow">▼</span>
            </div>
            <div class="card-body" id="usernameSection">
                <form method="POST">
                    <div class="form-group">
                        <label>Current Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($username); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>New Username</label>
                        <input type="text" name="new_username" placeholder="Enter new username" required>
                    </div>
                    <button type="submit" name="change_username" class="btn btn-dark">Update Username</button>
                </form>
            </div>
        </div>

        <!-- 2. CHANGE EMAIL (OTP) -->
        <div class="settings-card">
            <div class="card-header" onclick="toggleSection('emailSection', this)">
                <h3>📧 Change Email</h3>
                <span class="arrow">▼</span>
            </div>
            <div class="card-body" id="emailSection">
                <div class="form-group">
                    <label>Current Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($current_email); ?>" disabled>
                </div>

                <!-- Step 1: Verify Password -->
                <div id="emailStep1">
                    <div class="form-group">
                        <label>🔒 Enter Your Password to Continue</label>
                        <input type="password" id="emailPassword" placeholder="Enter your password">
                    </div>
                    <button type="button" class="btn btn-dark" id="verifyPassBtn" onclick="verifyPasswordForEmail()">🔒
                        Verify Password</button>
                    <div class="loading" id="passLoading">
                        <div class="spinner"></div>
                        <p>Verifying password...</p>
                    </div>
                    <div class="otp-msg" id="passMsg"></div>
                </div>

                <!-- Step 2: Enter New Email -->
                <div id="emailStep2" style="display:none;">
                    <div class="verified-badge">✅ Password verified! Now enter your new email.</div>
                    <div class="form-group">
                        <label>New Email</label>
                        <input type="email" id="newEmail" placeholder="Enter new email">
                    </div>
                    <button type="button" class="btn btn-blue" id="sendEmailOtpBtn" onclick="sendEmailOTP()">Send OTP to
                        New Email</button>
                    <div class="loading" id="emailLoading">
                        <div class="spinner"></div>
                        <p>Sending OTP...</p>
                    </div>
                </div>

                <!-- Step 3: Verify OTP -->
                <div id="emailStep3" class="otp-section">
                    <p style="font-size:14px;color:#666;">Enter OTP sent to <span class="email-display"
                            id="newEmailDisplay"></span></p>
                    <div class="otp-input-row">
                        <input type="text" class="otp-box email-otp" maxlength="1" data-index="0">
                        <input type="text" class="otp-box email-otp" maxlength="1" data-index="1">
                        <input type="text" class="otp-box email-otp" maxlength="1" data-index="2">
                        <input type="text" class="otp-box email-otp" maxlength="1" data-index="3">
                        <input type="text" class="otp-box email-otp" maxlength="1" data-index="4">
                        <input type="text" class="otp-box email-otp" maxlength="1" data-index="5">
                    </div>
                    <div class="otp-msg" id="emailOtpMsg"></div>
                    <div class="timer-text">OTP expires in: <span id="emailCountdown">05:00</span></div>
                    <div style="display:flex;gap:10px;margin-top:15px;">
                        <button type="button" class="btn btn-dark" onclick="verifyEmailOTP()">Verify &amp; Update
                            Email</button>
                        <button type="button" class="btn btn-gray" onclick="cancelEmailChange()">Cancel</button>
                    </div>
                    <div class="loading" id="emailVerifyLoading">
                        <div class="spinner"></div>
                        <p>Verifying...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. CHANGE PASSWORD -->
        <div class="settings-card">
            <div class="card-header" onclick="toggleSection('passwordSection', this)">
                <h3>🔒 Change Password</h3>
                <span class="arrow">▼</span>
            </div>
            <div class="card-body" id="passwordSection">
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" placeholder="Enter current password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" id="newPassword" placeholder="Enter new password"
                            required>
                        <div class="password-hint">
                            <span id="hint-length">✗ At least 8 characters</span>
                            <span id="hint-upper">✗ 1 uppercase letter (A-Z)</span>
                            <span id="hint-lower">✗ 1 lowercase letter (a-z)</span>
                            <span id="hint-number">✗ 1 number (0-9)</span>
                            <span id="hint-special">✗ 1 special character (!@#$%^&amp;*)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_new_password" id="confirmNewPassword"
                            placeholder="Confirm new password" required>
                        <p id="passMatchMsg" style="color:red;font-size:12px;margin-top:4px;display:none;">❌ Passwords
                            do not match!</p>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-dark">Update Password</button>
                </form>
            </div>
        </div>

    </div>

    <script>
        // Toggle accordion
        function toggleSection(id, header) {
            var section = document.getElementById(id);
            var arrow = header.querySelector('.arrow');
            section.classList.toggle('show');
            arrow.classList.toggle('open');
        }

        // Hamburger
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

        // Logout
        function logoutNow() { window.location.href = 'logout.php'; }

        // Password hints
        var newPassword = document.getElementById('newPassword');
        var confirmNewPassword = document.getElementById('confirmNewPassword');
        var passMatchMsg = document.getElementById('passMatchMsg');

        newPassword.addEventListener('input', function () {
            var val = this.value;
            checkHint('hint-length', val.length >= 8);
            checkHint('hint-upper', /[A-Z]/.test(val));
            checkHint('hint-lower', /[a-z]/.test(val));
            checkHint('hint-number', /[0-9]/.test(val));
            checkHint('hint-special', /[!@#$%^&*(),.?":{}|<>]/.test(val));
            passMatchMsg.style.display = (confirmNewPassword.value && val !== confirmNewPassword.value) ? 'block' : 'none';
        });
        confirmNewPassword.addEventListener('input', function () {
            passMatchMsg.style.display = (newPassword.value !== this.value) ? 'block' : 'none';
        });
        function checkHint(id, valid) {
            var el = document.getElementById(id);
            var text = el.textContent.substring(2);
            el.textContent = (valid ? '✓ ' : '✗ ') + text;
            el.className = valid ? 'valid' : 'invalid';
        }

        // ── Email Change ──
        var emailPasswordVerified = false;
        var emailTimerInterval;

        function verifyPasswordForEmail() {
            var password = document.getElementById('emailPassword').value.trim();
            if (!password) { showPassMsg('Please enter your password!', 'error'); return; }
            document.getElementById('passLoading').style.display = 'block';
            document.getElementById('verifyPassBtn').style.display = 'none';
            document.getElementById('passMsg').style.display = 'none';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'email_change/verify_password_email_change.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                document.getElementById('passLoading').style.display = 'none';
                document.getElementById('verifyPassBtn').style.display = 'inline-block';
                try {
                    var r = JSON.parse(this.responseText);
                    if (r.success) {
                        emailPasswordVerified = true;
                        document.getElementById('emailStep1').style.display = 'none';
                        document.getElementById('emailStep2').style.display = 'block';
                    } else { showPassMsg(r.message, 'error'); }
                } catch (e) { showPassMsg('Something went wrong.', 'error'); }
            };
            xhr.send('password=' + encodeURIComponent(password));
        }
        function showPassMsg(text, type) {
            var el = document.getElementById('passMsg');
            el.textContent = (type === 'error' ? '❌ ' : '✅ ') + text;
            el.className = 'otp-msg ' + type;
            el.style.display = 'block';
        }

        function sendEmailOTP() {
            if (!emailPasswordVerified) { alert('Please verify your password first!'); return; }
            var newEmail = document.getElementById('newEmail').value.trim();
            if (!newEmail) { alert('Please enter a new email!'); return; }
            document.getElementById('emailLoading').style.display = 'block';
            document.getElementById('sendEmailOtpBtn').style.display = 'none';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'email_change/send_email_change_otp.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                document.getElementById('emailLoading').style.display = 'none';
                document.getElementById('sendEmailOtpBtn').style.display = 'inline-block';
                try {
                    var r = JSON.parse(this.responseText);
                    if (r.success) {
                        document.getElementById('emailStep2').style.display = 'none';
                        document.getElementById('emailStep3').style.display = 'block';
                        document.getElementById('newEmailDisplay').textContent = newEmail;
                        startEmailCountdown(300);
                        document.querySelectorAll('.email-otp')[0].focus();
                    } else { alert(r.message); }
                } catch (e) { alert('Something went wrong.'); }
            };
            xhr.send('new_email=' + encodeURIComponent(newEmail));
        }

        function verifyEmailOTP() {
            var otp = '';
            document.querySelectorAll('.email-otp').forEach(function (i) { otp += i.value; });
            if (otp.length !== 6) { showOtpMsg('Please enter complete 6-digit OTP!', 'error'); return; }
            var newEmail = document.getElementById('newEmail').value.trim();
            document.getElementById('emailVerifyLoading').style.display = 'block';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'email_change/verify_email_change_otp.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                document.getElementById('emailVerifyLoading').style.display = 'none';
                try {
                    var r = JSON.parse(this.responseText);
                    if (r.success) {
                        showOtpMsg(r.message, 'success');
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        showOtpMsg(r.message, 'error');
                        document.querySelectorAll('.email-otp').forEach(function (i) { i.value = ''; });
                        document.querySelectorAll('.email-otp')[0].focus();
                    }
                } catch (e) { showOtpMsg('Something went wrong.', 'error'); }
            };
            xhr.send('otp=' + otp + '&new_email=' + encodeURIComponent(newEmail));
        }

        function showOtpMsg(text, type) {
            var el = document.getElementById('emailOtpMsg');
            el.textContent = (type === 'error' ? '❌ ' : '✅ ') + text;
            el.className = 'otp-msg ' + type;
            el.style.display = 'block';
        }

        function cancelEmailChange() {
            document.getElementById('emailStep1').style.display = 'block';
            document.getElementById('emailStep2').style.display = 'none';
            document.getElementById('emailStep3').style.display = 'none';
            clearInterval(emailTimerInterval);
            emailPasswordVerified = false;
            document.getElementById('emailPassword').value = '';
            document.querySelectorAll('.email-otp').forEach(function (i) { i.value = ''; });
            document.getElementById('emailOtpMsg').style.display = 'none';
            document.getElementById('passMsg').style.display = 'none';
        }

        // OTP inputs
        var emailOtpInputs = document.querySelectorAll('.email-otp');
        emailOtpInputs.forEach(function (input, index) {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 1 && index < 5) emailOtpInputs[index + 1].focus();
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) emailOtpInputs[index - 1].focus();
            });
            input.addEventListener('paste', function (e) {
                e.preventDefault();
                var paste = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                for (var i = 0; i < paste.length; i++) { if (emailOtpInputs[i]) emailOtpInputs[i].value = paste[i]; }
                if (paste.length > 0) emailOtpInputs[Math.min(paste.length, 5)].focus();
            });
        });

        function startEmailCountdown(seconds) {
            clearInterval(emailTimerInterval);
            var remaining = seconds;
            var countdownEl = document.getElementById('emailCountdown');
            countdownEl.style.color = '#333';
            emailTimerInterval = setInterval(function () {
                var mins = Math.floor(remaining / 60);
                var secs = remaining % 60;
                countdownEl.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
                remaining--;
                if (remaining < 0) {
                    clearInterval(emailTimerInterval);
                    countdownEl.textContent = 'Expired';
                    countdownEl.style.color = 'red';
                }
            }, 1000);
        }

        // Open on error
        <?php if ($error_msg || $success_msg): ?>
            <?php if (isset($_POST['change_username'])): ?>
                document.getElementById('usernameSection').classList.add('show');
                document.querySelector('[onclick*="usernameSection"] .arrow').classList.add('open');
            <?php elseif (isset($_POST['change_password'])): ?>
                document.getElementById('passwordSection').classList.add('show');
                document.querySelector('[onclick*="passwordSection"] .arrow').classList.add('open');
            <?php endif; ?>
        <?php endif; ?>
    </script>

    <?php mysqli_close($conn); ?>
</body>

</html>