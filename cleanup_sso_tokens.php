<?php
/**
 * SSO Token Cleanup Script
 * Removes expired and used SSO tokens from the database.
 * Can be run via cron job or manually triggered.
 * Usage: curl https://your-site/php-backend/cleanup_sso_tokens.php?key=YOUR_SECRET_KEY
 */

require_once __DIR__ . '/db_connect.php';

// Security: require a secret key to prevent unauthorized cleanup calls
$secretKey = getenv('SSO_CLEANUP_KEY') ?: 'your-secret-key-here';
$providedKey = $_GET['key'] ?? $_POST['key'] ?? '';

if ($secretKey === 'your-secret-key-here') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'SSO_CLEANUP_KEY environment variable not configured']);
    exit;
}

if ($providedKey !== $secretKey) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Delete expired and used SSO tokens
$stmt = $conn->prepare("DELETE FROM sso_tokens WHERE used = 1 OR expires_at < NOW()");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

if ($stmt->execute()) {
    $deletedCount = $conn->affected_rows;
    $stmt->close();
    
    // Log the cleanup action if audit_logs table exists
    if ($conn->query("SHOW TABLES LIKE 'audit_logs'")->num_rows > 0) {
        $logStmt = $conn->prepare("INSERT INTO audit_logs (action_type, target_type, details, ip_address) VALUES ('auto_cleanup', 'sso_tokens', ?, ?)");
        if ($logStmt) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'cron';
            $details = "Automatic cleanup of expired/used SSO tokens: $deletedCount rows deleted";
            $logStmt->bind_param('ss', $details, $ip);
            $logStmt->execute();
            $logStmt->close();
        }
    }
    
    $conn->close();
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => "Cleaned up $deletedCount expired/used SSO tokens",
        'deleted_count' => $deletedCount,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to cleanup tokens: ' . $conn->error]);
}
?>
