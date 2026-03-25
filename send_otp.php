<?php
session_start();

// Show all errors (for debugging)
error_reporting(E_ALL);
ini_set('display_errors', 0);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


require 'PHPMailer-7.0.2/src/Exception.php';
require 'PHPMailer-7.0.2/src/PHPMailer.php';
require 'PHPMailer-7.0.2/src/SMTP.php';
require_once 'config.php';

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed!']);
    exit();
}

header('Content-Type: application/json');

// Get data from form
$username = isset($_POST['username']) ? mysqli_real_escape_string($conn, $_POST['username']) : '';
$email = isset($_POST['email']) ? mysqli_real_escape_string($conn, $_POST['email']) : '';

// Check if data received
if (empty($username) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Username and Email are required!']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format!']);
    exit();
}

// Check if username already exists
$check_user = "SELECT * FROM users WHERE username = '$username'";
$result = mysqli_query($conn, $check_user);

if (mysqli_num_rows($result) > 0) {
    echo json_encode(['success' => false, 'message' => 'Username already exists!']);
    exit();
}

// Check if email already registered
$check_email = "SELECT * FROM users WHERE email = '$email'";
$result = mysqli_query($conn, $check_email);

if (mysqli_num_rows($result) > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered!']);
    exit();
}

// Generate 6-digit OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Delete old OTPs for this email
$delete_old = "DELETE FROM otp_verification WHERE email = '$email'";
mysqli_query($conn, $delete_old);

// Save OTP (expires in 5 minutes)
$expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
$insert_otp = "INSERT INTO otp_verification (email, otp, expires_at) VALUES ('$email', '$otp', '$expires_at')";

if (!mysqli_query($conn, $insert_otp)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save OTP. DB Error: ' . mysqli_error($conn)]);
    exit();
}

// Send OTP via email
$mail = new PHPMailer(true);

try {
    // SMTP Settings
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = MAIL_PORT;

    // Email Settings
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress($email);

    // Email Content
    $mail->isHTML(true);
    $mail->Subject = 'Your OTP for Campus Find Registration';
    $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px; border: 1px solid #eee; border-radius: 10px;">
            
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #159f35; margin: 0;">Campus Find</h1>
                <p style="color: #999; font-size: 14px;">Lost & Found Management System</p>
            </div>

            <p style="color: #333; font-size: 16px;">Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
            
            <p style="color: #666; font-size: 14px;">
                You have requested to register on Campus Find. 
                Please use the following OTP to verify your email:
            </p>

            <div style="background-color: #f0f2f5; padding: 25px; text-align: center; border-radius: 10px; margin: 25px 0;">
                <h1 style="color: #159f35; letter-spacing: 12px; margin: 0; font-size: 36px;">' . $otp . '</h1>
            </div>

            <p style="color: #666; font-size: 14px;">
                This OTP is valid for <strong>5 minutes</strong> only.
            </p>

            <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;">

            <p style="color: #999; font-size: 12px; text-align: center;">
                If you did not request this OTP, please ignore this email.<br>
                Do not share this OTP with anyone.
            </p>

            <div style="text-align: center; margin-top: 20px;">
                <p style="color: #159f35; font-size: 12px; font-weight: bold;">
                    Campus Find - All Rights Reserved
                </p>
                <p style="color: #999; font-size: 12px;">If any query Contact Us: campusfind3@gmail.com</p>
            </div>
        </div>
    ';

    $mail->AltBody = 'Hello ' . $username . ', Your OTP for Campus Find registration is: ' . $otp . '. This OTP is valid for 5 minutes.';

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent to ' . $email]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Email Error: ' . $mail->ErrorInfo]);
}

mysqli_close($conn);
?>