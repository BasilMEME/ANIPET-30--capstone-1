<?php
require_once __DIR__ . "/db_connect.php";

// Update first 4 available pets with MULTIPLE image filenames each,
// so the new photo gallery has something to show while testing.
// Adjust these filenames to match whatever test images actually exist
// in your /images folder.
$imageSets = array(
    array("pet_1.jpg", "pet_1b.jpg", "pet_1c.jpg"),
    array("pet_2.jpg", "pet_2b.jpg"),
    array("pet_3.jpg", "pet_3b.jpg", "pet_3c.jpg"),
    array("pet_4.jpg"),
);

$sql = "SELECT id FROM pets WHERE status = 'available' ORDER BY id LIMIT 4";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $index = 0;
    while ($index < count($imageSets) && ($row = $result->fetch_assoc())) {
        $pet_id = $row["id"];
        $imageString = implode(',', $imageSets[$index]);

        $update_sql = "UPDATE pets SET image = ? WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("si", $imageString, $pet_id);
        $stmt->execute();
        $stmt->close();

        echo "Updated pet ID $pet_id with images: $imageString\n";
        $index++;
    }
    echo "\n✅ All pet images updated successfully!";
} else {
    echo "❌ No available pets found in database";
}

$conn->close();
?>