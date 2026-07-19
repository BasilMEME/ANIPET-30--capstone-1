<?php
// Enable detailed error reporting for debugging (remove or disable on production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'tokaido.proxy.rlwy.net';
$dbname = 'anipet_db';
$username = 'root';
$password = 'akVeaqGsMrFagZHBxQgMJGnsLDAWwRAW';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Return JSON error for Android clients during debugging
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}
?>