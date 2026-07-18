<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/application_status_helper.php';
require_permission($conn, 'manage_applications');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Missing application id");
}

// Routed through the shared helper (not a raw UPDATE) so rejecting also releases
// the pet back to 'available' — same pipeline approve_application.php already uses.
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/';
applyApplicationStatusChange($conn, $id, 'rejected', current_user_id(), 'Rejected via admin UI', null, $base_url);

header("Location: admin_applications.php");
exit;
?>
