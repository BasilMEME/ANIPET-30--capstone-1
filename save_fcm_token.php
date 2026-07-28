<?php

header("Content-Type: application/json; charset=UTF-8");

$user_id = intval($_POST["user_id"] ?? 0);
$fcm_token = trim($_POST["fcm_token"] ?? "");

if ($user_id <= 0 || $fcm_token === "") {
    echo json_encode([
        "success" => false,
        "message" => "Missing user_id or fcm_token."
    ]);
    exit;
}

require_once __DIR__ . "/db_connect.php";

if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed."
    ]);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE users SET fcm_token = ? WHERE id = ?"
);

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare database query."
    ]);
    exit;
}

$stmt->bind_param("si", $fcm_token, $user_id);

if (!$stmt->execute()) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to save FCM token."
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

if ($stmt->affected_rows === 0) {
    $check = $conn->prepare(
        "SELECT id FROM users WHERE id = ? LIMIT 1"
    );

    $check->bind_param("i", $user_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "User not found."
        ]);

        $check->close();
        $stmt->close();
        $conn->close();
        exit;
    }

    $check->close();
}

echo json_encode([
    "success" => true,
    "message" => "FCM token saved successfully."
]);

$stmt->close();
$conn->close();