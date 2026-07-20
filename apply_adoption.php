<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
// Renamed from apply_adoption_with_docs.php — consolidated handler supporting files and form_data
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";

// Ensure uploads directory exists
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// TEMP DEBUG — remove after diagnosing the "pet no longer available" reports.
error_log("apply_adoption.php DEBUG raw POST: " . json_encode($_POST) . " | FILES keys: " . json_encode(array_keys($_FILES)));

// Wrap the whole handler so any unexpected DB/file error becomes a readable JSON
// error instead of a bare HTTP 500 (which Retrofit surfaces to the app as an
// opaque "HTTP 500 Internal Server Error" with no detail on what actually failed).
try {

// Ensure form_data column exists (adds column if missing)
$colCheck = $conn->query("SHOW COLUMNS FROM adoption_applications LIKE 'form_data'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE adoption_applications ADD COLUMN form_data TEXT DEFAULT NULL");
}

// Basic required fields
$pet_id = $_POST['pet_id'] ?? '';
$user_id = $_POST['user_id'] ?? '';
$applicant_name = trim($_POST['applicant_name'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($pet_id === '' || $user_id === '' || $applicant_name === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields: pet_id, user_id or applicant_name"
    ]);
    exit;
}

// Verify the pet and user still exist before doing any file uploads — catches a stale/
// cached pet_id (pet deleted after the user opened its details screen) or a stale
// session (user_id from a deleted account) with a clear message instead of letting the
// INSERT fail later with a raw foreign-key-constraint SQL error.
$petCheck = $conn->prepare("SELECT id FROM pets WHERE id = ? LIMIT 1");
$petCheck->bind_param('i', $pet_id);
$petCheck->execute();
if ($petCheck->get_result()->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "This pet is no longer available. Please go back and choose another pet."
    ]);
    $petCheck->close();
    exit;
}
$petCheck->close();

$userCheck = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$userCheck->bind_param('i', $user_id);
$userCheck->execute();
if ($userCheck->get_result()->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Your account could not be found. Please log out and log in again."
    ]);
    $userCheck->close();
    exit;
}
$userCheck->close();

// Parse structured questionnaire JSON (if present)
$form_data = isset($_POST['form_data']) ? trim($_POST['form_data']) : null;
$form = [];
if ($form_data) {
    $form = json_decode($form_data, true);
    if ($form === null) {
        echo json_encode(["status" => "error", "message" => "Invalid form_data JSON"]);
        exit;
    }
}

// Server-side validations for key questionnaire fields
// Note: no 'intro_steps' field — the "Introduce Pet" step was removed from the pipeline.
$required = [
    'address','phone','email','birth_date','company','status','pronouns','prompted_by',
    'adopted_before','looking_for','specific_animal','ideal_pet','building_type','do_rent','move_plan','household',
    'allergic','daily_caregiver','financial_responsible','pet_sitter','hours_left',
    'family_support','other_pets','past_pets','will_visit','interaction_method'
];
foreach ($required as $key) {
    if (!isset($form[$key]) || trim($form[$key]) === '') {
        echo json_encode(["status" => "error", "message" => "Missing required form field: $key"]);
        exit;
    }
}

// preferred_date/preferred_time are only collected in the UI when Zoom is chosen
if ($form['interaction_method'] === 'Zoom') {
    foreach (['preferred_date', 'preferred_time'] as $key) {
        if (!isset($form[$key]) || trim($form[$key]) === '') {
            echo json_encode(["status" => "error", "message" => "Missing required form field: $key"]);
            exit;
        }
    }
}

// If applicant is a minor (age < 18) require alternate contact fields
if (!empty($form['birth_date'])) {
    $dob = DateTime::createFromFormat('Y-m-d', $form['birth_date']);
    if ($dob) {
        $age = $dob->diff(new DateTime('now'))->y;
        if ($age < 18) {
            $altReq = ['alt_name','alt_relation','alt_phone','alt_email'];
            foreach ($altReq as $k) {
                if (empty($form[$k])) {
                    echo json_encode(["status" => "error", "message" => "Applicant is minor — required alternate contact field missing: $k"]);
                    exit;
                }
            }
        }
    }
}

// Handle files: id_document (single) and house_photos[] (multiple)
$id_paths = [];
$house_paths = [];

// File constraints
$MAX_BYTES = 8 * 1024 * 1024; // 8 MB
$allowed_image_types = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/heic',
    'image/heif'
];

// id_document validation + move
if (empty($_FILES['id_document']) || $_FILES['id_document']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(["status"=>"error","message"=>"Valid ID upload is required"]);
    exit;
}
if ($_FILES['id_document']['error'] !== UPLOAD_ERR_OK) {

    $uploadError = (int) $_FILES['id_document']['error'];

    $uploadMessages = [
        UPLOAD_ERR_INI_SIZE =>
            "The ID image exceeds the image size limit. Please upload a smaller image.",
        UPLOAD_ERR_FORM_SIZE =>
            "The ID image is too large.",
        UPLOAD_ERR_PARTIAL =>
            "The ID image was only partially uploaded. Please try again.",
        UPLOAD_ERR_NO_FILE =>
            "No ID image was received.",
        UPLOAD_ERR_NO_TMP_DIR =>
            "Your temporary upload folder is missing.",
        UPLOAD_ERR_CANT_WRITE =>
            "Could not save the ID image.",
        UPLOAD_ERR_EXTENSION =>
            "The ID file is not compatible."
    ];

    error_log(
        "ID upload error code: " . $uploadError .
        " | File: " . print_r($_FILES['id_document'], true)
    );

    echo json_encode([
        "status" => "error",
        "message" => $uploadMessages[$uploadError]
            ?? "ID upload failed with error code: $uploadError"
    ]);

    exit;
}
if ($_FILES['id_document']['size'] > $MAX_BYTES) {
    echo json_encode(["status"=>"error","message"=>"ID document exceeds 8MB limit"]);
    exit;
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['id_document']['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, array_merge($allowed_image_types, ['application/pdf']))) {
    echo json_encode(["status"=>"error","message"=>"ID document must be an image or PDF"]);
    exit;
}
$tmp = $_FILES['id_document']['tmp_name'];
$name = basename($_FILES['id_document']['name']);
$target = 'uploads/' . uniqid('id_', true) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $name);
if (move_uploaded_file($tmp, __DIR__ . '/' . $target)) {
    $id_paths[] = $target;
} else {
    echo json_encode(["status"=>"error","message"=>"Failed to save ID document"]);
    exit;
}

// house photos
$files = null;

if (!empty($_FILES['house_photos'])) {
    $files = $_FILES['house_photos'];
} elseif (!empty($_FILES['house_photos[]'])) {
    $files = $_FILES['house_photos[]'];
}

if ($files !== null) {
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            if ($files['size'][$i] > $MAX_BYTES) {
                echo json_encode(["status"=>"error","message"=>"One of the house photos exceeds 8MB limit"]);
                exit;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);
            if (!in_array($mime, $allowed_image_types)) {
                echo json_encode(["status"=>"error","message"=>"House photos must be images (jpeg/png/gif/webp)"]);
                exit;
            }
            $tmp = $files['tmp_name'][$i];
            $name = basename($files['name'][$i]);
            $target = 'uploads/' . uniqid('house_', true) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $name);
            if (move_uploaded_file($tmp, __DIR__ . '/' . $target)) {
                $house_paths[] = $target;
            }
        }
    } elseif ($files['error'] === UPLOAD_ERR_OK) {
        if ($files['size'] > $MAX_BYTES) {
            echo json_encode(["status"=>"error","message"=>"House photo exceeds 8MB limit"]);
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $files['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed_image_types)) {
            echo json_encode(["status"=>"error","message"=>"House photo must be an image (jpeg/png/gif/webp)"]);
            exit;
        }
        $tmp = $files['tmp_name'];
        $name = basename($files['name']);
        $target = 'uploads/' . uniqid('house_', true) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $name);
        if (move_uploaded_file($tmp, __DIR__ . '/' . $target)) {
            $house_paths[] = $target;
        }
    }
}
if (empty($house_paths)) {
    echo json_encode(["status"=>"error","message"=>"At least one house photo is required"]);
    exit;
}

// Do not generate QR at submission time; QR is created when admin approves the application

// Re-check the pet still exists right before inserting — file uploads and the long
// questionnaire form above can take long enough for the pet to be deleted/adopted out
// from under this request, which would otherwise surface as a raw FK-constraint SQL
// error instead of a message the applicant can act on.
$petRecheck = $conn->prepare("SELECT id FROM pets WHERE id = ? LIMIT 1");
$petRecheck->bind_param('i', $pet_id);
$petRecheck->execute();
if ($petRecheck->get_result()->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "This pet is no longer available. Please go back and choose another pet."
    ]);
    $petRecheck->close();
    exit;
}
$petRecheck->close();

$stmt = $conn->prepare(
    "INSERT INTO adoption_applications (pet_id, user_id, applicant_name, message, id_documents, house_photos, form_data, terms_accepted, privacy_consent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$id_json = empty($id_paths) ? null : json_encode($id_paths);
$house_json = empty($house_paths) ? null : json_encode($house_paths);
$form_data = isset($_POST['form_data']) ? trim($_POST['form_data']) : null;
$terms_accepted = ($_POST['terms_accepted'] ?? '') === '1' ? 1 : 0;
$privacy_consent = trim($_POST['privacy_consent'] ?? '') ?: null;

$stmt->bind_param("iisssssis", $pet_id, $user_id, $applicant_name, $message, $id_json, $house_json, $form_data, $terms_accepted, $privacy_consent);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Application submitted",
        "application_id" => (string)$stmt->insert_id,
        "qr_code" => null
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Insert failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();

} catch (Throwable $e) {
    error_log("apply_adoption.php error: " . $e->getMessage());
    http_response_code(200);
    if ($e->getCode() === 1452) {
        echo json_encode([
            "status" => "error",
            "message" => "This pet is no longer available. Please go back and choose another pet."
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Server error: " . $e->getMessage()
        ]);
    }
}
?>
