<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-7.0.2/src/Exception.php';
require 'PHPMailer-7.0.2/src/PHPMailer.php';
require 'PHPMailer-7.0.2/src/SMTP.php';
require_once 'config.php';

$error = '';
$success = '';

if (isset($_POST['send_otp'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $result = mysqli_query($conn, "SELECT id, username FROM users WHERE email='$email'");
        if (mysqli_num_rows($result) === 0) {
            $error = "No account found with that email.";
        } else {
            $user = mysqli_fetch_assoc($result);
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            mysqli_query($conn, "DELETE FROM otp_verification WHERE email='$email'");
            mysqli_query($conn, "INSERT INTO otp_verification (email, otp, expires_at, is_verified) VALUES ('$email', '$otp', '$expires_at', 0)");

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = MAIL_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = MAIL_USERNAME;
                $mail->Password = MAIL_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = MAIL_PORT;
                $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset OTP - Campus Find';
                $mail->Body = '
                    <div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;border:1px solid #eee;border-radius:10px;">
                        <div style="text-align:center;margin-bottom:30px;">
                            <h1 style="color:#159f35;margin:0;">Campus Find</h1>
                            <p style="color:#999;font-size:14px;">Lost & Found Management System</p>
                        </div>
                        <p style="color:#333;font-size:16px;">Hello <strong>' . htmlspecialchars($user['username']) . '</strong>,</p>
                        <p style="color:#666;font-size:14px;">Use the OTP below to reset your password:</p>
                        <div style="background:#f0f2f5;padding:25px;text-align:center;border-radius:10px;margin:25px 0;">
                            <h1 style="color:#159f35;letter-spacing:12px;margin:0;font-size:36px;">' . $otp . '</h1>
                        </div>
                        <p style="color:#666;font-size:14px;">This OTP is valid for <strong>5 minutes</strong>.</p>
                        <p style="color:#999;font-size:12px;text-align:center;">If you did not request this, please ignore this email.</p>
                        <p style="color:#999;font-size:12px;text-align:center;">If any query Contact Us: campusfind3@gmail.com</p>
                    </div>';
                $mail->send();

                $_SESSION['reset_email'] = $email;
                $success = "OTP sent to your email. Redirecting...";
                header("Refresh: 2; URL=reset_password.php");
            } catch (Exception $e) {
                $error = "Failed to send OTP. Please try again.";
            }
        }
    }
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Campus Find</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Righteous&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background-image: url(images/forgetpassword.png);
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        #div {
            background-color: #ffffff;
            padding: 40px 36px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border: 2px solid black;
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

        input[type="email"] {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            outline: none;
            transition: border-color 0.2s;
            margin-bottom: 16px;
        }

        input[type="email"]:focus {
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
    </style>
</head>

<body>
    <div id="div">
        <h3>Campus-Find</h3>
        <p class="subtitle">Enter your registered email to receive a reset OTP</p>

        <?php if ($error): ?>
            <p class="error-msg">❌ <?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="success-msg">✅ <?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>

        <form action="" method="post">
            <input type="email" name="email" placeholder="Enter your email address" required />
            <input type="submit" value="Send OTP" name="send_otp" />
        </form>
        <div class="back-link">
            <a href="index.php">← Back to Login</a>
        </div>
    </div>
</body>

</html>