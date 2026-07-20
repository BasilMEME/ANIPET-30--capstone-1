<?php

require_once __DIR__ . '/../smtp_config.php';

if (
    defined('PHPMailer_AUTOLOAD') &&
    file_exists(PHPMailer_AUTOLOAD)
) {
    require_once PHPMailer_AUTOLOAD;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('sendEmail')) {
    function sendEmail(
        string $to,
        string $subject,
        string $message,
        bool $isHtml = true
    ): array {
        $mail = null;

        try {
            $mail = new PHPMailer(true);

            if (defined('USE_SMTP') && USE_SMTP) {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure =
                    PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;
                $mail->CharSet = 'UTF-8';

                $mail->Timeout = 20;
                $mail->Timelimit = 20;
                $mail->SMTPKeepAlive = false;
            }

            $mail->setFrom(
                SMTP_FROM_EMAIL,
                SMTP_FROM_NAME
            );

            $mail->addAddress($to);
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $message;

            $mail->AltBody = strip_tags(
                str_replace(
                    ['<br>', '<br/>', '<br />'],
                    PHP_EOL,
                    $message
                )
            );

            $mail->send();

            return [
                'success' => true,
                'message' => 'Email sent successfully.'
            ];
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();

            if (
                $mail instanceof PHPMailer &&
                $mail->ErrorInfo !== ''
            ) {
                $errorMessage = $mail->ErrorInfo;
            }

            error_log(
                'PHPMailer error: ' . $errorMessage
            );

            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }
    }
}