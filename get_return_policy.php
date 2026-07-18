<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/return_policy_helper.php";

echo json_encode(["status" => "success", "policy" => get_return_policy_public($conn)]);

$conn->close();
?>
