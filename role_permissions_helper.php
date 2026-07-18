<?php
// Single source of truth for the role_permissions table and its defaults, shared by
// super_admin_api.php and super_admin_security.php. Defaults are seeded ONLY when the
// table is empty — never force-overwritten on every call, otherwise any customization
// a super admin saves gets silently reverted on the next unrelated request.
function ensureRolePermissionsTable($conn) {
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS `role_permissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `role` VARCHAR(20) NOT NULL,
            `permission_key` VARCHAR(120) NOT NULL,
            `is_allowed` TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY `role_perm_unique` (`role`, `permission_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $result = $conn->query("SELECT COUNT(*) AS total FROM role_permissions");
        if ($result && ($row = $result->fetch_assoc()) && intval($row['total']) === 0) {
            $defaultPermissions = [
                ['super_admin', 'manage_admins', 1],
                ['super_admin', 'view_audit_logs', 1],
                ['super_admin', 'manage_users', 1],
                ['super_admin', 'manage_pets', 1],
                ['super_admin', 'manage_applications', 1],
                ['super_admin', 'configure_system', 1],
                ['super_admin', 'backup_restore_database', 1],
                ['super_admin', 'terminate_sessions', 1],
                ['super_admin', 'update_security_policy', 1],
                ['super_admin', 'manage_notifications', 1],
                ['super_admin', 'generate_reports', 1],
                ['super_admin', 'manage_appointments', 1],
                ['super_admin', 'manage_returns', 1],
                ['super_admin', 'manage_settings', 1],
                ['admin', 'manage_admins', 0],
                ['admin', 'view_audit_logs', 1],
                ['admin', 'manage_users', 1],
                ['admin', 'manage_pets', 1],
                ['admin', 'manage_applications', 1],
                ['admin', 'configure_system', 0],
                ['admin', 'backup_restore_database', 0],
                ['admin', 'terminate_sessions', 1],
                ['admin', 'update_security_policy', 0],
                ['admin', 'manage_notifications', 1],
                ['admin', 'generate_reports', 1],
                ['admin', 'manage_appointments', 1],
                ['admin', 'manage_returns', 1],
                ['admin', 'manage_settings', 1],
                ['user', 'view_audit_logs', 0],
                ['user', 'manage_users', 0],
                ['user', 'manage_pets', 0],
                ['user', 'manage_applications', 0],
                ['user', 'configure_system', 0],
                ['user', 'backup_restore_database', 0],
                ['user', 'terminate_sessions', 0],
                ['user', 'update_security_policy', 0],
                ['user', 'manage_notifications', 0],
                ['user', 'generate_reports', 0],
                ['user', 'manage_appointments', 0],
                ['user', 'manage_returns', 0],
                ['user', 'manage_settings', 0]
            ];
            $stmt = $conn->prepare("INSERT INTO role_permissions (role, permission_key, is_allowed) VALUES (?, ?, ?)");
            if ($stmt) {
                foreach ($defaultPermissions as $perm) {
                    $stmt->bind_param('ssi', $perm[0], $perm[1], $perm[2]);
                    $stmt->execute();
                }
                $stmt->close();
            }
        }
    } catch (Throwable $e) {
        error_log('ensureRolePermissionsTable error: ' . $e->getMessage());
    }
}
