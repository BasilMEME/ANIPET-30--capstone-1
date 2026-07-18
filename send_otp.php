<?php
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

require_once "db_connect.php";
require_once __DIR__ . '/smtp_config.php';

$email = trim($_POST['email'] ?? '');
if (empty($email)) {
    exit(json_encode(['status' => 'error', 'message' => 'Email required']));
}

// Generate random 6-digit OTP
$otp = sprintf("%06d", mt_rand(0, 999999));

// Clean + Insert (use prepared statement for security)
$stmt = $conn->prepare("DELETE FROM otps WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

$stmt = $conn->prepare("INSERT INTO otps (email, otp, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
$stmt->bind_param("ss", $email, $otp);
$stmt->execute();

$sent = false;
$sendError = '';

if (defined('USE_SMTP') && USE_SMTP) {
    // Try to send via PHPMailer
    if (file_exists(PHPMailer_AUTOLOAD)) {
        require PHPMailer_AUTOLOAD;
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Your Anipet OTP';
            $mail->Body = "Your OTP code: <b>{$otp}</b>. It expires in 5 minutes.";

            $mail->send();
            $sent = true;
        } catch (Exception $e) {
            $sendError = $e->getMessage();
            error_log('send_otp.php mail error: ' . $sendError);
        }
    } else {
        $sendError = 'PHPMailer autoload not found; run composer require phpmailer/phpmailer in php-backend';
        error_log('send_otp.php: ' . $sendError);
    }
}

// If SMTP not configured or sending failed, log OTP to server file for debugging
if (!$sent) {
    $logLine = date('c') . "\t$email\t$otp\n";
    file_put_contents(__DIR__ . '/otp_debug.log', $logLine, FILE_APPEND | LOCK_EX);
}

if (defined('USE_SMTP') && USE_SMTP) {
    if ($sent) {
        echo json_encode([
            'status' => 'success',
            'message' => 'OTP emailed successfully.',
            'debug' => 'emailed'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'OTP email failed to send: ' . ($sendError ?: 'unknown smtp failure'),
            'debug' => $sendError ?: 'unknown smtp failure'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'success',
        'message' => 'OTP generated and logged for debugging.',
        'debug' => 'logged'
    ]);
}