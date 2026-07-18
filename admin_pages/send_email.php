<?php

require_once __DIR__ . '/../smtp_config.php';
require_once PHPMailer_AUTOLOAD;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $message, $isHtml = true)
{
    try {
        $mail = new PHPMailer(true);

        if (USE_SMTP) {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
        }

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML($isHtml);

        $mail->Subject = $subject;
        $mail->Body    = $message;

        // Fallback for email clients that don't support HTML
        $mail->AltBody = strip_tags(str_replace(
            ['<br>', '<br/>', '<br />'],
            PHP_EOL,
            $message
        ));

        $mail->send();

        return [
            'success' => true,
            'message' => 'Email sent successfully.'
        ];

    } catch (Exception $e) {

        error_log('PHPMailer Error: ' . $mail->ErrorInfo);

        return [
            'success' => false,
            'message' => $mail->ErrorInfo
        ];
    }
}