<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/db_connect.php';
    $stmt = $conn->prepare('UPDATE user_sessions SET is_active = 0 WHERE session_id = ?');
    if ($stmt) {
        $sessionId = session_id();
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $stmt->close();
    }
}
session_unset();
session_destroy();
header('Location: login_form.php');
exit;
