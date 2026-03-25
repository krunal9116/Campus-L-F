<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer-7.0.2/src/Exception.php';
require_once 'PHPMailer-7.0.2/src/PHPMailer.php';
require_once 'PHPMailer-7.0.2/src/SMTP.php';
require_once 'config.php';

header('Content-Type: application/json');

// Get data
$otp = isset($_POST['otp']) ? mysqli_real_escape_string($conn, $_POST['otp']) : '';
$email = isset($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : '';
$username = isset($_POST['username']) ? mysqli_real_escape_string($conn, $_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$requested_role = isset($_POST['role']) ? $_POST['role'] : 'user';
$role = ($requested_role === 'admin') ? 'pending_admin' : 'user';

// Validate inputs
if (empty($otp) || empty($email) || empty($username) || empty($password) || empty($role)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required!']);
    exit();
}

// Validate OTP format
if (!preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP format!']);
    exit();
}

// Check OTP in database
$check_otp = "SELECT * FROM otp_verification WHERE email = '$email' AND otp = '$otp' AND is_verified = 0 ORDER BY created_at DESC LIMIT 1";
$result = mysqli_query($conn, $check_otp);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP! Please check and try again.']);
    exit();
}

$otp_data = mysqli_fetch_assoc($result);

// Check if expired
if (strtotime($otp_data['expires_at']) < time()) {
    $delete_expired = "DELETE FROM otp_verification WHERE id = '" . $otp_data['id'] . "'";
    mysqli_query($conn, $delete_expired);
    echo json_encode(['success' => false, 'message' => 'OTP has expired! Please request a new one.']);
    exit();
}

// Mark OTP as verified
$update_otp = "UPDATE otp_verification SET is_verified = 1 WHERE id = '" . $otp_data['id'] . "'";
mysqli_query($conn, $update_otp);

// Double check username
$check_user = "SELECT * FROM users WHERE username = '$username'";
if (mysqli_num_rows(mysqli_query($conn, $check_user)) > 0) {
    echo json_encode(['success' => false, 'message' => 'Username already taken!']);
    exit();
}

// Double check email
$check_email = "SELECT * FROM users WHERE email = '$email'";
if (mysqli_num_rows(mysqli_query($conn, $check_email)) > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered!']);
    exit();
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Create user
$insert_user = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed_password', '$role')";

if (mysqli_query($conn, $insert_user)) {
    $new_user_id = mysqli_insert_id($conn);

    // Clean up OTPs
    $cleanup = "DELETE FROM otp_verification WHERE email = '$email'";
    mysqli_query($conn, $cleanup);

    if ($role === 'pending_admin') {
        // Send email to boss

        // Email to campusfind3@gmail.com (boss)
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = MAIL_PORT;
            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);            $mail->addAddress('campusfind3@gmail.com');
            $mail->isHTML(true);
            $mail->Subject = 'Admin Access Requested - Campus Find';
            $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;border:1px solid #eee;border-radius:10px;">
                <h2 style="color:#159f35;">Admin Access Request</h2>
                <p>A new admin registration request has been received.</p>
                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr><td style="padding:8px;color:#666;"><strong>Username:</strong></td><td style="padding:8px;">' . htmlspecialchars($username) . '</td></tr>
                    <tr><td style="padding:8px;color:#666;"><strong>Email:</strong></td><td style="padding:8px;">' . htmlspecialchars($email) . '</td></tr>
                </table>
                <p>Please visit the <strong>Boss Page</strong> to approve or reject this request.</p>
                <p style="color:#999;font-size:12px;">If any query Contact Us: campusfind3@gmail.com</p>
            </div>';
            $mail->send();
        } catch (Exception $e) {
        }

        // Email to user
        try {
            $mail2 = new PHPMailer(true);
            $mail2->isSMTP();
            $mail2->Host = MAIL_HOST;
            $mail2->SMTPAuth = true;
            $mail2->Username = MAIL_USERNAME;
            $mail2->Password = MAIL_PASSWORD;
            $mail2->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail2->Port = MAIL_PORT;
            $mail2->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail2->addAddress($email);
            $mail2->isHTML(true);
            $mail2->Subject = 'Admin Request Received - Campus Find';
            $mail2->Body = '<div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:30px;border:1px solid #eee;border-radius:10px;">
                <h2 style="color:#159f35;">Admin Request Received</h2>
                <p>Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
                <p>Your request to become an admin on Campus Find has been sent to the upper level for review.</p>
                <div style="background:#fff3cd;padding:15px;border-radius:8px;margin:20px 0;border:1px solid #ffc107;">
                    <strong>Please wait for approval or rejection.</strong><br>
                    You will receive an email once a decision is made.
                </div>
                <p style="color:#999;font-size:12px;">If any query Contact Us: campusfind3@gmail.com</p>
            </div>';
            $mail2->send();
        } catch (Exception $e) {
        }

        echo json_encode([
            'success' => true,
            'message' => 'Request sent! Please wait for approval from the upper level. You will be notified via email.',
            'redirect' => 'index.php',
            'pending' => true
        ]);

    } else {
        // Normal user — auto login
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Redirecting...',
            'redirect' => 'user_dashboard.php'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}

mysqli_close($conn);
?>