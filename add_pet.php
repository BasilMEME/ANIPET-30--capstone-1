<?php
require_once __DIR__ . "/db_connect.php";
header("Content-Type: application/json");

// Use 10.0.2.2 for Emulator or your IP for physical device
$base_url = "http://192.168.100.18/pet_adoption_api/images/";

$sql = "SELECT * FROM pets";
$result = $conn->query($sql);

$pets = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $row["image"] = !empty($row["image"]) ? $base_url . $row["image"] : "";
        $pets[] = $row;
    }
    echo json_encode(["status" => "success", "pets" => $pets]);
} else {
    echo json_encode(["status" => "success", "pets" => []]);
}

$conn->close();
?>