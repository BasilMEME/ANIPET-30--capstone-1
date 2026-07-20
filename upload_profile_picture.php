<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

ob_start();

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $httpCode = 200): never
{
    http_response_code($httpCode);

    if (
        ob_get_length() !== false &&
        ob_get_length() > 0
    ) {
        ob_clean();
    }

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

try {
    require_once __DIR__ . '/db_connect.php';

    if (
        !isset($conn) ||
        !($conn instanceof mysqli)
    ) {
        throw new RuntimeException(
            'Database connection is unavailable.'
        );
    }

    $userId = filter_input(
        INPUT_POST,
        'user_id',
        FILTER_VALIDATE_INT
    );

    if (!$userId || $userId <= 0) {
        respond([
            'status' => 'error',
            'message' => 'A valid user_id is required.'
        ], 400);
    }

    if (!isset($_FILES['image'])) {
        respond([
            'status' => 'error',
            'message' => 'No profile image was uploaded.'
        ], 400);
    }

    $file = $_FILES['image'];

    if (
        !isset(
            $file['error'],
            $file['tmp_name'],
            $file['name'],
            $file['size']
        )
    ) {
        respond([
            'status' => 'error',
            'message' => 'Invalid upload data.'
        ], 400);
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        respond([
            'status' => 'error',
            'message' => 'Image upload failed.',
            'debug' => 'PHP upload error code: ' .
                (int) $file['error']
        ], 400);
    }

    if ((int) $file['size'] <= 0) {
        respond([
            'status' => 'error',
            'message' => 'The selected image is empty.'
        ], 400);
    }

    // Maximum size: 5 MB
    if ((int) $file['size'] > 5 * 1024 * 1024) {
        respond([
            'status' => 'error',
            'message' => 'The image must not exceed 5 MB.'
        ], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (
        $mimeType === false ||
        !isset($allowedTypes[$mimeType])
    ) {
        respond([
            'status' => 'error',
            'message' =>
                'Only JPEG, PNG, and WebP images are allowed.',
            'debug' =>
                'Detected MIME type: ' .
                ($mimeType ?: 'unknown')
        ], 400);
    }

    $uploadDir =
        __DIR__ .
        DIRECTORY_SEPARATOR .
        'uploads' .
        DIRECTORY_SEPARATOR .
        'profile_pictures';

    if (
        !is_dir($uploadDir) &&
        !mkdir($uploadDir, 0755, true) &&
        !is_dir($uploadDir)
    ) {
        throw new RuntimeException(
            'Unable to create the profile picture directory.'
        );
    }

    if (!is_writable($uploadDir)) {
        throw new RuntimeException(
            'The profile picture directory is not writable.'
        );
    }

    $extension = $allowedTypes[$mimeType];

    $filename = sprintf(
        'user_%d_%s.%s',
        $userId,
        bin2hex(random_bytes(8)),
        $extension
    );

    $destination =
        $uploadDir .
        DIRECTORY_SEPARATOR .
        $filename;

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        throw new RuntimeException(
            'Failed to move the uploaded image.'
        );
    }

    $relativePath =
        'uploads/profile_pictures/' .
        $filename;

    $updateStmt = $conn->prepare(
        'UPDATE users
         SET profile_picture = ?
         WHERE id = ?'
    );

    if (!$updateStmt) {
        @unlink($destination);

        throw new RuntimeException(
            'Failed to prepare profile update: ' .
            $conn->error
        );
    }

    $updateStmt->bind_param(
        'si',
        $relativePath,
        $userId
    );

    if (!$updateStmt->execute()) {
        $error = $updateStmt->error;
        $updateStmt->close();

        @unlink($destination);

        throw new RuntimeException(
            'Failed to update profile picture: ' .
            $error
        );
    }

    if ($updateStmt->affected_rows === 0) {
        $updateStmt->close();

        @unlink($destination);

        respond([
            'status' => 'error',
            'message' => 'User account was not found.'
        ], 404);
    }

    $updateStmt->close();

    $userStmt = $conn->prepare(
        'SELECT
            id,
            full_name,
            username,
            email,
            phone,
            address,
            contact_preference,
            role,
            is_verified,
            profile_picture
         FROM users
         WHERE id = ?'
    );

    if (!$userStmt) {
        throw new RuntimeException(
            'Failed to prepare profile query: ' .
            $conn->error
        );
    }

    $userStmt->bind_param('i', $userId);

    if (!$userStmt->execute()) {
        throw new RuntimeException(
            'Failed to retrieve updated profile: ' .
            $userStmt->error
        );
    }

    $result = $userStmt->get_result();
    $user = $result->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        respond([
            'status' => 'error',
            'message' => 'User profile was not found.'
        ], 404);
    }

    $user['is_verified'] =
        (bool) $user['is_verified'];

    $isHttps =
        (!empty($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off') ||
        ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') ===
            'https';

    $scheme = $isHttps ? 'https' : 'http';

    $scriptDirectory = rtrim(
        str_replace(
            '\\',
            '/',
            dirname($_SERVER['SCRIPT_NAME'] ?? '/')
        ),
        '/'
    );

    $baseUrl =
        $scheme .
        '://' .
        ($_SERVER['HTTP_HOST'] ?? '') .
        ($scriptDirectory !== ''
            ? $scriptDirectory . '/'
            : '/');

    $user['profile_picture_url'] =
        !empty($user['profile_picture'])
            ? $baseUrl . $user['profile_picture']
            : null;

    respond([
        'status' => 'success',
        'message' =>
            'Profile picture uploaded successfully.',
        'user' => $user
    ]);
} catch (Throwable $exception) {
    error_log(
        'upload_profile_photo.php error: ' .
        $exception->getMessage()
    );

    respond([
        'status' => 'error',
        'message' =>
            'Failed to upload profile picture.',
        'debug' =>
            $exception->getMessage()
    ], 500);
}