<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
require_once __DIR__ . "/PHPMailer/src/SMTP.php";
require_once __DIR__ . "/PHPMailer/src/Exception.php";

require_once "company_settings.php";
require_once "env.php";

function sendMail($toEmail, $toName, $subject, $htmlBody)
{
    global $company_name;

    $requiredEnv = [
        'SMTP_HOST',
        'SMTP_PORT',
        'SMTP_USERNAME',
        'SMTP_PASSWORD',
        'SMTP_FROM_EMAIL'
    ];

    foreach ($requiredEnv as $key) {
        if (empty($_ENV[$key])) {
            error_log("Missing env variable: {$key}");
            return false;
        }
    }

    $mail = new PHPMailer(true);

    try {
        // SMTP CONFIG
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'];
        $mail->Password   = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$_ENV['SMTP_PORT'];

        // HEADERS
        $mail->setFrom($_ENV['SMTP_FROM_EMAIL'], $company_name);
        $mail->addReplyTo($_ENV['SMTP_FROM_EMAIL'], $company_name);
        $mail->addAddress($toEmail, $toName);

        // CONTENT
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail error: " . $mail->ErrorInfo);
        return false;
    }
}
