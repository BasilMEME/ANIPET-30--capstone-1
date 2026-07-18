<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";

try {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    if ($user_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Missing or invalid user_id"]);
        exit;
    }

    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_preference = trim($_POST['contact_preference'] ?? '');

    if ($full_name === '') {
        echo json_encode(["status" => "error", "message" => "Full name is required"]);
        exit;
    }

    if ($contact_preference !== '' && !in_array(strtolower($contact_preference), ['email', 'phone'], true)) {
        echo json_encode(["status" => "error", "message" => "Invalid contact preference"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, contact_preference = ? WHERE id = ?");
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("ssssi", $full_name, $phone, $address, $contact_preference, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0 && $conn->error) {
        throw new Exception($conn->error);
    }
    $stmt->close();

    // Return the freshly saved row so the app can update its local display
    // without a second round trip.
    $stmt2 = $conn->prepare("SELECT id, username, full_name, email, role, is_verified, address, phone, contact_preference, profile_picture FROM users WHERE id = ? LIMIT 1");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $stmt2->bind_result($id2, $username2, $full_name2, $email2, $role2, $is_verified2, $address2, $phone2, $contact_preference2, $profile_picture2);
    $stmt2->fetch();
    $stmt2->close();

    $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
    $profile_picture_url2 = $profile_picture2 ? $base_url . $profile_picture2 : null;

    echo json_encode([
        "status" => "success",
        "message" => "Profile updated",
        "user" => [
            "id" => $id2,
            "username" => $username2,
            "full_name" => $full_name2,
            "email" => $email2,
            "role" => $role2,
            "is_verified" => (bool)$is_verified2,
            "address" => $address2,
            "phone" => $phone2,
            "contact_preference" => $contact_preference2,
            "profile_picture" => $profile_picture2,
            "profile_picture_url" => $profile_picture_url2
        ]
    ]);

    $conn->close();
} catch (Throwable $t) {
    error_log("update_user_profile error: " . $t->getMessage());
    echo json_encode(["status" => "error", "message" => "Server error while updating profile"]);
    exit;
}