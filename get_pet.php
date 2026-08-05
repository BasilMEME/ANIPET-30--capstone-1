<?php
header('Content-Type: application/json');        // ✅ Only ONCE
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . "/db_connect.php";

$base_url = 'https://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
    . '/images/';
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
    // `image` may now hold multiple comma-separated filenames.
    $filenames = !empty($row["image"]) ? explode(',', $row["image"]) : [];
    $filenames = array_values(array_filter(array_map('trim', $filenames)));

    $imageUrls = array_map(function ($fname) use ($base_url) {
        return $base_url . $fname;
    }, $filenames);

    // Keep "image" as the first photo for any older app code still reading it directly,
    // and add "images" as the full gallery array for the updated PetDetailsScreen.
    $row["image"]  = $imageUrls[0] ?? "";
    $row["images"] = $imageUrls;

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