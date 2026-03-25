<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer-7.0.2/src/Exception.php';
require '../PHPMailer-7.0.2/src/PHPMailer.php';
require '../PHPMailer-7.0.2/src/SMTP.php';

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in!']);
    exit();
}

$username = $_SESSION['username'];
$new_email = isset($_POST['new_email']) ? mysqli_real_escape_string($conn, trim($_POST['new_email'])) : '';

if (empty($new_email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required!']);
    exit();
}

if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format!']);
    exit();
}

// Get current user data
$user_query = "SELECT * FROM users WHERE username = '$username'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);

// Check if new email is same as current
if ($new_email == $user_data['email']) {
    echo json_encode(['success' => false, 'message' => 'New email is same as current email!']);
    exit();
}

// Check if email already used by someone else
$check_email = "SELECT * FROM users WHERE email = '$new_email'";
$result = mysqli_query($conn, $check_email);

if (mysqli_num_rows($result) > 0) {
    echo json_encode(['success' => false, 'message' => 'This email is already registered!']);
    exit();
}

// Generate OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Delete old OTPs
$delete_old = "DELETE FROM otp_verification WHERE email = '$new_email'";
mysqli_query($conn, $delete_old);

// Save OTP
$expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
$insert_otp = "INSERT INTO otp_verification (email, otp, expires_at) VALUES ('$new_email', '$otp', '$expires_at')";
mysqli_query($conn, $insert_otp);

// Store new email in session for verification
$_SESSION['pending_email'] = $new_email;

// Send OTP
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
    $mail->addAddress($new_email);

    $mail->isHTML(true);
    $mail->Subject = 'OTP For Changing Email - Campus Find';
    $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px; border: 1px solid #eee; border-radius: 10px;">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #159f35; margin: 0;">Campus Find</h1>
                <p style="color: #999; font-size: 14px;">Email Change Verification</p>
            </div>

            <p style="color: #333; font-size: 16px;">Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
            
            <p style="color: #666; font-size: 14px;">
                You requested to change your email address. Use this OTP to verify your new email:
            </p>

            <div style="background-color: #f0f2f5; padding: 25px; text-align: center; border-radius: 10px; margin: 25px 0;">
                <h1 style="color: #159f35; letter-spacing: 12px; margin: 0; font-size: 36px;">' . $otp . '</h1>
            </div>

            <p style="color: #666; font-size: 14px;">
                This OTP is valid for <strong>5 minutes</strong> only.
            </p>

            <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;">

            <p style="color: #999; font-size: 12px; text-align: center;">
                If you did not request this, please ignore this email.
            </p>
            <p style="color: #999; font-size: 12px; text-align: center;">If any query Contact Us: campusfind3@gmail.com</p>
        </div>
    ';

    $mail->AltBody = 'Your OTP for email change is: ' . $otp . '. Valid for 5 minutes.';

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent to ' . $new_email]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP: ' . $mail->ErrorInfo]);
}

mysqli_close($conn);
?>