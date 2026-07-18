<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/application_status_helper.php';
require_permission($conn, 'manage_applications');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die("Missing or invalid application id");
}

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), '/') . '/';
applyApplicationStatusChange($conn, $id, 'approved', current_user_id(), 'Approved via admin UI', null, $base_url);

header("Location: admin_applications.php");
exit;
?>