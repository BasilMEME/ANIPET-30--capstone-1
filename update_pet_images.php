<?php
require_once __DIR__ . "/db_connect.php";

// Update first 4 available pets with image filenames
$images = array("pet_1.jpg", "pet_2.jpg", "pet_3.jpg", "pet_4.jpg");

$sql = "SELECT id FROM pets WHERE status = 'available' ORDER BY id LIMIT 4";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $index = 0;
    while($row = $result->fetch_assoc() && $index < count($images)) {
        $pet_id = $row["id"];
        $image = $images[$index];
        
        $update_sql = "UPDATE pets SET image = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $image, $pet_id);
        $stmt->execute();
        $stmt->close();
        
        echo "Updated pet ID $pet_id with image: $image\n";
        $index++;
    }
    echo "\n✅ All pet images updated successfully!";
} else {
    echo "❌ No available pets found in database";
}

$conn->close();
?>
