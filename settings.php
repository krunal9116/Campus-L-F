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
$user_query = "SELECT * FROM users WHERE username = '$username'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
if (!$user_data) {
    session_destroy();
    header("Location: index.php");
    exit();
}
$user_id = $user_data['id'];
$current_email = $user_data['email'];

$success_msg = "";
$error_msg = "";

// ========================
// CHANGE USERNAME
// ========================
if (isset($_POST['change_username'])) {
    $new_username = mysqli_real_escape_string($conn, trim($_POST['new_username']));

    if (empty($new_username)) {
        $error_msg = "Username cannot be empty!";
    } else if ($new_username == $username) {
        $error_msg = "New username is same as current!";
    } else {
        $check = "SELECT * FROM users WHERE username = '$new_username' AND id != '$user_id'";
        $check_result = mysqli_query($conn, $check);

        if (mysqli_num_rows($check_result) > 0) {
            $error_msg = "Username already taken!";
        } else {
            $update = "UPDATE users SET username = '$new_username' WHERE id = '$user_id'";
            if (mysqli_query($conn, $update)) {
                $update_claims = "UPDATE claims SET claimed_by = '$new_username' WHERE user_id = '$user_id'";
                mysqli_query($conn, $update_claims);

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
    } else if ($new_password !== $confirm_password) {
        $error_msg = "New passwords do not match!";
    } else if (strlen($new_password) < 8) {
        $error_msg = "Password must be at least 8 characters!";
    } else if (!preg_match('/[A-Z]/', $new_password)) {
        $error_msg = "Password must contain at least 1 uppercase letter!";
    } else if (!preg_match('/[a-z]/', $new_password)) {
        $error_msg = "Password must contain at least 1 lowercase letter!";
    } else if (!preg_match('/[0-9]/', $new_password)) {
        $error_msg = "Password must contain at least 1 number!";
    } else if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error_msg = "Password must contain at least 1 special character!";
    } else {
        if (password_verify($current_password, $user_data['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = "UPDATE users SET password = '$hashed' WHERE id = '$user_id'";
            if (mysqli_query($conn, $update)) {
                $success_msg = "Password updated successfully!";
                $user_result = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
                $user_data = mysqli_fetch_assoc($user_result);
            } else {
                $error_msg = "Failed to update password.";
            }
        } else {
            $error_msg = "Current password is incorrect!";
        }
    }
}

// ========================
// DELETE ACCOUNT
// ========================
if (isset($_POST['delete_account'])) {
    $confirm_password = $_POST['delete_password'];

    if (empty($confirm_password)) {
        $error_msg = "Password is required to delete account!";
    } else if (password_verify($confirm_password, $user_data['password'])) {
        // Delete in correct order to avoid FK constraint errors
        mysqli_query($conn, "DELETE FROM messages WHERE conversation_id IN (SELECT id FROM conversations WHERE user1_id='$user_id' OR user2_id='$user_id')");
        mysqli_query($conn, "DELETE FROM conversations WHERE user1_id='$user_id' OR user2_id='$user_id'");
        mysqli_query($conn, "DELETE FROM claims WHERE user_id='$user_id'");
        mysqli_query($conn, "DELETE FROM items WHERE user_id='$user_id'");
        // Delete profile photo
        if (!empty($user_data['profile_photo'])) {
            $old = 'uploads/profile_photos/' . $user_data['profile_photo'];
            if (file_exists($old)) unlink($old);
        }
        $delete_user = "DELETE FROM users WHERE id='$user_id'";
        if (mysqli_query($conn, $delete_user)) {
            session_destroy();
            header("Location: index.php");
            exit();
        } else {
            $error_msg = "Failed to delete account.";
        }
    } else {
        $error_msg = "Incorrect password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Campus Find</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dark-mode.css">
    <script src="dark-mode.js"></script>
    <script src="page-loader.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f0f2f5;
        }

        .navbar {
            background-color: #159f35;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .navbar h1 {
            font-size: 30px;
        }

        .back-btn {
            background-color: white;
            color: #159f35;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
        }

        .back-btn:hover {
            background-color: #e6f8e6;
        }

        .container {
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 25px;
            border-bottom: 3px solid #159f35;
            padding-bottom: 10px;
            display: inline-block;
        }

        .msg {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .msg.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .msg.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .settings-card {
            background-color: white;
            border-radius: 15px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            padding: 18px 25px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        .card-header:hover {
            background-color: #e9ecef;
        }

        .card-header h3 {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .card-header .icon {
            font-size: 20px;
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
            border-color: #159f35;
        }

        .form-group input:disabled {
            background-color: #f0f0f0;
            color: #999;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }

        .btn-green {
            background-color: #159f35;
            color: white;
        }

        .btn-green:hover {
            background-color: #035815;
        }

        .btn-red {
            background-color: #e74c3c;
            color: white;
        }

        .btn-red:hover {
            background-color: #c0392b;
        }

        .btn-blue {
            background-color: #007bff;
            color: white;
        }

        .btn-blue:hover {
            background-color: #0056b3;
        }

        .btn-gray {
            background-color: #6c757d;
            color: white;
        }

        .btn-gray:hover {
            background-color: #545b62;
        }

        .password-hint {
            font-size: 11px;
            text-align: left;
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
            border-color: #159f35;
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
            border-top: 3px solid #159f35;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .loading p {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }

        .delete-section {
            border-color: #f5c6cb;
        }

        .delete-section .card-header {
            background-color: #fff5f5;
        }

        .delete-section .card-header:hover {
            background-color: #ffe0e0;
        }

        .delete-warning {
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ffeeba;
            margin-bottom: 20px;
            font-size: 13px;
            color: #856404;
        }

        .delete-warning strong {
            display: block;
            margin-bottom: 5px;
            color: #e74c3c;
        }

        .delete-warning ul {
            margin-top: 8px;
            padding-left: 20px;
        }

        .delete-warning ul li {
            margin-bottom: 3px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }

        .modal-content h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .modal-content p {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-buttons button {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .email-display {
            color: #159f35;
            font-weight: 600;
        }

        .otp-msg {
            padding: 8px;
            border-radius: 8px;
            font-size: 13px;
            margin: 10px 0;
            display: none;
        }

        .otp-msg.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .otp-msg.success {
            background-color: #d4edda;
            color: #155724;
        }

        .verified-badge {
            background-color: #d4edda;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>

<body>

    <div class="navbar">
        <h1>Campus-Find</h1>
        <a href="user_dashboard.php" class="back-btn">← Back to Dashboard</a>
    </div>

    <div class="container">

        <h2 class="page-title">⚙️ Account Settings</h2>

        <?php if ($success_msg != "") { ?>
            <div class="msg success">✅ <?php echo $success_msg; ?></div>
        <?php } ?>

        <?php if ($error_msg != "") { ?>
            <div class="msg error">❌ <?php echo $error_msg; ?></div>
        <?php } ?>

        <!-- ======================== -->
        <!-- 1. CHANGE USERNAME -->
        <!-- ======================== -->
        <div class="settings-card">
            <div class="card-header" onclick="toggleSection('usernameSection', this)">
                <h3><span class="icon">👤</span> Change Username</h3>
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
                    <button type="submit" name="change_username" class="btn btn-green">Update Username</button>
                </form>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 2. CHANGE EMAIL -->
        <!-- ======================== -->
        <div class="settings-card">
            <div class="card-header" onclick="toggleSection('emailSection', this)">
                <h3><span class="icon">📧</span> Change Email</h3>
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
                        <input type="password" id="emailPassword" placeholder="Enter your password" required>
                    </div>
                    <button type="button" class="btn btn-green" id="verifyPassBtn" onclick="verifyPasswordForEmail()">🔒
                        Verify Password</button>

                    <div class="loading" id="passLoading">
                        <div class="spinner"></div>
                        <p>Verifying password...</p>
                    </div>

                    <div class="otp-msg" id="passMsg"></div>
                </div>

                <!-- Step 2: Enter New Email -->
                <div id="emailStep2" style="display: none;">
                    <div class="verified-badge">✅ Password verified! Now enter your new email.</div>
                    <div class="form-group">
                        <label>New Email</label>
                        <input type="email" id="newEmail" placeholder="Enter new email" required>
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
                    <p style="font-size: 14px; color: #666;">Enter OTP sent to <span class="email-display"
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

                    <div class="timer-text">
                        OTP expires in: <span id="emailCountdown">05:00</span>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="button" class="btn btn-green" onclick="verifyEmailOTP()">Verify & Update
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

        <!-- ======================== -->
        <!-- 3. CHANGE PASSWORD -->
        <!-- ======================== -->
        <div class="settings-card">
            <div class="card-header" onclick="toggleSection('passwordSection', this)">
                <h3><span class="icon">🔒</span> Change Password</h3>
                <span class="arrow">▼</span>
            </div>
            <div class="card-body" id="passwordSection">
                <form method="POST" id="passwordForm">
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
                            <span id="hint-special">✗ 1 special character (!@#$%^&*)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_new_password" id="confirmNewPassword"
                            placeholder="Confirm new password" required>
                        <p id="passMatchMsg" style="color: red; font-size: 12px; margin-top: 4px; display: none;">❌
                            Passwords do not match!</p>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-green">Update Password</button>
                </form>
            </div>
        </div>

        <!-- ======================== -->
        <!-- 4. DELETE ACCOUNT -->
        <!-- ======================== -->
        <div class="settings-card delete-section">
            <div class="card-header" onclick="toggleSection('deleteSection', this)">
                <h3><span class="icon">🗑️</span> Delete Account</h3>
                <span class="arrow">▼</span>
            </div>
            <div class="card-body" id="deleteSection">
                <div class="delete-warning">
                    <strong>⚠️ Warning: This action cannot be undone!</strong>
                    Deleting your account will permanently remove:
                    <ul>
                        <li>Your profile and login credentials</li>
                        <li>All your claims</li>
                        <li>You will not be able to recover this account</li>
                    </ul>
                </div>
                <button type="button" class="btn btn-red" onclick="showDeleteModal()">Delete My Account</button>
            </div>
        </div>

    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="border: 2px solid #e74c3c;">
            <h3>🗑️ Delete Account?</h3>
            <p>This is permanent! Enter your password to confirm.</p>
            <form method="POST">
                <div class="form-group" style="text-align: left;">
                    <label>Enter Password</label>
                    <input type="password" name="delete_password" placeholder="Enter your password" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-gray" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_account" class="btn btn-red">Yes, Delete Forever</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ========================
        // Toggle Sections
        // ========================
        function toggleSection(id, header) {
            var section = document.getElementById(id);
            var arrow = header.querySelector('.arrow');

            if (section.classList.contains('show')) {
                section.classList.remove('show');
                arrow.classList.remove('open');
            } else {
                section.classList.add('show');
                arrow.classList.add('open');
            }
        }

        // ========================
        // Password Strength Hints
        // ========================
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

            if (confirmNewPassword.value !== '' && val !== confirmNewPassword.value) {
                passMatchMsg.style.display = 'block';
            } else {
                passMatchMsg.style.display = 'none';
            }
        });

        confirmNewPassword.addEventListener('input', function () {
            if (newPassword.value !== this.value) {
                passMatchMsg.style.display = 'block';
            } else {
                passMatchMsg.style.display = 'none';
            }
        });

        function checkHint(id, valid) {
            var el = document.getElementById(id);
            var text = el.textContent.substring(2);
            if (valid) {
                el.textContent = '✓ ' + text;
                el.className = 'valid';
            } else {
                el.textContent = '✗ ' + text;
                el.className = 'invalid';
            }
        }

        // ========================
        // STEP 1: Verify Password for Email Change
        // ========================
        var emailPasswordVerified = false;

        function verifyPasswordForEmail() {
            var password = document.getElementById('emailPassword').value.trim();

            if (password === '') {
                showPassMsg('Please enter your password!', 'error');
                return;
            }

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
                    var response = JSON.parse(this.responseText);

                    if (response.success) {
                        emailPasswordVerified = true;
                        document.getElementById('emailStep1').style.display = 'none';
                        document.getElementById('emailStep2').style.display = 'block';
                    } else {
                        showPassMsg(response.message, 'error');
                    }
                } catch (e) {
                    showPassMsg('Something went wrong.', 'error');
                }
            };

            xhr.send('password=' + encodeURIComponent(password));
        }

        function showPassMsg(text, type) {
            var el = document.getElementById('passMsg');
            el.textContent = (type === 'error' ? '❌ ' : '✅ ') + text;
            el.className = 'otp-msg ' + type;
            el.style.display = 'block';
        }

        // ========================
        // STEP 2: Send OTP to New Email
        // ========================
        var emailTimerInterval;

        function sendEmailOTP() {
            if (!emailPasswordVerified) {
                alert('Please verify your password first!');
                return;
            }

            var newEmail = document.getElementById('newEmail').value.trim();

            if (newEmail === '') {
                alert('Please enter a new email!');
                return;
            }

            document.getElementById('emailLoading').style.display = 'block';
            document.getElementById('sendEmailOtpBtn').style.display = 'none';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'email_change/send_email_change_otp.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function () {
                document.getElementById('emailLoading').style.display = 'none';
                document.getElementById('sendEmailOtpBtn').style.display = 'inline-block';

                try {
                    var response = JSON.parse(this.responseText);

                    if (response.success) {
                        document.getElementById('emailStep2').style.display = 'none';
                        document.getElementById('emailStep3').style.display = 'block';
                        document.getElementById('newEmailDisplay').textContent = newEmail;
                        startEmailCountdown(300);
                        document.querySelectorAll('.email-otp')[0].focus();
                    } else {
                        alert(response.message);
                    }
                } catch (e) {
                    alert('Something went wrong. Please try again.');
                }
            };

            xhr.send('new_email=' + encodeURIComponent(newEmail));
        }

        // ========================
        // STEP 3: Verify OTP & Update Email
        // ========================
        function verifyEmailOTP() {
            var otp = '';
            document.querySelectorAll('.email-otp').forEach(function (input) {
                otp += input.value;
            });

            if (otp.length !== 6) {
                showOtpMsg('Please enter complete 6-digit OTP!', 'error');
                return;
            }

            var newEmail = document.getElementById('newEmail').value.trim();

            document.getElementById('emailVerifyLoading').style.display = 'block';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'email_change/verify_email_change_otp.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onload = function () {
                document.getElementById('emailVerifyLoading').style.display = 'none';

                try {
                    var response = JSON.parse(this.responseText);

                    if (response.success) {
                        showOtpMsg(response.message, 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        showOtpMsg(response.message, 'error');
                        document.querySelectorAll('.email-otp').forEach(function (input) {
                            input.value = '';
                        });
                        document.querySelectorAll('.email-otp')[0].focus();
                    }
                } catch (e) {
                    showOtpMsg('Something went wrong.', 'error');
                }
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
            document.querySelectorAll('.email-otp').forEach(function (input) {
                input.value = '';
            });
            document.getElementById('emailOtpMsg').style.display = 'none';
            document.getElementById('passMsg').style.display = 'none';
        }

        // ========================
        // OTP Input Handling
        // ========================
        var emailOtpInputs = document.querySelectorAll('.email-otp');

        emailOtpInputs.forEach(function (input, index) {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length === 1 && index < 5) {
                    emailOtpInputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    emailOtpInputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', function (e) {
                e.preventDefault();
                var paste = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                for (var i = 0; i < paste.length; i++) {
                    if (emailOtpInputs[i]) {
                        emailOtpInputs[i].value = paste[i];
                    }
                }
                if (paste.length > 0) {
                    emailOtpInputs[Math.min(paste.length, 5)].focus();
                }
            });
        });

        // ========================
        // Email Countdown
        // ========================
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

        // ========================
        // Delete Modal
        // ========================
        function showDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
    </script>

    <?php mysqli_close($conn); ?>
</body>

</html>