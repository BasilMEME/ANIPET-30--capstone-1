<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';

$response = [
    'success' => false,
    'message' => 'Unable to upload completed adoption photo.'
];

$applicationId = intval($_POST['application_id'] ?? 0);
$adminRole = strtolower(trim($_POST['admin_role'] ?? ''));

if ($applicationId <= 0) {
    $response['message'] = 'Invalid application ID.';
    echo json_encode($response);
    exit;
}

if (!in_array($adminRole, ['admin', 'super admin', 'super_admin'], true)) {
    http_response_code(403);
    $response['message'] = 'Only Admin and Super Admin can upload this photo.';
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['completed_photo'])) {
    $response['message'] = 'No photo was uploaded.';
    echo json_encode($response);
    exit;
}

$file = $_FILES['completed_photo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Photo upload failed. Error code: ' . $file['error'];
    echo json_encode($response);
    exit;
}

$maxFileSize = 15 * 1024 * 1024;

if ($file['size'] > $maxFileSize) {
    $response['message'] = 'Photo must not exceed 15 MB.';
    echo json_encode($response);
    exit;
}

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowedMimeTypes[$mimeType])) {
    $response['message'] = 'Only JPG, PNG, and WEBP images are allowed.';
    echo json_encode($response);
    exit;
}

$checkApplication = $conn->prepare(
    "SELECT id, status, completed_photo
     FROM adoption_applications
     WHERE id = ?"
);

$checkApplication->bind_param('i', $applicationId);
$checkApplication->execute();

$application = $checkApplication->get_result()->fetch_assoc();

if (!$application) {
    $response['message'] = 'Adoption application not found.';
    echo json_encode($response);
    exit;
}

$uploadDirectory = __DIR__ . '/uploads/completed_photos/';
$relativeDirectory = 'uploads/completed_photos/';

if (!is_dir($uploadDirectory)) {
    if (!mkdir($uploadDirectory, 0755, true)) {
        $response['message'] = 'Unable to create the upload directory.';
        echo json_encode($response);
        exit;
    }
}

$extension = $allowedMimeTypes[$mimeType];
$fileName = 'completed_' . $applicationId . '_' . time() . '.' . $extension;
$destination = $uploadDirectory . $fileName;
$relativePath = $relativeDirectory . $fileName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    $response['message'] = 'Unable to save the uploaded photo.';
    echo json_encode($response);
    exit;
}

$oldPhoto = $application['completed_photo'] ?? '';

$update = $conn->prepare(
    "UPDATE adoption_applications
     SET completed_photo = ?
     WHERE id = ?"
);

$update->bind_param('si', $relativePath, $applicationId);

if (!$update->execute()) {
    if (file_exists($destination)) {
        unlink($destination);
    }

    $response['message'] = 'Failed to update the application record.';
    echo json_encode($response);
    exit;
}

if (!empty($oldPhoto)) {
    $oldPhotoPath = __DIR__ . '/' . ltrim($oldPhoto, '/');

    if (file_exists($oldPhotoPath)) {
        unlink($oldPhotoPath);
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Completed adoption photo uploaded successfully.',
    'completed_photo' => $relativePath
]);