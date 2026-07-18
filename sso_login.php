<?php
/**
 * SSO Login Handler
 * Called from the mobile app with an SSO token to authenticate the browser session
 * Usage: sso_login.php?t=<token>
 */

require_once __DIR__ . '/db_connect.php';

$token = $_GET['t'] ?? '';

if (empty($token)) {
    http_response_code(400);
    echo '<h1>Invalid Request</h1>';
    echo '<p>Missing SSO token.</p>';
    exit;
}

// Hash the token and look it up
$tokenHash = hash('sha256', $token);

// Verify token exists, is not used, and not expired
$stmt = $conn->prepare("SELECT user_id FROM sso_tokens WHERE token_hash = ? AND used = 0 AND expires_at >= NOW() LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo '<h1>Server Error</h1>';
    echo '<p>Database error.</p>';
    exit;
}

$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(401);
    echo '<h1>Invalid or Expired Token</h1>';
    echo '<p>The SSO token is invalid, expired, or already used. Please log in again from the mobile app.</p>';
    exit;
}

$userId = $row['user_id'];

// Mark token as used
$markStmt = $conn->prepare("UPDATE sso_tokens SET used = 1 WHERE token_hash = ?");
if ($markStmt) {
    $markStmt->bind_param('s', $tokenHash);
    $markStmt->execute();
    $markStmt->close();
}

// Fetch user details
$userStmt = $conn->prepare("SELECT id, username, full_name, email, role, is_suspended, is_deleted FROM users WHERE id = ? LIMIT 1");
if (!$userStmt) {
    http_response_code(500);
    echo '<h1>Server Error</h1>';
    exit;
}

$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userRow = $userResult->fetch_assoc();
$userStmt->close();

if (!$userRow) {
    http_response_code(404);
    echo '<h1>User Not Found</h1>';
    exit;
}

if (!empty($userRow['is_deleted']) || !empty($userRow['is_suspended'])) {
    http_response_code(403);
    echo '<h1>Account Unavailable</h1>';
    echo '<p>This account has been suspended or removed.</p>';
    exit;
}

// Start session and set user data
session_start();
$_SESSION['user_id'] = $userRow['id'];
$_SESSION['username'] = $userRow['username'];
$_SESSION['full_name'] = $userRow['full_name'];
$_SESSION['email'] = $userRow['email'];
$_SESSION['role'] = $userRow['role'];
$_SESSION['user_session'] = session_id();

// Log audit for SSO token usage
$auditStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, target_type, target_id, details, ip_address) VALUES (?, 'sso_token_used', 'sso_tokens', ?, 'SSO token redeemed from browser', ?)");
if ($auditStmt) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $auditStmt->bind_param('iss', $userId, $tokenHash, $ip);
    $auditStmt->execute();
    $auditStmt->close();
}

$conn->close();

// Redirect to the proper dashboard for this role
if ($userRow['role'] === 'admin') {
    header('Location: admin_workspace.php');
} else {
    header('Location: super_admin_dashboard.php');
}
exit;
?>
