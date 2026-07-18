<?php
require_once __DIR__ . "/db_connect.php";
header("Content-Type: application/json");

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/images/';

$sql = "SELECT id, name, breed, age, gender, description, health_status, image, status
        FROM pets
        WHERE status = 'available'
        ORDER BY id DESC";

$result = $conn->query($sql);

$pets = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row["image"] = !empty($row["image"]) ? $base_url . $row["image"] : "";
        $pets[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "pets" => $pets
]);

$conn->close();
?>