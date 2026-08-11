<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";

try {
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if ($user_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Missing or invalid user_id"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, username, full_name, email, role, is_verified, address, phone, contact_preference, birth_date, profile_picture FROM users WHERE id = ? LIMIT 1");
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $executed = $stmt->execute();
    if ($executed === false) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $stmt->bind_result($id, $username, $full_name, $email, $role, $is_verified, $address, $phone, $contact_preference, $birth_date, $profile_picture);
    if ($stmt->fetch()) {
        $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
        $profile_picture_url = $profile_picture ? $base_url . $profile_picture : null;

        echo json_encode([
            "status" => "success",
            "user" => [
                "id" => $id,
                "username" => $username,
                "full_name" => $full_name,
                "email" => $email,
                "role" => $role,
                "is_verified" => (bool)$is_verified,
                "address" => $address,
                "phone" => $phone,
                "contact_preference" => $contact_preference,
                "birth_date" => $birth_date,
                "profile_picture" => $profile_picture,
                "profile_picture_url" => $profile_picture_url
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "User not found"]);
    }

    $stmt->close();
    $conn->close();
} catch (Throwable $t) {
    error_log("get_user_profile error: " . $t->getMessage());
    echo json_encode(["status" => "error", "message" => "Server error while fetching profile"]);
    exit;
}
