<?php
header('Content-Type: application/json');        // ✅ Only ONCE
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . "/db_connect.php";

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/images/';
$pet_id = $_GET["pet_id"] ?? "";

if (empty($pet_id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing pet_id parameter"
    ]);
    $conn->close();
    exit;
}

$stmt = $conn->prepare("SELECT * FROM pets WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $row["image"] = !empty($row["image"]) ? $base_url . $row["image"] : "";
    echo json_encode([
        "status" => "success",
        "message" => "Pet found",
        "pet" => $row  // ✅ Matches PetDetailsScreen!
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Pet not found with ID: $pet_id"
    ]);
}

$stmt->close();
$conn->close();
exit;
?>