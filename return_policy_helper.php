<?php
// Single source of truth for the configurable return-penalty / donation-beneficiary
// policy, backed by the existing generic `system_settings` key-value table. Shared by
// request_return.php, admin_api.php, and the public get_return_policy.php endpoint so
// the penalty amount shown/charged is always computed the same way everywhere.

function get_return_policy_setting(mysqli $conn, string $key, string $default = ''): string {
    $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) return $default;
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($value);
    $found = $stmt->fetch();
    $stmt->close();
    return ($found && $value !== null && $value !== '') ? $value : $default;
}

// Shelter policy value — never trust a client-supplied penalty amount (see request_return.php).
function calculate_return_penalty(mysqli $conn): float {
    $type   = get_return_policy_setting($conn, 'return_penalty_type', 'fixed');
    $amount = (float) get_return_policy_setting($conn, 'return_penalty_amount', '1000');
    if ($type === 'percentage') {
        $base = (float) get_return_policy_setting($conn, 'return_penalty_base_amount', '1000');
        return round($base * ($amount / 100), 2);
    }
    return round($amount, 2);
}

const RETURN_POLICY_KEYS = [
    'return_penalty_type', 'return_penalty_amount', 'return_penalty_base_amount',
    'dog_pound_name', 'dog_pound_contact', 'dog_pound_address', 'dog_pound_notes',
    'donation_qr_filename', 'donation_gcash_name', 'donation_gcash_number', 'donation_notes',
];

// Safe-for-clients subset (no admin-only fields) used by the public policy endpoint and
// surfaced in the Android app's adoption terms / return request screens.
function get_return_policy_public(mysqli $conn): array {
    return [
        'penalty_type'          => get_return_policy_setting($conn, 'return_penalty_type', 'fixed'),
        'penalty_amount'        => get_return_policy_setting($conn, 'return_penalty_amount', '1000'),
        'penalty_base_amount'   => get_return_policy_setting($conn, 'return_penalty_base_amount', '1000'),
        'computed_penalty'      => calculate_return_penalty($conn),
        'dog_pound_name'        => get_return_policy_setting($conn, 'dog_pound_name', ''),
        'dog_pound_contact'     => get_return_policy_setting($conn, 'dog_pound_contact', ''),
        'dog_pound_address'     => get_return_policy_setting($conn, 'dog_pound_address', ''),
        'dog_pound_notes'       => get_return_policy_setting($conn, 'dog_pound_notes', ''),
        'donation_qr_filename'  => get_return_policy_setting($conn, 'donation_qr_filename', 'donation_qr.jpg'),
        'donation_gcash_name'   => get_return_policy_setting($conn, 'donation_gcash_name', ''),
        'donation_gcash_number' => get_return_policy_setting($conn, 'donation_gcash_number', ''),
        'donation_notes'        => get_return_policy_setting($conn, 'donation_notes', ''),
    ];
}

function save_return_policy_setting(mysqli $conn, string $key, string $value): void {
    $stmt = $conn->prepare(
        "INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, '')
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}
?>
