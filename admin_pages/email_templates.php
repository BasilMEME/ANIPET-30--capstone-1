<?php

require_once __DIR__ . '/send_email.php';

function sendApplicationReceived($email, $name)
{
    $subject = "AniPet Application Received";

    $message = "
    Hello $name,

    Thank you for applying to adopt a pet through AniPet.

    We have successfully received your application and it is now under review.

    We will notify you once there is an update.

    Regards,
    AniPet Team
    ";

    return sendEmail($email, $subject, nl2br($message));
}

function sendApplicationApproved(
    $email,
    $name,
    $qrUrl = null,
    $petName = null
)
{
    $subject = "Congratulations! Your Adoption Application Has Been Approved";

    $message = "
    <h2>Congratulations {$name}!</h2>

    <p>
    We are pleased to inform you that your adoption application has been approved.
    </p>";

    if ($petName) {
        $message .= "
        <p>
        Pet: <strong>{$petName}</strong>
        </p>";
    }

    if ($qrUrl) {
        $message .= "
        <p>
        Please use the QR Code below when visiting the shelter.
        </p>

        <p>
        <a href='{$qrUrl}'>
        View Your QR Code
        </a>
        </p>";
    }

    $message .= "
    <br>

    <p>
    Thank you for choosing AniPet.
    </p>";

    return sendEmail(
        $email,
        $subject,
        $message,
        true
    );
}

function sendApplicationRejected($email, $name)
{
    $subject = "Update on Your Adoption Application";

    $message = "
    Hello $name,

    Thank you for your interest in AniPet.

    Unfortunately, your application was not approved at this time.

    We encourage you to apply again in the future.

    Regards,
    AniPet Team
    ";

    return sendEmail($email, $subject, nl2br($message));
}

function sendInterviewSchedule($email, $name, $date, $time)
{
    $subject = "AniPet Interview Schedule";

    $message = "
    Hello $name,

    Your adoption interview has been scheduled.

    Date: $date

    Time: $time

    Please arrive at least 15 minutes early.

    Regards,
    AniPet Team
    ";

    return sendEmail($email, $subject, nl2br($message));
}

function sendReadyForPickup($email, $name)
{
    $subject = "Your Pet is Ready for Pickup";

    $message = "
    Hello $name,

    Great news!

    Your adopted pet is now ready for pickup.

    Please bring a valid ID when visiting the shelter.

    Regards,
    AniPet Team
    ";

    return sendEmail($email, $subject, nl2br($message));
}