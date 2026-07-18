<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_returns');

// Required text fields
$pet_name       = trim($_POST['pet_name'] ?? '');
$owner_name     = trim($_POST['owner_name'] ?? '');
$reason         = trim($_POST['reason'] ?? '');
$penalty_amount = $_POST['penalty_amount'] ?? '';
$species        = trim($_POST['species'] ?? '');
$breed          = trim($_POST['breed'] ?? '');
$age            = trim($_POST['age'] ?? '');
$gender         = trim($_POST['gender'] ?? '');
$health_status  = trim($_POST['health_status'] ?? '');

// Basic validation
if ($pet_name === '' || $owner_name === '' || $reason === '' ||
    $penalty_amount === '' ||
    $species === '' || $breed === '' || $age === '' ||
    $gender === '' || $health_status === '') {
    echo "All required fields must be filled in.";
    exit;
}

if (!is_numeric($penalty_amount) || $penalty_amount < 0) {
    echo "Penalty amount must be a valid number.";
    exit;
}

// Handle photo upload (optional)
$pet_photo = null;

if (!empty($_FILES['pet_photo']['name'])) {

    $uploadDir = __DIR__ . '/../images/pet_pound/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($_FILES['pet_photo']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        echo "Invalid photo file type.";
        exit;
    }

    $pet_photo = uniqid('pet_', true) . '.' . $ext;
    $destination = $uploadDir . $pet_photo;

    if (!move_uploaded_file($_FILES['pet_photo']['tmp_name'], $destination)) {
        echo "Failed to upload photo.";
        exit;
    }
}

// Impound timestamp is "now"; the owner then gets a fixed 48-hour grace period to
// claim the pet before it becomes eligible to post for adoption. This is a policy
// constant, not something entered on the form. Computed with MySQL's own NOW() (not
// PHP's date()/time()) so it's always consistent with the "claim_deadline < NOW()"
// expiry check elsewhere — PHP's configured timezone doesn't necessarily match the
// DB server's, which would otherwise skew the grace-period math by several hours.
$status = 'Pending';

$stmt = $conn->prepare("
    INSERT INTO pet_pound
        (pet_name, pet_photo, owner_name, reason, penalty_amount,
         impound_date, claim_deadline, species, breed, age,
         gender, health_status, status)
    VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 48 HOUR), ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    'ssssdssssss',
    $pet_name,
    $pet_photo,
    $owner_name,
    $reason,
    $penalty_amount,
    $species,
    $breed,
    $age,
    $gender,
    $health_status,
    $status
);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "Error saving pet: " . $stmt->error;
}

$stmt->close();
$conn->close();