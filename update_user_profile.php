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
    $birth_date = trim($_POST['birth_date'] ?? '');

    if ($full_name === '') {
        echo json_encode(["status" => "error", "message" => "Full name is required"]);
        exit;
    }

    if ($contact_preference !== '' && !in_array(strtolower($contact_preference), ['email', 'phone'], true)) {
        echo json_encode(["status" => "error", "message" => "Invalid contact preference"]);
        exit;
    }

    $existing = $conn->prepare("SELECT birth_date FROM users WHERE id = ? LIMIT 1");
    $existing->bind_param("i", $user_id);
    $existing->execute();
    $existing->bind_result($existing_birth_date);
    if (!$existing->fetch()) {
        $existing->close();
        echo json_encode(["status" => "error", "message" => "User not found"]);
        exit;
    }
    $existing->close();

    $birth_date_to_save = $existing_birth_date;

    // Birthday can only be set once. Existing users with no birthday may add it once.
    if (empty($existing_birth_date) && $birth_date !== '') {
        $birthDateObject = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$birthDateObject || $birthDateObject->format('Y-m-d') !== $birth_date || $birthDateObject > new DateTime('today')) {
            echo json_encode(["status" => "error", "message" => "Invalid birthday"]);
            exit;
        }
        $birth_date_to_save = $birth_date;
    }

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, contact_preference = ?, birth_date = ? WHERE id = ?");
    if ($stmt === false) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param("sssssi", $full_name, $phone, $address, $contact_preference, $birth_date_to_save, $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt2 = $conn->prepare("SELECT id, username, full_name, email, role, is_verified, address, phone, contact_preference, birth_date, profile_picture FROM users WHERE id = ? LIMIT 1");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $stmt2->bind_result($id2, $username2, $full_name2, $email2, $role2, $is_verified2, $address2, $phone2, $contact_preference2, $birth_date2, $profile_picture2);
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
            "birth_date" => $birth_date2,
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
