<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . "/db_connect.php";

$base_url = 'https://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\')
    . '/images/';

$sql = "SELECT * FROM pets WHERE status = 'available' ORDER BY created_at DESC";
$result = $conn->query($sql);

$pets = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $filenames = !empty($row["image"]) ? explode(',', $row["image"]) : [];
        $filenames = array_values(array_filter(array_map('trim', $filenames)));

        $imageUrls = array_map(function ($fname) use ($base_url) {
            return $base_url . $fname;
        }, $filenames);

        $row["image"] = $imageUrls[0] ?? "";
        $row["images"] = $imageUrls;

        // created_at is already included because of SELECT *
        $pets[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "message" => "Pets loaded successfully",
        "pets" => $pets,
        "count" => count($pets)
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "message" => "No pets available",
        "pets" => []
    ]);
}

$conn->close();
exit;
?>