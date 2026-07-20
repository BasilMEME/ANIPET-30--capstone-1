<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

ob_start();

header('Content-Type: application/json; charset=utf-8');

function respond(array $response, int $httpCode = 200): never
{
    http_response_code($httpCode);

    if (ob_get_length() !== false && ob_get_length() > 0) {
        ob_clean();
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

try {
    require_once __DIR__ . '/db_connect.php';
    require_once __DIR__ . '/smtp_config.php';

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        respond([
            'status' => 'error',
            'message' => 'Email required'
        ], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond([
            'status' => 'error',
            'message' => 'Invalid email address'
        ], 400);
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    $otp = sprintf('%06d', random_int(0, 999999));

    $deleteStmt = $conn->prepare(
        'DELETE FROM otps WHERE email = ?'
    );

    if (!$deleteStmt) {
        throw new RuntimeException(
            'Failed to prepare OTP cleanup query: ' . $conn->error
        );
    }

    $deleteStmt->bind_param('s', $email);

    if (!$deleteStmt->execute()) {
        throw new RuntimeException(
            'Failed to remove previous OTP: ' . $deleteStmt->error
        );
    }

    $deleteStmt->close();

    $insertStmt = $conn->prepare(
        'INSERT INTO otps (email, otp, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))'
    );

    if (!$insertStmt) {
        throw new RuntimeException(
            'Failed to prepare OTP insert query: ' . $conn->error
        );
    }

    $insertStmt->bind_param('ss', $email, $otp);

    if (!$insertStmt->execute()) {
        throw new RuntimeException(
            'Failed to save OTP: ' . $insertStmt->error
        );
    }

    $insertStmt->close();

    $sent = false;
    $sendError = '';

    if (defined('USE_SMTP') && USE_SMTP) {
        if (
            !defined('PHPMailer_AUTOLOAD') ||
            !file_exists(PHPMailer_AUTOLOAD)
        ) {
            $sendError = 'PHPMailer autoload file was not found.';
        } else {
            require_once PHPMailer_AUTOLOAD;

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USER;
                $mail->Password = SMTP_PASS;
                $mail->SMTPSecure =
                    PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;

                $mail->Timeout = 20;
                $mail->Timelimit = 20;
                $mail->SMTPKeepAlive = false;

                $mail->CharSet = 'UTF-8';

                $mail->setFrom(
                    SMTP_FROM_EMAIL,
                    SMTP_FROM_NAME
                );

                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Your Anipet OTP';
                $mail->Body =
                    "Your OTP code is <b>{$otp}</b>. " .
                    "It expires in 5 minutes.";

                $mail->AltBody =
                    "Your OTP code is {$otp}. " .
                    "It expires in 5 minutes.";

                error_log(
                    "send_otp.php: attempting SMTP send to {$email}"
                );

                $mail->send();

                error_log(
                    "send_otp.php: SMTP send successful to {$email}"
                );

                $sent = true;
            } catch (Throwable $mailException) {
                $sendError =
                    $mail->ErrorInfo !== ''
                        ? $mail->ErrorInfo
                        : $mailException->getMessage();

                error_log(
                    'send_otp.php mail error: ' . $sendError
                );
            }
        }
    }

    if (!$sent) {
        $logLine =
            date('c') . "\t" .
            $email . "\t" .
            $otp . PHP_EOL;

        $logResult = @file_put_contents(
            __DIR__ . '/otp_debug.log',
            $logLine,
            FILE_APPEND | LOCK_EX
        );

        if ($logResult === false) {
            error_log(
                'send_otp.php: failed to write otp_debug.log'
            );
        }
    }

    if (defined('USE_SMTP') && USE_SMTP) {
        if ($sent) {
            respond([
                'status' => 'success',
                'message' => 'OTP emailed successfully.',
                'debug' => 'emailed'
            ]);
        }

        respond([
            'status' => 'error',
            'message' => 'OTP email failed to send.',
            'debug' => $sendError !== ''
                ? $sendError
                : 'Unknown SMTP failure'
        ], 500);
    }

    respond([
        'status' => 'success',
        'message' => 'OTP generated and logged for debugging.',
        'debug' => 'logged'
    ]);
} catch (Throwable $exception) {
    error_log(
        'send_otp.php fatal error: ' .
        $exception->getMessage()
    );

    respond([
        'status' => 'error',
        'message' => 'Unable to generate or send OTP.',
        'debug' => $exception->getMessage()
    ], 500);
}