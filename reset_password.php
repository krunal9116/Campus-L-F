<?php
session_start();

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

require_once __DIR__ . '/config.php';
$error = '';
$success = '';

// Verify OTP + set new password
if (isset($_POST['reset'])) {
    $email = mysqli_real_escape_string($conn, $_SESSION['reset_email']);
    $otp = mysqli_real_escape_string($conn, trim($_POST['otp']));
    $pass = $_POST['password'];
    $pass2 = $_POST['password2'];

    if (empty($otp) || empty($pass)) {
        $error = "All fields are required.";
    } elseif ($pass !== $pass2) {
        $error = "Passwords do not match.";
    } elseif (strlen($pass) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $result = mysqli_query($conn, "SELECT * FROM otp_verification WHERE email='$email' AND otp='$otp' AND is_verified=0 ORDER BY created_at DESC LIMIT 1");
        if (mysqli_num_rows($result) === 0) {
            $error = "Invalid OTP. Please check and try again.";
        } else {
            $row = mysqli_fetch_assoc($result);
            if (strtotime($row['expires_at']) < time()) {
                mysqli_query($conn, "DELETE FROM otp_verification WHERE id='" . $row['id'] . "'");
                $error = "OTP has expired. Please request a new one.";
            } else {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE email='$email'");
                mysqli_query($conn, "DELETE FROM otp_verification WHERE email='$email'");
                unset($_SESSION['reset_email']);
                $success = "Password reset successfully! Redirecting to login...";
                header("Refresh: 2; URL=index.php");
            }
        }
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Campus Find</title>
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
            background: linear-gradient(135deg, #1a472a, #2d8a4e);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #div {
            background: white;
            padding: 40px 36px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        h3 {
            font-family: 'Righteous', sans-serif;
            font-size: 28px;
            color: #159f35;
            margin-bottom: 6px;
            letter-spacing: 1px;
        }

        p.subtitle {
            color: #888;
            font-size: 13px;
            margin-bottom: 24px;
        }

        .email-badge {
            background: #f0fff4;
            border: 1px solid #b2dfdb;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 13px;
            color: #159f35;
            margin-bottom: 20px;
        }

        .error-msg {
            color: #e53935;
            background: #fff5f5;
            border: 1px solid #ffcdd2;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .success-msg {
            color: #159f35;
            background: #f0fff4;
            border: 1px solid #b2dfdb;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            outline: none;
            transition: border-color 0.2s;
            margin-bottom: 14px;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #159f35;
        }

        input[type="submit"] {
            width: 100%;
            padding: 12px;
            background: #159f35;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        input[type="submit"]:hover {
            background: #0d7a28;
        }

        .back-link {
            margin-top: 18px;
            font-size: 13px;
            color: #888;
        }

        .back-link a {
            color: #159f35;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        label {
            display: block;
            text-align: left;
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }
    </style>
</head>

<body>
    <div id="div">
        <h3>Campus-Find</h3>
        <p class="subtitle">Enter the OTP sent to your email and set a new password</p>
        <div class="email-badge">📧 <?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></div>

        <?php if ($error): ?>
            <p class="error-msg">❌ <?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success-msg">✅ <?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <form action="" method="post">
            <label>OTP Code</label>
            <input type="text" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" required />
            <label>New Password</label>
            <input type="password" name="password" placeholder="New password (min 8 chars)" required />
            <label>Confirm Password</label>
            <input type="password" name="password2" placeholder="Re-enter new password" required />
            <input type="submit" value="Reset Password" name="reset" />
        </form>
        <div class="back-link">
            <a href="forgot_password.php">← Resend OTP</a> &nbsp;|&nbsp; <a href="index.php">Login</a>
        </div>
    </div>
</body>

</html>