<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login_form.php');
        exit;
    }
}

function require_api_login(): void
{
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);

        exit;
    }
}

function current_user_id(): ?int
{
    return !empty($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;
}

function current_user_role(): ?string
{
    return isset($_SESSION['role'])
        ? (string) $_SESSION['role']
        : null;
}

function load_setting(
    mysqli $conn,
    string $key,
    mixed $default = ''
): mixed {
    $stmt = $conn->prepare(
        'SELECT setting_value
         FROM system_settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($value);

    if ($stmt->fetch()) {
        $stmt->close();
        return $value;
    }

    $stmt->close();
    return $default;
}

function has_permission(
    mysqli $conn,
    string $permissionKey
): bool {
    $role = current_user_role();

    if (!$role) {
        return false;
    }

    if (
        $role === 'admin' &&
        $permissionKey === 'view_audit_logs'
    ) {
        return false;
    }

    $stmt = $conn->prepare(
        'SELECT is_allowed
         FROM role_permissions
         WHERE role = ?
           AND permission_key = ?
         LIMIT 1'
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $role, $permissionKey);
    $stmt->execute();
    $stmt->bind_result($isAllowed);

    $found = $stmt->fetch();
    $stmt->close();

    return $found && (int) $isAllowed === 1;
}

function require_permission(
    mysqli $conn,
    string $permissionKey
): void {
    require_login();

    if (!has_permission($conn, $permissionKey)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function require_admin(): void
{
    require_login();

    $role = current_user_role();

    if ($role === 'super_admin') {
        header('Location: super_admin_dashboard.php');
        exit;
    }

    if ($role !== 'admin') {
        header('Location: login_form.php');
        exit;
    }
}

function require_admin_or_super(): void
{
    require_login();

    $role = current_user_role();

    if (
        $role !== 'admin' &&
        $role !== 'super_admin'
    ) {
        header('Location: login_form.php');
        exit;
    }
}

function require_super_admin(): void
{
    require_login();

    $role = current_user_role();

    if ($role === 'admin') {
        header('Location: admin_workspace.php');
        exit;
    }

    if ($role !== 'super_admin') {
        header('Location: login_form.php');
        exit;
    }
}

function require_super_or_permission(
    string $permissionKey
): void {
    require_login();

    $role = current_user_role();

    if ($role === 'super_admin') {
        return;
    }

    if ($role === 'admin') {
        header('Location: admin_workspace.php');
        exit;
    }

    global $conn;

    if (!isset($conn) || !($conn instanceof mysqli)) {
        require __DIR__ . '/db_connect.php';
    }

    if (has_permission($conn, $permissionKey)) {
        return;
    }

    header('Location: login_form.php');
    exit;
}