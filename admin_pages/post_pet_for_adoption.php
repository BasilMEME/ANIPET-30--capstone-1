<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_pet_pound');

header("Content-Type: application/json");

$id = (int)($_POST['id'] ?? 0);

$name          = trim($_POST['name'] ?? '');
$species       = trim($_POST['species'] ?? '');
$breed         = trim($_POST['breed'] ?? '');
$age           = trim($_POST['age'] ?? '');
$gender        = trim($_POST['gender'] ?? '');
$description   = trim($_POST['description'] ?? '');
$healthStatus  = trim($_POST['health_status'] ?? '');

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid pet ID."
    ]);
    exit;
}

/* Required adoption details */

if ($name === '') {
    echo json_encode([
        "success" => false,
        "message" => "Pet name is required."
    ]);
    exit;
}

if ($species === '') {
    echo json_encode([
        "success" => false,
        "message" => "Species is required."
    ]);
    exit;
}

if ($gender === '') {
    echo json_encode([
        "success" => false,
        "message" => "Gender is required."
    ]);
    exit;
}

if ($description === '') {
    echo json_encode([
        "success" => false,
        "message" => "Description is required."
    ]);
    exit;
}

/*
Automatically change Pending to Expired once the
14-day grace period has passed.
*/

$expireStmt = $conn->prepare("
    UPDATE pet_pound
    SET status = 'Expired'
    WHERE id = ?
      AND status = 'Pending'
      AND claim_deadline < NOW()
");

$expireStmt->bind_param("i", $id);
$expireStmt->execute();
$expireStmt->close();

/* Get impounded pet */

$stmt = $conn->prepare("
    SELECT *
    FROM pet_pound
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$pet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pet) {
    echo json_encode([
        "success" => false,
        "message" => "Pet record not found."
    ]);
    exit;
}

/* Prevent duplicate posting */

if (!empty($pet['posted_for_adoption']) || $pet['status'] === 'Posted') {
    echo json_encode([
        "success" => false,
        "message" => "This pet has already been posted for adoption."
    ]);
    exit;
}

/* Block deceased pets */

if ($pet['status'] === 'Deceased') {
    echo json_encode([
        "success" => false,
        "message" => "A deceased pet cannot be posted for adoption."
    ]);
    exit;
}

/* Block claimed or paid pets */

if (in_array($pet['status'], ['Claimed', 'Paid'], true)) {
    echo json_encode([
        "success" => false,
        "message" => "This pet has already been claimed and cannot be posted for adoption."
    ]);
    exit;
}

/* Enforce the full 14-day grace period */

if ($pet['status'] === 'Pending') {
    echo json_encode([
        "success" => false,
        "message" =>
            "The 14-day grace period is still active until " .
            date("M d, Y g:i A", strtotime($pet['claim_deadline'])) .
            "."
    ]);
    exit;
}

/* Only expired pets may be posted */

if ($pet['status'] !== 'Expired') {
    echo json_encode([
        "success" => false,
        "message" => "Only expired pet-pound records can be posted for adoption."
    ]);
    exit;
}

/* Copy pet photo into adoption image folder */

$image = null;
$copiedImagePath = null;

if (!empty($pet['pet_photo'])) {
    $originalFilename = basename($pet['pet_photo']);

    $sourcePath = __DIR__ . '/../images/pet_pound/' . $originalFilename;
    $destinationDirectory = __DIR__ . '/../images/';

    if (!is_dir($destinationDirectory)) {
        mkdir($destinationDirectory, 0755, true);
    }

    if (is_file($sourcePath)) {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);

        $newFilename = 'pet_' . bin2hex(random_bytes(8));

        if ($extension !== '') {
            $newFilename .= '.' . strtolower($extension);
        }

        $destinationPath = $destinationDirectory . $newFilename;

        if (copy($sourcePath, $destinationPath)) {
            $image = $newFilename;
            $copiedImagePath = $destinationPath;
        }
    }
}

/* Use a transaction so both tables update together */

$conn->begin_transaction();

try {
    /* Insert into adoption pets table */

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

    if (!$insert) {
        throw new Exception($conn->error);
    }

    $insert->bind_param(
        "ssssssss",
        $name,
        $species,
        $breed,
        $age,
        $gender,
        $description,
        $healthStatus,
        $image
    );

    if (!$insert->execute()) {
        throw new Exception($insert->error);
    }

    $newPetId = $conn->insert_id;
    $insert->close();

    /* Update pet-pound record */

    $update = $conn->prepare("
        UPDATE pet_pound
        SET
            posted_for_adoption = 1,
            adoption_pet_id = ?,
            status = 'Posted'
        WHERE id = ?
          AND posted_for_adoption = 0
          AND status = 'Expired'
    ");

    if (!$update) {
        throw new Exception($conn->error);
    }

    $update->bind_param("ii", $newPetId, $id);

    if (!$update->execute()) {
        throw new Exception($update->error);
    }

    if ($update->affected_rows !== 1) {
        throw new Exception(
            "The pet could not be marked as posted. It may already have been processed."
        );
    }

    $update->close();

    $conn->commit();

    echo json_encode([
        "success" => true,
        "message" => "Pet successfully posted for adoption.",
        "pet_id" => $id,
        "new_pet_id" => $newPetId
    ]);

} catch (Throwable $error) {
    $conn->rollback();

    if ($copiedImagePath && is_file($copiedImagePath)) {
        unlink($copiedImagePath);
    }

    echo json_encode([
        "success" => false,
        "message" => $error->getMessage()
    ]);
}