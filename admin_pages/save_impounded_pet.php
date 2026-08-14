<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_pet_pound');
require_once __DIR__ . '/../system_settings_helper.php';

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

// Read the current grace-period setting. New impoundments use this value.
// Existing records keep their already-saved claim_deadline.
$gracePeriodDays = (int)get_system_setting(
    $conn,
    'claim_grace_period_days',
    '14'
);

// Safety fallback in case the database contains an invalid value.
if ($gracePeriodDays < 1 || $gracePeriodDays > 365) {
    $gracePeriodDays = 14;
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

    if (!in_array($ext, $allowed, true)) {
        echo "Invalid photo file type.";
        exit;
    }

    $fileName = uniqid('pet_', true) . '.' . $ext;
    $destination = $uploadDir . $fileName;

    // Save the relative path in MySQL
    $pet_photo = 'pet_pound/' . $fileName;

    if (!move_uploaded_file($_FILES['pet_photo']['tmp_name'], $destination)) {
        echo "Failed to upload photo.";
        exit;
    }
}

// The claim deadline is calculated from the current configurable grace period.
// MySQL's NOW() is used so it stays consistent with the Pet Pound expiry check.
$status = 'Pending';

$stmt = $conn->prepare("
    INSERT INTO pet_pound
        (pet_name, pet_photo, owner_name, reason, penalty_amount,
         impound_date, claim_deadline, species, breed, age,
         gender, health_status, status)
    VALUES (?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    echo "Error preparing pet record: " . $conn->error;
    exit;
}

$stmt->bind_param(
    'ssssdissssss',
    $pet_name,
    $pet_photo,
    $owner_name,
    $reason,
    $penalty_amount,
    $gracePeriodDays,
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