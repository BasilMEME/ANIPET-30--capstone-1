<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_super_admin();

try {
    $result = $conn->query('SELECT us.id, us.user_id, us.session_id, us.ip_address, us.user_agent, us.created_at, us.last_active_at, us.is_active, u.username, u.email, u.full_name, u.role FROM user_sessions us LEFT JOIN users u ON us.user_id = u.id ORDER BY us.last_active_at DESC LIMIT 100');
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
    echo json_encode(['success' => true, 'sessions' => $sessions]);
} catch (Throwable $t) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
