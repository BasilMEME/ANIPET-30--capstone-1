<?php
// SMTP configuration for sending OTP emails.
// Enable USE_SMTP and provide valid credentials to deliver OTPs via Gmail.
// For Gmail, configure an App Password and use smtp.gmail.com on port 587.
// If your account uses 2-step verification, normal Gmail passwords will fail.

define('USE_SMTP', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'anipet.adoption@gmail.com');
define('SMTP_PASS', 'ubqy lqbh jxfx vnny');
define('SMTP_FROM_EMAIL', 'anipet.adoption@gmail.com');
define('SMTP_FROM_NAME', 'Anipet');

// PHPMailer autoload path (if installed via Composer in this folder)
define('PHPMailer_AUTOLOAD', __DIR__ . '/vendor/autoload.php');

?>
