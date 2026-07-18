<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_returns');

header("Content-Type: application/json");

$id = intval($_POST['id'] ?? 0);

if($id <= 0){
    echo json_encode([
        "success"=>false,
        "message"=>"Invalid ID."
    ]);
    exit;
}

/* Lazy grace-period expiry, scoped to this row. Runs as a pure SQL comparison
   (claim_deadline < NOW(), both evaluated by MySQL) rather than mixing in PHP's
   time()/strtotime() — the PHP process and the DB server are not guaranteed to run
   in the same timezone, which would otherwise skew the 48-hour window. */
$conn->query("UPDATE pet_pound SET status='Expired' WHERE id=" . (int)$id . " AND status='Pending' AND claim_deadline < NOW()");

/* Get impounded pet */

$stmt = $conn->prepare("
SELECT *
FROM pet_pound
WHERE id=?
");

$stmt->bind_param("i",$id);
$stmt->execute();

$pet = $stmt->get_result()->fetch_assoc();

if(!$pet){
    echo json_encode([
        "success"=>false,
        "message"=>"Pet not found."
    ]);
    exit;
}

/* Already posted? */

if(!empty($pet['posted_for_adoption'])){

    echo json_encode([
        "success"=>true,
        "message"=>"Pet already posted."
    ]);

    exit;

}

if($pet['status'] === 'Deceased'){
    echo json_encode([
        "success"=>false,
        "message"=>"This pet is recorded as deceased and cannot be posted for adoption."
    ]);
    exit;
}

if($pet['status'] === 'Claimed' || $pet['status'] === 'Paid'){
    echo json_encode([
        "success"=>false,
        "message"=>"This pet has already been claimed by its owner and cannot be posted for adoption."
    ]);
    exit;
}

/* 48-hour grace period gate: the owner must be given the full window before the
   pet can be posted publicly, even if an admin jumps straight to this action.
   By this point the lazy-expiry UPDATE above has already flipped the row to
   'Expired' if the deadline has passed, so a status still stuck on 'Pending'
   means the grace period is genuinely still active. */
if($pet['status'] === 'Pending'){
    echo json_encode([
        "success"=>false,
        "message"=>"Grace period still active until " . date("M d, Y g:i A", strtotime($pet['claim_deadline'])) . ". The pet cannot be posted for adoption yet."
    ]);
    exit;
}

/* Copy photo from pet_pound folder into the pets folder so it displays correctly */

$image = null;

if (!empty($pet['pet_photo'])) {

    $sourcePath = __DIR__ . '/../images/pet_pound/' . $pet['pet_photo'];
    $destDir    = __DIR__ . '/../images/';

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (file_exists($sourcePath)) {
        $newFilename = 'pet_' . uniqid() . '_' . $pet['pet_photo'];
        $destPath    = $destDir . $newFilename;

        if (copy($sourcePath, $destPath)) {
            $image = $newFilename;
        }
    }
}

/* Insert into pets table. Note: `pets` has no owner_id column — it's the adoptable
   catalog, not an ownership record, so there is nothing to carry over there. */

$insert = $conn->prepare("
INSERT INTO pets
(
    name,
    species,
    breed,
    age,
    gender,
    description,
    health_status,
    image,
    status
)
VALUES
(
    ?, ?, ?, ?, ?, ?, ?, ?, 'available'
)
");

$species = $pet['species'];
$breed = $pet['breed'];
$age = $pet['age'];
$gender = $pet['gender'];
$health_status = $pet['health_status'];

$description = "Impounded pet.\nReason: ".$pet['reason'];

$insert->bind_param(
    "ssssssss",
    $pet['pet_name'],
    $species,
    $breed,
    $age,
    $gender,
    $description,
    $health_status,
    $image
);

if(!$insert->execute()){

    echo json_encode([
        "success"=>false,
        "message"=>$insert->error
    ]);

    exit;

}

$newPetId = $conn->insert_id;

/* Update pet pound */

$update = $conn->prepare("
UPDATE pet_pound
SET
    posted_for_adoption = ?,
    adoption_pet_id = ?,
    status = ?
WHERE id = ?
");

$posted = 1;
$status = "Posted";

$update->bind_param(
    "iisi",
    $posted,
    $newPetId,
    $status,
    $id
);

if(!$update->execute()){
    echo json_encode([
        "success" => false,
        "message" => $update->error
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "affected_rows" => $update->affected_rows,
    "pet_id" => $id,
    "new_pet_id" => $newPetId
]);
