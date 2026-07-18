require_once 'send_email.php';

// Get applicant email
$email = $user['email'];
$name = $user['full_name'];

$subject = "AniPet - Application Approved";

$message = "
<h2>Congratulations, $name!</h2>

<p>Your adoption application has been approved.</p>

<p>Please log in to AniPet to view the next steps.</p>

<p>Thank you,<br>
AniPet Team</p>
";

sendEmail($email, $subject, $message);