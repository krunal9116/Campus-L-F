<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer-7.0.2/src/Exception.php';
require_once 'PHPMailer-7.0.2/src/PHPMailer.php';
require_once 'PHPMailer-7.0.2/src/SMTP.php';

require_once __DIR__ . '/config.php';
$message = '';
$msg_type = '';

// ─── CSRF Token ───
if (empty($_SESSION['boss_csrf_token'])) {
    $_SESSION['boss_csrf_token'] = bin2hex(random_bytes(32));
}

// ─── Handle Login ───
if (isset($_POST['boss_login'])) {
    if ($_POST['boss_user'] === BOSS_USERNAME && $_POST['boss_pass'] === BOSS_PASSWORD) {
        $_SESSION['boss_logged_in'] = true;
    } else {
        $message = 'Invalid credentials!';
        $msg_type = 'error';
    }
}

// ─── Handle Logout ───
if (isset($_GET['logout'])) {
    $_SESSION['boss_logged_in'] = false;
    unset($_SESSION['boss_logged_in']);
    header("Location: boss.php");
    exit();
}

// ─── Handle Approve / Reject ───
if (isset($_POST['action']) && isset($_SESSION['boss_logged_in']) && $_SESSION['boss_logged_in']) {
    if (!isset($_POST['boss_csrf_token']) || $_POST['boss_csrf_token'] !== $_SESSION['boss_csrf_token']) {
        die('Invalid CSRF token.');
    }
    $user_id = intval($_POST['user_id']);
    $action = $_POST['action'];

    $user_data = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT username, email FROM users WHERE id = '$user_id' AND role = 'pending_admin' LIMIT 1"
    ));

    if ($user_data) {
        $uname = $user_data['username'];
        $uemail = $user_data['email'];

        function sendBossEmail($to, $name, $subject, $body)
        {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = MAIL_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = MAIL_USERNAME;
                $mail->Password = MAIL_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = MAIL_PORT;
                $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                $mail->addAddress($to, $name);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->send();
            } catch (Exception $e) {
            }
        }

        if ($action === 'approve') {
            mysqli_query($conn, "UPDATE users SET role = 'admin' WHERE id = '$user_id'");
            sendBossEmail(
                $uemail,
                $uname,
                'Admin Request Approved - Campus Find',
                '<div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;border:1px solid #eee;border-radius:10px;">
                    <h2 style="color:#159f35;">Admin Request Approved</h2>
                    <p>Hello <strong>' . htmlspecialchars($uname) . '</strong>,</p>
                    <p>Congratulations! Your request to become an admin on <strong>Campus Find</strong> has been <span style="color:#159f35;font-weight:bold;">APPROVED</span>.</p>
                    <p>You can now log in with your registered credentials and you will be redirected to the Admin Dashboard.</p>
                    <p style="color:#999;font-size:12px;">If any query Contact Us: campusfind3@gmail.com</p>
                </div>'
            );
            $message = htmlspecialchars($uname) . " has been approved as admin. Email sent.";
            $msg_type = 'success';

        } elseif ($action === 'reject') {
            $email_safe = mysqli_real_escape_string($conn, $uemail);
            sendBossEmail(
                $uemail,
                $uname,
                'Admin Request Rejected - Campus Find',
                '<div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;border:1px solid #eee;border-radius:10px;">
                    <h2 style="color:#dc2626;">Admin Request Rejected</h2>
                    <p>Hello <strong>' . htmlspecialchars($uname) . '</strong>,</p>
                    <p>We regret to inform you that your request to become an admin on <strong>Campus Find</strong> has been <span style="color:#dc2626;font-weight:bold;">REJECTED</span>.</p>
                    <p>Your account and credentials have been removed from the system.</p>
                    <p style="color:#999;font-size:12px;">If any query Contact Us: campusfind3@gmail.com</p>
                </div>'
            );
            // Delete account and OTPs
            mysqli_query($conn, "DELETE FROM otp_verification WHERE email = '$email_safe'");
            mysqli_query($conn, "DELETE FROM users WHERE id = '$user_id'");
            $message = htmlspecialchars($uname) . " has been rejected and account deleted. Email sent.";
            $msg_type = 'error';
        }
    }
}

// ─── Fetch pending admin requests ───
$pending = [];
if (isset($_SESSION['boss_logged_in']) && $_SESSION['boss_logged_in']) {
    $res = mysqli_query($conn, "SELECT id, username, email, created_at FROM users WHERE role = 'pending_admin' ORDER BY created_at ASC");
    while ($row = mysqli_fetch_assoc($res))
        $pending[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boss Panel - Campus Find</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Righteous&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #0f0f1a;
            min-height: 100vh;
        }

        /* ══════════════════════════════
           LOGIN PAGE
        ══════════════════════════════ */
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a3e 50%, #0f0f1a 100%);
            position: relative;
            overflow: hidden;
        }

        .login-page::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
            top: -100px;
            left: -100px;
            border-radius: 50%;
        }

        .login-page::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%);
            bottom: -50px;
            right: -50px;
            border-radius: 50%;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 48px 42px;
            border-radius: 24px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            position: relative;
            z-index: 1;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5);
        }

        .login-logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }

        .login-card h2 {
            font-family: 'Righteous', cursive;
            color: #fff;
            font-size: 28px;
            margin-bottom: 6px;
            letter-spacing: 1px;
        }

        .login-card .subtitle {
            color: rgba(255, 255, 255, 0.4);
            font-size: 13px;
            margin-bottom: 32px;
        }

        .input-group {
            position: relative;
            margin-bottom: 16px;
            text-align: left;
        }

        .input-group label {
            display: block;
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .input-group input {
            width: 100%;
            padding: 13px 16px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }

        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.25);
        }

        .input-group input:focus {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.08);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            margin-top: 8px;
            letter-spacing: 0.5px;
            transition: opacity 0.2s, transform 0.1s;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .login-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-error {
            background: rgba(220, 38, 38, 0.15);
            border: 1px solid rgba(220, 38, 38, 0.4);
            color: #fca5a5;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        /* ══════════════════════════════
           PANEL PAGE
        ══════════════════════════════ */
        .panel-page {
            background: #f1f5f9;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 240px;
            height: 100vh;
            background: linear-gradient(180deg, #1a1a3e 0%, #0f0f1a 100%);
            display: flex;
            flex-direction: column;
            padding: 28px 0;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
            z-index: 100;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px 28px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 20px;
        }

        .sidebar-logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .sidebar-logo-text {
            font-family: 'Righteous', cursive;
            color: #fff;
            font-size: 17px;
            letter-spacing: 0.5px;
        }

        .sidebar-logo-text span {
            display: block;
            font-family: 'Poppins', sans-serif;
            font-size: 10px;
            color: rgba(255, 255, 255, 0.35);
            font-weight: 400;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .sidebar-menu {
            flex: 1;
            padding: 0 12px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.55);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 4px;
        }

        .menu-item.active {
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
        }

        .menu-item .menu-icon {
            font-size: 17px;
            width: 22px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 16px 12px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 10px;
            color: rgba(255, 100, 100, 0.7);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
        }

        .logout-btn:hover {
            background: rgba(220, 38, 38, 0.12);
            color: #fca5a5;
        }

        /* Main content */
        .main {
            margin-left: 240px;
            padding: 32px;
            min-height: 100vh;
        }

        /* Top bar */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .topbar h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
        }

        .topbar .date {
            font-size: 13px;
            color: #475569;
        }

        /* Stats row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .stat-icon.purple {
            background: rgba(99, 102, 241, 0.12);
        }

        .stat-icon.green {
            background: rgba(16, 185, 129, 0.12);
        }

        .stat-icon.red {
            background: rgba(239, 68, 68, 0.12);
        }

        .stat-label {
            font-size: 12px;
            color: #475569;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }

        /* Requests table */
        .requests-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        .requests-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .requests-header h2 {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
        }

        .badge-pill {
            background: #fef3c7;
            color: #92400e;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .badge-pill.none {
            background: #d1fae5;
            color: #065f46;
        }

        .request-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid #f8fafc;
            transition: background 0.15s;
            gap: 16px;
        }

        .request-row:last-child {
            border-bottom: none;
        }

        .request-row:hover {
            background: #f8fafc;
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        .user-details {
            flex: 1;
        }

        .user-details .name {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .user-details .email {
            font-size: 12px;
            color: #475569;
            margin-top: 2px;
        }

        .user-details .req-date {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }

        .status-badge {
            background: #fef9c3;
            color: #a16207;
            border: 1px solid #fde68a;
            font-size: 11px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: opacity 0.2s, transform 0.1s;
            box-shadow: 0 3px 10px rgba(16, 185, 129, 0.3);
        }

        .btn-approve:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: opacity 0.2s, transform 0.1s;
            box-shadow: 0 3px 10px rgba(239, 68, 68, 0.3);
        }

        .btn-reject:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state .empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .empty-state p {
            font-size: 15px;
        }

        /* Toast notification */
        .toast {
            position: fixed;
            top: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
            max-width: 360px;
        }

        .toast.success {
            background: #fff;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .toast.error {
            background: #fff;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        @keyframes slideIn {
            from {
                transform: translateX(120px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .request-row {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>

    <?php if (!isset($_SESSION['boss_logged_in']) || !$_SESSION['boss_logged_in']): ?>

        <!-- ════════════════════════════════
     LOGIN PAGE
════════════════════════════════ -->
        <div class="login-page">
            <div class="login-card">
                <div class="login-logo">👑</div>
                <h2>Boss Panel</h2>
                <p class="subtitle">Campus Find — Upper Level Access Only</p>

                <?php if ($message): ?>
                    <div class="login-error">⚠️ <?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="boss_user" placeholder="Enter boss username" required autocomplete="off">
                    </div>
                    <div class="input-group">
                        <label>Password</label>
                        <input type="password" name="boss_pass" placeholder="Enter boss password" required>
                    </div>
                    <button type="submit" name="boss_login" class="login-btn">🔐 LOGIN TO BOSS PANEL</button>
                </form>
            </div>
        </div>

    <?php else:
        $total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role != 'pending_admin'"))['c'];
        $total_admins = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE role = 'admin'"))['c'];
        ?>

        <!-- ════════════════════════════════
     PANEL PAGE
════════════════════════════════ -->
        <div class="panel-page">

            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-logo">
                    <div class="sidebar-logo-icon">👑</div>
                    <div class="sidebar-logo-text">
                        Boss Panel
                        <span>Campus Find</span>
                    </div>
                </div>
                <div class="sidebar-menu">
                    <div class="menu-item active">
                        <span class="menu-icon">📋</span> Admin Requests
                    </div>
                </div>
                <div class="sidebar-footer">
                    <a href="?logout=1" class="logout-btn">
                        <span class="menu-icon">Logout</span>
                    </a>
                </div>
            </div>

            <!-- Main -->
            <div class="main">

                <!-- Topbar -->
                <div class="topbar">
                    <h1>Admin Access Requests</h1>
                    <div class="date"><?php echo date('l, d F Y'); ?></div>
                </div>

                <!-- Stats -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon purple">⏳</div>
                        <div>
                            <div class="stat-label">Pending Requests</div>
                            <div class="stat-value"><?php echo count($pending); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">👤</div>
                        <div>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-value"><?php echo $total_users; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red">🛡️</div>
                        <div>
                            <div class="stat-label">Total Admins</div>
                            <div class="stat-value"><?php echo $total_admins; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Requests Box -->
                <div class="requests-box">
                    <div class="requests-header">
                        <h2>Pending Admin Requests</h2>
                        <?php if (count($pending) > 0): ?>
                            <span class="badge-pill"><?php echo count($pending); ?> Pending</span>
                        <?php else: ?>
                            <span class="badge-pill none">All Clear</span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($pending)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">✅</div>
                            <p>No pending admin requests at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pending as $p): ?>
                            <div class="request-row">
                                <div class="avatar"><?php echo strtoupper(substr($p['username'], 0, 1)); ?></div>
                                <div class="user-details">
                                    <div class="name"><?php echo htmlspecialchars($p['username']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($p['email']); ?></div>
                                    <div class="req-date">Requested:
                                        <?php echo date('d M Y, h:i A', strtotime($p['created_at'])); ?></div>
                                </div>
                                <span class="status-badge">⏳ Pending</span>
                                <div class="actions">
                                    <form method="POST"
                                        onsubmit="return confirm('Approve <?php echo htmlspecialchars($p['username'], ENT_QUOTES); ?> as admin?')">
                                        <input type="hidden" name="boss_csrf_token" value="<?php echo $_SESSION['boss_csrf_token']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve">✔ Approve</button>
                                    </form>
                                    <form method="POST"
                                        onsubmit="return confirm('Reject and DELETE account of <?php echo htmlspecialchars($p['username'], ENT_QUOTES); ?>?')">
                                        <input type="hidden" name="boss_csrf_token" value="<?php echo $_SESSION['boss_csrf_token']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject">✘ Reject</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div><!-- /main -->
        </div><!-- /panel-page -->

        <?php if ($message): ?>
            <div class="toast <?php echo $msg_type; ?>" id="toast">
                <?php echo $msg_type === 'success' ? '✅' : '❌'; ?>
                <?php echo $message; ?>
            </div>
            <script>setTimeout(function () { document.getElementById('toast').style.opacity = '0'; document.getElementById('toast').style.transition = 'opacity 0.5s'; }, 3500);</script>
        <?php endif; ?>

    <?php endif; ?>
    <?php mysqli_close($conn); ?>
</body>

</html>