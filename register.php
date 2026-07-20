<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => "error", "message" => "POST required"]);
    exit;
}

$username = trim($_POST["username"] ?? "");
$first_name = trim($_POST["first_name"] ?? "");
$middle_name = trim($_POST["middle_name"] ?? "");
$last_name = trim($_POST["last_name"] ?? "");
$suffix = trim($_POST["suffix"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";
$confirm_password = $_POST["confirm_password"] ?? "";

$full_name = trim($_POST["full_name"] ?? "");
$address = trim($_POST["address"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$contact_preference = trim($_POST["contact_preference"] ?? "");

if (($first_name === "" || $last_name === "") && $full_name === "") {
    echo json_encode(["status" => "error", "message" => "First and last name required"]);
    exit;
}

if (empty($email) || empty($password) || empty($confirm_password)) {
    echo json_encode(["status" => "error", "message" => "Email, password, and confirmation are required"]);
    exit;
}

if (empty($username)) {
    echo json_encode(["status" => "error", "message" => "Username is required"]);
    exit;
}

if ($password !== $confirm_password) {
    echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
    exit;
}

function normalize_name($name) {
    $normalized = preg_replace('/\s+/', ' ', trim($name));
    return ucwords(strtolower($normalized));
}

if ($first_name !== "" && $last_name !== "") {
    $nameParts = array_filter([$first_name, $middle_name, $last_name, $suffix], function($part) {
        return trim($part) !== "";
    });
    $full_name = normalize_name(implode(' ', $nameParts));
} else {
    $full_name = normalize_name($full_name);
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
$stmt->bind_param("ss", $email, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email or username already registered"]);
} else {
    $insert = $conn->prepare("INSERT INTO users (username, full_name, first_name, middle_name, last_name, suffix, email, password, address, phone, contact_preference, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', 0)");
    $insert->bind_param("sssssssssss", $username, $full_name, $first_name, $middle_name, $last_name, $suffix, $email, $hashed_password, $address, $phone, $contact_preference);

    if ($insert->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "User created. Verify using OTP",
            "user_id" => $conn->insert_id
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Registration failed"]);
    }
    $insert->close();
}

$stmt->close();
$conn->close();