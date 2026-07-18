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

function sendApplicationApproved($email, $name)
{
    $subject = "Congratulations! Your Application Has Been Approved";

    $message = "
    Hello $name,

    Congratulations!

    Your adoption application has been approved.

    Please log in to AniPet to view the next steps.

    Regards,
    AniPet Team
    ";

    return sendEmail($email, $subject, nl2br($message));
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