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
$otp = isset($_POST['otp']) ? mysqli_real_escape_string($conn, $_POST['otp']) : '';
$new_email = isset($_POST['new_email']) ? mysqli_real_escape_string($conn, trim($_POST['new_email'])) : '';

if (empty($otp) || empty($new_email)) {
    echo json_encode(['success' => false, 'message' => 'OTP and email are required!']);
    exit();
}

if (!preg_match('/^\d{6}$/', $otp)) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP format!']);
    exit();
}

// Check OTP in database
$check_otp = "SELECT * FROM otp_verification WHERE email = '$new_email' AND otp = '$otp' AND is_verified = 0 ORDER BY created_at DESC LIMIT 1";
$result = mysqli_query($conn, $check_otp);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP!']);
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

// Get user
$user_query = "SELECT * FROM users WHERE username = '$username'";
$user_result = mysqli_query($conn, $user_query);
$user_data = mysqli_fetch_assoc($user_result);
$user_id = $user_data['id'];
$old_email = $user_data['email'];

// Check if email already taken by someone else
$check_email = "SELECT * FROM users WHERE email = '$new_email' AND id != '$user_id'";
if (mysqli_num_rows(mysqli_query($conn, $check_email)) > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already in use!']);
    exit();
}

// Update email
$update = "UPDATE users SET email = '$new_email' WHERE id = '$user_id'";

if (mysqli_query($conn, $update)) {
    $_SESSION['email'] = $new_email;

    // Cleanup OTPs
    $cleanup = "DELETE FROM otp_verification WHERE email = '$new_email'";
    mysqli_query($conn, $cleanup);

    // ========================
    // Send email to NEW email
    // ========================
    $mail_new = new PHPMailer(true);

    try {
        $mail_new->isSMTP();
        $mail_new->Host = MAIL_HOST;
        $mail_new->SMTPAuth = true;
        $mail_new->Username = MAIL_USERNAME;
        $mail_new->Password = MAIL_PASSWORD;
        $mail_new->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail_new->Port = MAIL_PORT;

        $mail_new->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail_new->addAddress($new_email);

        $mail_new->isHTML(true);
        $mail_new->Subject = 'Email Changed Successfully - Campus Find';
        $mail_new->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px; border: 1px solid #eee; border-radius: 10px;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #159f35; margin: 0;">Campus Find</h1>
                    <p style="color: #999; font-size: 14px;">Email Change Confirmation</p>
                </div>

                <p style="color: #333; font-size: 16px;">Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
                
                <p style="color: #666; font-size: 14px;">
                    Your email address has been <strong>successfully changed</strong> on Campus Find.
                </p>

                <div style="background-color: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #c3e6cb;">
                    <p style="margin: 0; color: #155724; font-size: 14px;">
                        ✅ <strong>This is your new registered email.</strong>
                    </p>
                </div>

                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="margin: 5px 0; font-size: 14px; color: #555;">
                        <strong>Previous Email:</strong> ' . htmlspecialchars($old_email) . '
                    </p>
                    <p style="margin: 5px 0; font-size: 14px; color: #555;">
                        <strong>New Email:</strong> ' . htmlspecialchars($new_email) . '
                    </p>
                    <p style="margin: 5px 0; font-size: 14px; color: #555;">
                        <strong>Changed On:</strong> ' . date('d M Y, h:i A') . '
                    </p>
                </div>

                <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;">

                <p style="color: #e74c3c; font-size: 13px;">
                    If you did not make this change, please contact us immediately. If any query Contact Us: campusfind3@gmail.com
                </p>

                <p style="color: #999; font-size: 12px; text-align: center; margin-top: 20px;">
                    © ' . date('Y') . ' Campus Find. All rights reserved.
                </p>
            </div>
        ';

        $mail_new->send();
    } catch (Exception $e) {
        // Email send failed but email was still updated
    }

    // ========================
    // Send email to OLD email
    // ========================
    $mail_old = new PHPMailer(true);

    try {
        $mail_old->isSMTP();
        $mail_old->Host = MAIL_HOST;
        $mail_old->SMTPAuth = true;
        $mail_old->Username = MAIL_USERNAME;
        $mail_old->Password = MAIL_PASSWORD;
        $mail_old->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail_old->Port = MAIL_PORT;

        $mail_old->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail_old->addAddress($old_email);

        $mail_old->isHTML(true);
        $mail_old->Subject = 'Your Email Was Changed - Campus Find';
        $mail_old->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 30px; border: 1px solid #eee; border-radius: 10px;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="color: #159f35; margin: 0;">Campus Find</h1>
                    <p style="color: #999; font-size: 14px;">Security Alert</p>
                </div>

                <p style="color: #333; font-size: 16px;">Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
                
                <p style="color: #666; font-size: 14px;">
                    The email address associated with your Campus Find account has been changed.
                </p>

                <div style="background-color: #fff3cd; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #ffeeba;">
                    <p style="margin: 0; color: #856404; font-size: 14px;">
                        ⚠️ <strong>This email is no longer linked to your account.</strong>
                    </p>
                </div>

                <div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <p style="margin: 5px 0; font-size: 14px; color: #555;">
                        <strong>Previous Email:</strong> ' . htmlspecialchars($old_email) . ' (this email)
                    </p>
                    <p style="margin: 5px 0; font-size: 14px; color: #555;">
                        <strong>New Email:</strong> ' . htmlspecialchars($new_email) . '
                    </p>
                    <p style="margin: 5px 0; font-size: 14px; color: #555;">
                        <strong>Changed On:</strong> ' . date('d M Y, h:i A') . '
                    </p>
                </div>

                <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;">

                <p style="color: #e74c3c; font-size: 13px;">
                    If you did NOT make this change, Your Account Maybe In Danger.<br> 
                    If any query Contact Us: campusfind3@gmail.com
                </p>

                <p style="color: #999; font-size: 12px; text-align: center; margin-top: 20px;">
                    © ' . date('Y') . ' Campus Find. All rights reserved.
                </p>
            </div>
        ';

        $mail_old->send();
    } catch (Exception $e) {
        // Email send failed but email was still updated
    }

    echo json_encode(['success' => true, 'message' => 'Email updated successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update email.']);
}

mysqli_close($conn);
?>