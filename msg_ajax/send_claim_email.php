<?php
/**
 * Sends an HTML email via PHPMailer (Gmail SMTP).
 * Usage: sendClaimEmail($toEmail, $toName, $subject, $bodyHtml)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer-7.0.2/src/Exception.php';
require_once __DIR__ . '/../PHPMailer-7.0.2/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-7.0.2/src/SMTP.php';
require_once __DIR__ . '/../config.php';

function sendClaimEmail($toEmail, $toName, $subject, $bodyHtml)
{
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
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->AltBody = strip_tags($bodyHtml);
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>