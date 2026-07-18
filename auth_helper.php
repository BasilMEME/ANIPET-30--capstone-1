<?php
require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login_form.php');
        exit;
    }
}

function require_api_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
}

function current_user_id(): ?int {
    return !empty($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
}

function current_user_role(): ?string {
    return $_SESSION['role'] ?? null;
}

function load_setting(mysqli $conn, string $key, $default = '') {
    $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->bind_result($value);
        if ($stmt->fetch()) {
            $stmt->close();
            return $value;
        }
        $stmt->close();
    }
    return $default;
}

function has_permission(mysqli $conn, string $permissionKey): bool {
    $role = current_user_role();
    if (!$role) {
        return false;
    }

    // Admins are not allowed to view audit logs regardless of stored permissions.
    if ($role === 'admin' && $permissionKey === 'view_audit_logs') {
        return false;
    }

    $stmt = $conn->prepare('SELECT is_allowed FROM role_permissions WHERE role = ? AND permission_key = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $role, $permissionKey);
    $stmt->execute();
    $stmt->bind_result($isAllowed);
    $result = $stmt->fetch();
    $stmt->close();

    return $result && intval($isAllowed) === 1;
}

function require_permission(mysqli $conn, string $permissionKey): void {
    require_login();
    if (!has_permission($conn, $permissionKey)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function require_admin(): void {
    require_login();
    $role = current_user_role();
    if ($role === 'super_admin') {
        // Super admin trying admin portal — redirect to their own dashboard
        header('Location: super_admin_dashboard.php');
        exit;
    }
    if ($role !== 'admin') {
        header('Location: login_form.php');
        exit;
    }
}

function require_admin_or_super(): void {
    require_login();
    $role = current_user_role();
    if ($role !== 'admin' && $role !== 'super_admin') {
        header('Location: login_form.php');
        exit;
    }
}

function require_super_admin(mysqli $conn = null): void {
    require_login();
    $role = current_user_role();
    if ($role === 'admin') {
        // Admin trying super admin portal — redirect to their own dashboard
        header('Location: admin_workspace.php');
        exit;
    }
    if ($role !== 'super_admin') {
        header('Location: login_form.php');
        exit;
    }
}

function require_super_or_permission(string $permissionKey): void {
    require_login();
    $role = current_user_role();
    if ($role === 'super_admin') {
        return;
    }
    if ($role === 'admin') {
        // Admin trying super admin portal — redirect to their own dashboard
        header('Location: admin_workspace.php');
        exit;
    }
    global $conn;
    if (!isset($conn)) {
        require_once __DIR__ . '/db_connect.php';
    }
    if (has_permission($conn, $permissionKey)) {
        return;
    }
    header('Location: login_form.php');
    exit;
}
