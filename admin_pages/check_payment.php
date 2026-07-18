<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_returns');

$id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;

$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM pet_penalty_payments
    WHERE pet_pound_id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();

echo $count;