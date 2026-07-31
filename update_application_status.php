<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/application_status_helper.php';
header("Content-Type: application/json");

$protocol = 'http';

if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
) {
    $protocol = 'https';
}

$scriptDirectory = trim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/';

if ($scriptDirectory !== '' && $scriptDirectory !== '.') {
    $base_url .= $scriptDirectory . '/';
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "POST") {
    // Only admin/super_admin staff can change an application's status.
    require_permission($conn, 'manage_applications');

    $data = json_decode(file_get_contents("php://input"), true) ?: $_POST;

    $application_id = $data["application_id"] ?? "";
    $status = trim($data["status"] ?? "");
    $admin_id = $data["admin_id"] ?? null;
    $admin_notes = $data["admin_notes"] ?? "";
    $interview_datetime = trim($data["interview_datetime"] ?? "") ?: null;

    if ($application_id === "" || $status === "") {
        echo json_encode([
            "success" => false,
            "message" => "application_id and status are required"
        ]);
        exit;
    }

    $result = applyApplicationStatusChange(
        $conn,
        (int)$application_id,
        $status,
        $admin_id !== null ? (int)$admin_id : null,
        $admin_notes,
        $interview_datetime,
        $base_url
    );

    echo json_encode($result);

} elseif ($method === "GET") {
    // Get application with tracking info
    $application_id = $_GET["application_id"] ?? null;

    if (!$application_id) {
        echo json_encode([
            "success" => false,
            "message" => "application_id required"
        ]);
        exit;
    }

    $query = $conn->prepare("
        SELECT
            aa.id, aa.pet_id, aa.user_id, aa.applicant_name, aa.message,
            aa.status, aa.qr_code, aa.admin_notes, aa.interview_datetime, aa.form_data,
            aa.created_at, aa.screened_by,
            p.name as pet_name, p.image as pet_image,
            u.full_name as user_name, u.email as user_email
        FROM adoption_applications aa
        JOIN pets p ON aa.pet_id = p.id
        JOIN users u ON aa.user_id = u.id
        WHERE aa.id = ?
    ");
    $query->bind_param("i", $application_id);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "message" => "Application not found"
        ]);
        exit;
    }

    $application = $result->fetch_assoc();
    if (!empty($application['qr_code'])) {
        $application['qr_code'] = (strpos($application['qr_code'], 'http') === 0) ? $application['qr_code'] : $base_url . $application['qr_code'];
    }
    if (!empty($application['pet_image'])) {
        $application['pet_image'] = (strpos($application['pet_image'], 'http') === 0) ? $application['pet_image'] : $base_url . 'images/' . $application['pet_image'];
    }

    // Get status progression (display steps only — 'rejected' is a side-branch, not a step)
    $statuses = ['pending', 'screening', 'approved', 'for_releasing', 'ready_pickup', 'completed'];
    $current_index = array_search($application['status'], $statuses);
    $completed_steps = $current_index !== false ? $current_index + 1 : 0;

    echo json_encode([
        "success" => true,
        "data" => $application,
        "progress" => [
            "current_status" => $application['status'],
            "completed_steps" => $completed_steps,
            "total_steps" => count($statuses),
            "statuses" => $statuses
        ]
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
}

$conn->close();
?>
