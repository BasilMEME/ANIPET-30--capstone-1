<?php
header('Content-Type: application/json');        // ✅ Only ONCE
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . "/db_connect.php";

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/images/';

$sql = "SELECT * FROM pets WHERE status = 'available' ORDER BY created_at DESC";  // ✅ Only available pets
$result = $conn->query($sql);

$pets = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row["image"] = !empty($row["image"]) ? $base_url . $row["image"] : "";
        $pets[] = $row;
    }
    echo json_encode([
        "status" => "success",
        "message" => "Pets loaded successfully",
        "pets" => $pets,
        "count" => count($pets)  // ✅ Bonus count
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "message" => "No pets available",
        "pets" => []
    ]);
}

$conn->close();
exit;  // ✅ Clean end - NO EXTRA ECHO!
?>