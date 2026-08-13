<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/smtp_config.php';
require_once __DIR__ . '/application_status_helper.php';
require_once __DIR__ . '/role_permissions_helper.php';
require_once __DIR__ . '/admin_pages/send_email.php';
require_api_login();

$action = $_REQUEST['action'] ?? '';
$actor_id = current_user_id();
if (empty($actor_id)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

function respond($payload) {
    echo json_encode($payload);
    exit;
}

function safeValue($conn, $value) {
    return trim($value);
}

function getPolicySetting($conn, $key, $default = null) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
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

function validatePasswordPolicy($conn, $password) {
    $minLength = intval(getPolicySetting($conn, 'password_min_length', 8));
    $requireLetters = intval(getPolicySetting($conn, 'password_require_letters', 1)) === 1;
    $requireNumbers = intval(getPolicySetting($conn, 'password_require_numbers', 1)) === 1;
    $requireSpecial = intval(getPolicySetting($conn, 'password_require_special_chars', 0)) === 1;

    if (strlen($password) < $minLength) {
        return false;
    }
    if ($requireLetters && !preg_match('/[A-Za-z]/', $password)) {
        return false;
    }
    if ($requireNumbers && !preg_match('/\d/', $password)) {
        return false;
    }
    if ($requireSpecial && !preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    return true;
}

function saveSystemSetting($conn, $key, $value, $description = '') {
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description)");
    if ($stmt) {
        $stmt->bind_param('sss', $key, $value, $description);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}

function handlePetImageUpload($uploadedFile) {
    if (!isset($uploadedFile) || !is_array($uploadedFile) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    if (!is_uploaded_file($uploadedFile['tmp_name'])) {
        return null;
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return false;
    }

    $uploadDir = __DIR__ . '/images';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) ?: 'jpg');
    $filename = 'pet_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
        return false;
    }

    return $filename;
}

function handleMultiplePetImageUploads(?array $files): array
{
    if (
        !$files ||
        !isset($files['name']) ||
        !isset($files['tmp_name']) ||
        !isset($files['error']) ||
        !isset($files['size'])
    ) {
        return [];
    }

    $uploadDirectory = __DIR__ . '/images/';

    if (!is_dir($uploadDirectory)) {
        if (!mkdir($uploadDirectory, 0775, true)) {
            throw new RuntimeException(
                'Unable to create the pet image directory.'
            );
        }
    }

    if (!is_writable($uploadDirectory)) {
        throw new RuntimeException(
            'The pet image directory is not writable.'
        );
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $maximumFileSize = 5 * 1024 * 1024;
    $uploadedFilenames = [];

    $fileNames = is_array($files['name'])
        ? $files['name']
        : [$files['name']];

    $temporaryNames = is_array($files['tmp_name'])
        ? $files['tmp_name']
        : [$files['tmp_name']];

    $errors = is_array($files['error'])
        ? $files['error']
        : [$files['error']];

    $sizes = is_array($files['size'])
        ? $files['size']
        : [$files['size']];

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);

    foreach ($fileNames as $index => $originalName) {
        $error = $errors[$index] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                getUploadErrorMessage($error)
            );
        }

        $temporaryName = $temporaryNames[$index] ?? '';
        $fileSize = (int) ($sizes[$index] ?? 0);

        if (
            $temporaryName === '' ||
            !is_uploaded_file($temporaryName)
        ) {
            throw new RuntimeException(
                'Invalid uploaded image.'
            );
        }

        if ($fileSize <= 0 || $fileSize > $maximumFileSize) {
            throw new RuntimeException(
                'Each image must be smaller than 5 MB.'
            );
        }

        $mimeType = $fileInfo->file($temporaryName);

        if (!isset($allowedMimeTypes[$mimeType])) {
            throw new RuntimeException(
                'Only JPG, PNG, and WEBP images are allowed.'
            );
        }

        $extension = $allowedMimeTypes[$mimeType];

        $filename =
            'pet_' .
            bin2hex(random_bytes(12)) .
            '.' .
            $extension;

        $destination = $uploadDirectory . $filename;

        if (!move_uploaded_file(
            $temporaryName,
            $destination
        )) {
            throw new RuntimeException(
                'Failed to save uploaded image.'
            );
        }

        $uploadedFilenames[] = $filename;
    }

    return $uploadedFilenames;
}

function getUploadErrorMessage(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE =>
            'The uploaded image exceeds the server upload limit.',

        UPLOAD_ERR_FORM_SIZE =>
            'The uploaded image exceeds the form upload limit.',

        UPLOAD_ERR_PARTIAL =>
            'The image was only partially uploaded.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'The server temporary upload directory is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The server could not write the image to storage.',

        UPLOAD_ERR_EXTENSION =>
            'A PHP extension stopped the image upload.',

        default =>
            'An unknown image upload error occurred.'
    };
}

function getSystemSetting($conn, $key, $default = null) {
    return getPolicySetting($conn, $key, $default);
}


function ensureScheduledReportsTable($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS `report_schedules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `report_type` VARCHAR(100) NOT NULL,
        `frequency` VARCHAR(20) NOT NULL DEFAULT 'daily',
        `schedule_hour` TINYINT(2) NOT NULL DEFAULT 8,
        `recipient_email` VARCHAR(150) DEFAULT NULL,
        `enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `last_run_at` DATETIME DEFAULT NULL,
        `next_run_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function calculateNextRun($frequency, $hour = 8, $base = null) {
    $now = new DateTime('now');
    $current = $base ? new DateTime($base) : clone $now;
    $current->setTime($hour, 0, 0);
    if ($frequency === 'weekly') {
        if ($current <= $now) {
            $current->modify('next monday');
        }
        $current->setTime($hour, 0, 0);
        if ($current <= $now) {
            $current->modify('+7 days');
        }
    } elseif ($frequency === 'monthly') {
        if ($current <= $now) {
            $current->modify('first day of next month');
        } else {
            $current->setDate((int)$current->format('Y'), (int)$current->format('m'), 1);
        }
        $current->setTime($hour, 0, 0);
    } else {
        if ($current <= $now) {
            $current->modify('+1 day');
        }
    }
    return $current->format('Y-m-d H:i:s');
}

function getAlertItems($conn) {
    $pendingThreshold = intval(getSystemSetting($conn, 'alert_pending_applications', 10));
    $stalledThreshold = intval(getSystemSetting($conn, 'alert_stalled_applications', 5));
    $unassignedThreshold = intval(getSystemSetting($conn, 'alert_unassigned_applications', 5));
    $threadsThreshold = intval(getSystemSetting($conn, 'alert_threads_running', 25));
    $abortedThreshold = intval(getSystemSetting($conn, 'alert_aborted_connects', 10));

    $items = [];
    $pending = intval($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status = 'pending'")->fetch_row()[0] ?? 0);
    $stalled = intval($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status IN ('pending','screening') AND DATEDIFF(NOW(), created_at) >= 14")->fetch_row()[0] ?? 0);
    $unassigned = intval($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE screened_by IS NULL AND status IN ('pending','screening')")->fetch_row()[0] ?? 0);
    $threadRow = $conn->query("SHOW GLOBAL STATUS LIKE 'Threads_running'")->fetch_assoc();
    $threadsRunning = intval($threadRow['Value'] ?? 0);
    $abortedRow = $conn->query("SHOW GLOBAL STATUS LIKE 'Aborted_connects'")->fetch_assoc();
    $abortedConnects = intval($abortedRow['Value'] ?? 0);

    $items[] = [
        'key' => 'pending_applications',
        'label' => 'Pending applications',
        'value' => $pending,
        'threshold' => $pendingThreshold,
        'active' => $pending >= $pendingThreshold,
        'message' => $pending >= $pendingThreshold ? 'High pending adoption backlog' : 'Healthy pending queue'
    ];
    $items[] = [
        'key' => 'stalled_applications',
        'label' => 'Stalled applications',
        'value' => $stalled,
        'threshold' => $stalledThreshold,
        'active' => $stalled >= $stalledThreshold,
        'message' => $stalled >= $stalledThreshold ? 'Applications stalled for 14+ days' : 'Application flow is healthy'
    ];
    $items[] = [
        'key' => 'unassigned_applications',
        'label' => 'Unassigned applications',
        'value' => $unassigned,
        'threshold' => $unassignedThreshold,
        'active' => $unassigned >= $unassignedThreshold,
        'message' => $unassigned >= $unassignedThreshold ? 'Unassigned applications require review' : 'Review assignments look good'
    ];
    $items[] = [
        'key' => 'threads_running',
        'label' => 'DB threads running',
        'value' => $threadsRunning,
        'threshold' => $threadsThreshold,
        'active' => $threadsRunning >= $threadsThreshold,
        'message' => $threadsRunning >= $threadsThreshold ? 'Database load is elevated' : 'Database thread usage is normal'
    ];
    $items[] = [
        'key' => 'aborted_connects',
        'label' => 'Aborted connects',
        'value' => $abortedConnects,
        'threshold' => $abortedThreshold,
        'active' => $abortedConnects >= $abortedThreshold,
        'message' => $abortedConnects >= $abortedThreshold ? 'Connection failures are rising' : 'Database connection stability is normal'
    ];
    return $items;
}

function generateReportContent($conn, $reportType) {
    $today = date('Y-m-d');
    $period = $today;
    $title = 'Daily Adoption Summary';
    $start = $today;

    if ($reportType === 'weekly_summary') {
        $start = date('Y-m-d', strtotime('-6 days'));
        $title = 'Weekly Adoption Summary';
        $period = "{$start} to {$today}";
    } elseif ($reportType === 'audit_activity') {
        $title = 'Audit Activity Summary';
        $period = 'Last 7 days';
        $start = date('Y-m-d', strtotime('-7 days'));
    }

    $body = "<h2>{$title}</h2>";
    $body .= "<p>Period: {$period}</p>";

    if ($reportType === 'audit_activity') {
        $recentAuditCount = intval($conn->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) >= '" . $conn->real_escape_string($start) . "'")->fetch_row()[0] ?? 0);
        $topActions = [];
        $actionResult = $conn->query("SELECT action_type, COUNT(*) AS total FROM audit_logs WHERE DATE(created_at) >= '" . $conn->real_escape_string($start) . "' GROUP BY action_type ORDER BY total DESC LIMIT 5");
        while ($row = $actionResult->fetch_assoc()) { $topActions[] = $row; }

        $body .= "<ul>";
        $body .= "<li>Total audit events: {$recentAuditCount}</li>";
        $body .= "</ul>";
        $body .= "<h3>Top audit actions</h3>";
        $body .= "<ul>";
        foreach ($topActions as $action) {
            $body .= "<li>" . htmlspecialchars($action['action_type']) . ": " . intval($action['total']) . "</li>";
        }
        $body .= "</ul>";
    } else {
        $userCount = intval($conn->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) >= '" . $conn->real_escape_string($start) . "'")->fetch_row()[0] ?? 0);
        $newApplications = intval($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE DATE(created_at) >= '" . $conn->real_escape_string($start) . "'")->fetch_row()[0] ?? 0);
        $completed = intval($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status = 'completed' AND DATE(created_at) >= '" . $conn->real_escape_string($start) . "'")->fetch_row()[0] ?? 0);
        $pending = intval($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status = 'pending'")->fetch_row()[0] ?? 0);
        $topBreeds = [];
        $result = $conn->query("SELECT p.breed, COUNT(*) AS total FROM adoption_applications aa JOIN pets p ON aa.pet_id = p.id WHERE aa.status = 'completed' AND DATE(aa.created_at) >= '" . $conn->real_escape_string($start) . "' GROUP BY p.breed ORDER BY total DESC LIMIT 5");
        while ($row = $result->fetch_assoc()) { $topBreeds[] = $row; }

        $body .= "<ul>";
        $body .= "<li>New users: {$userCount}</li>";
        $body .= "<li>New applications: {$newApplications}</li>";
        $body .= "<li>Completed adoptions: {$completed}</li>";
        $body .= "<li>Pending applications: {$pending}</li>";
        $body .= "</ul>";
        $body .= "<h3>Top breeds</h3>";
        $body .= "<ul>";
        foreach ($topBreeds as $breed) {
            $body .= "<li>" . htmlspecialchars($breed['breed']) . ": " . intval($breed['total']) . "</li>";
        }
        $body .= "</ul>";
    }

    return [
        'subject' => "AniPet {$title} - {$period}",
        'body' => $body,
    ];
}

function runScheduledReport($conn, $schedule) {
    $report = generateReportContent($conn, $schedule['report_type']);
    $reportsDir = __DIR__ . '/reports';
    if (!is_dir($reportsDir)) { mkdir($reportsDir, 0755, true); }
    $filename = 'report_' . $schedule['id'] . '_' . date('Ymd_His') . '.html';
    $path = $reportsDir . '/' . $filename;
    file_put_contents($path, "<html><body>" . $report['body'] . "</body></html>");
    $sent = false;
    $message = 'Report generated to ' . $path;
    if (!empty($schedule['recipient_email'])) {
        list($success, $emailMessage) = sendEmail($schedule['recipient_email'], $report['subject'], $report['body']);
        $sent = $success;
        $message = $emailMessage;
    }
    $nextRun = calculateNextRun($schedule['frequency'], intval($schedule['schedule_hour']), date('Y-m-d H:i:s'));
    $stmt = $conn->prepare("UPDATE report_schedules SET last_run_at = NOW(), next_run_at = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $nextRun, $schedule['id']);
    $stmt->execute();
    return ['path' => 'php-backend/reports/' . $filename, 'email_sent' => $sent, 'message' => $message, 'next_run_at' => $nextRun];
}

function runDueReports($conn) {
    $schedules = [];
    $result = $conn->query("SELECT * FROM report_schedules WHERE enabled = 1 AND next_run_at IS NOT NULL AND next_run_at <= NOW()");
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    $results = [];
    foreach ($schedules as $schedule) {
        $results[] = runScheduledReport($conn, $schedule);
    }
    return $results;
}

function sendAlertNotification($conn, $subject, $body) {
    $enabled = intval(
        getSystemSetting($conn, 'email_notifications_enabled', 1)
    ) === 1;

    if (!$enabled) {
        return [false, 'Email notifications are disabled'];
    }
    $recipients = getSystemSetting($conn, 'alert_recipient_emails', '');
    $recipients = array_filter(array_map('trim', explode(',', $recipients)));
    if (empty($recipients)) { return [false, 'No notification recipients configured']; }
    $sentAny = false;
    $lastMessage = '';
    foreach ($recipients as $recipient) {
        list($success, $message) = sendEmail($recipient, $subject, $body);
        $sentAny = $sentAny || $success;
        $lastMessage = $message;
    }
    return [$sentAny, $lastMessage];
}

function getAlertNotificationPayload($conn) {
    $alerts = getAlertItems($conn);
    $activeAlerts = array_filter($alerts, fn($item) => $item['active']);
    return [
        'alerts' => array_values($alerts),
        'hasActiveAlerts' => !empty($activeAlerts),
        'activeCount' => count($activeAlerts),
        'emailChannel' => getSystemSetting($conn, 'notification_channel', 'email'),
    ];
}

function sendAlertEmailIfNeeded($conn) {
    $alertEnabled = intval(
        getSystemSetting($conn, 'email_notifications_enabled', 1)
    ) === 1;

    if (!$alertEnabled) {
        return [false, 'Email notifications are disabled'];
    }
    $alerts = getAlertItems($conn);
    $active = array_filter($alerts, fn($item) => $item['active']);
    if (empty($active)) { return [false, 'No active alerts to notify']; }
    $body = '<h2>AniPet Alert Notification</h2><ul>';
    foreach ($active as $item) {
        $body .= '<li>' . htmlspecialchars($item['label']) . ': ' . intval($item['value']) . ' (threshold ' . intval($item['threshold']) . ') - ' . htmlspecialchars($item['message']) . '</li>';
    }
    $body .= '</ul>';
    return sendAlertNotification($conn, 'AniPet alert notification', $body);
}

function getAuditSearchResults($conn) {
    $actionType = trim($_REQUEST['action_type'] ?? '');
    $targetType = trim($_REQUEST['target_type'] ?? '');
    $actor = trim($_REQUEST['actor'] ?? '');
    $keyword = trim($_REQUEST['keyword'] ?? '');
    $dateFrom = trim($_REQUEST['date_from'] ?? '');
    $dateTo = trim($_REQUEST['date_to'] ?? '');
    $limit = intval($_REQUEST['limit'] ?? 100);
    if ($limit <= 0 || $limit > 500) { $limit = 100; }

    $conditions = [];
    if ($actionType !== '') {
        $conditions[] = "al.action_type = '" . $conn->real_escape_string($actionType) . "'";
    }
    if ($targetType !== '') {
        $conditions[] = "al.target_type = '" . $conn->real_escape_string($targetType) . "'";
    }
    if ($actor !== '') {
        $actorEsc = $conn->real_escape_string($actor);
        $conditions[] = "(u.username LIKE '%{$actorEsc}%' OR u.full_name LIKE '%{$actorEsc}%' OR u.email LIKE '%{$actorEsc}%')";
    }
    if ($keyword !== '') {
        $keywordEsc = $conn->real_escape_string($keyword);
        $conditions[] = "(al.details LIKE '%{$keywordEsc}%' OR al.action_type LIKE '%{$keywordEsc}%' OR al.target_type LIKE '%{$keywordEsc}%' OR al.before_data LIKE '%{$keywordEsc}%' OR al.after_data LIKE '%{$keywordEsc}%')";
    }
    if ($dateFrom !== '') {
        $conditions[] = "al.created_at >= '" . $conn->real_escape_string($dateFrom) . " 00:00:00'";
    }
    if ($dateTo !== '') {
        $conditions[] = "al.created_at <= '" . $conn->real_escape_string($dateTo) . " 23:59:59'";
    }

    $where = '';
    if (!empty($conditions)) {
        $where = 'WHERE ' . implode(' AND ', $conditions);
    }
    $sql = "SELECT al.id, al.user_id, al.action_type, al.target_type, al.target_id, al.details, al.before_data, al.after_data, al.ip_address, al.created_at, u.full_name AS actor_name, u.username, u.email FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id {$where} ORDER BY al.created_at DESC LIMIT {$limit}";
    $result = $conn->query($sql);
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    return $logs;
}

function getReportSchedules($conn) {
    $result = $conn->query("SELECT id, name, report_type, frequency, schedule_hour, recipient_email, enabled, last_run_at, next_run_at, created_at, updated_at FROM report_schedules ORDER BY enabled DESC, next_run_at ASC");
    $schedules = [];
    while ($row = $result->fetch_assoc()) { $schedules[] = $row; }
    return $schedules;
}

function saveReportSchedule($conn) {
    $id = intval($_POST['id'] ?? 0);
    $name = safeValue($conn, $_POST['name'] ?? '');
    $reportType = safeValue($conn, $_POST['report_type'] ?? 'daily_summary');
    $frequency = safeValue($conn, $_POST['frequency'] ?? 'daily');
    $scheduleHour = intval($_POST['schedule_hour'] ?? 8);
    $recipientEmail = safeValue($conn, $_POST['recipient_email'] ?? '');
    $enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 0;
    if (!$name || !$reportType) { return ['success' => false, 'message' => 'Missing schedule name or report type']; }
    $nextRun = calculateNextRun($frequency, $scheduleHour);
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE report_schedules SET name = ?, report_type = ?, frequency = ?, schedule_hour = ?, recipient_email = ?, enabled = ?, next_run_at = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('sssisisi', $name, $reportType, $frequency, $scheduleHour, $recipientEmail, $enabled, $nextRun, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO report_schedules (name, report_type, frequency, schedule_hour, recipient_email, enabled, next_run_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssisis', $name, $reportType, $frequency, $scheduleHour, $recipientEmail, $enabled, $nextRun);
    }
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Report schedule saved'];
    }
    return ['success' => false, 'message' => $conn->error];
}

function deleteReportSchedule($conn, $id) {
    $stmt = $conn->prepare("DELETE FROM report_schedules WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Report schedule deleted'];
    }
    return ['success' => false, 'message' => $conn->error];
}

function runReportImmediately($conn, $id) {
    $result = $conn->query("SELECT * FROM report_schedules WHERE id = " . intval($id) . " LIMIT 1");
    if (!$result || !$result->num_rows) { return ['success' => false, 'message' => 'Report schedule not found']; }
    $schedule = $result->fetch_assoc();
    $payload = runScheduledReport($conn, $schedule);
    return ['success' => true, 'message' => 'Report generated', 'payload' => $payload];
}

function getScheduledReportActions($conn) {
    return [
        ['key' => 'daily_summary', 'label' => 'Daily Summary'],
        ['key' => 'weekly_summary', 'label' => 'Weekly Summary'],
        ['key' => 'audit_activity', 'label' => 'Audit Activity Summary']
    ];
}

function getScheduleFrequencies($conn) {
    return [
        ['key' => 'daily', 'label' => 'Daily'],
        ['key' => 'weekly', 'label' => 'Weekly'],
        ['key' => 'monthly', 'label' => 'Monthly']
    ];
}

function registerReportRun($conn, $scheduleId, $path, $recipient, $message) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, target_type, target_id, details, ip_address) VALUES (?, 'run_report', 'report_schedule', ?, ?, ?)");
    $actor = current_user_id();
    $targetId = $scheduleId;
    $details = "Report generated: " . $path . "; recipient=" . $recipient . "; note=" . $message;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt->bind_param('iiss', $actor, $targetId, $details, $ip);
    $stmt->execute();
}

function get_sanitized_report_name($value) {
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $value);
}

function get_current_super_admin_id() {
    return current_user_id();
}

function get_recipient_emails($conn) {
    $emails = getSystemSetting($conn, 'alert_recipient_emails', '');
    return array_filter(array_map('trim', explode(',', $emails)));
}

function send_audit_notification_if_needed($conn) {
    return sendAlertEmailIfNeeded($conn);
}

function get_system_alert_settings($conn) {
    return [
        'pending_threshold' => intval(getSystemSetting($conn, 'alert_pending_applications', 10)),
        'stalled_threshold' => intval(getSystemSetting($conn, 'alert_stalled_applications', 5)),
        'unassigned_threshold' => intval(getSystemSetting($conn, 'alert_unassigned_applications', 5)),
        'threads_threshold' => intval(getSystemSetting($conn, 'alert_threads_running', 25)),
        'aborted_threshold' => intval(getSystemSetting($conn, 'alert_aborted_connects', 10)),
    ];
}

function require_api_permission($conn, $permissionKey) {
    if (current_user_role() === 'super_admin') {
        return;
    }
    if (!has_permission($conn, $permissionKey)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

function authorize_action($conn, $action) {
    switch ($action) {
        case 'get_admins':
        case 'create_admin':
        case 'update_admin':
        case 'delete_admin':
        case 'restore_admin':
        case 'reset_admin_password':
        case 'get_role_permissions':
        case 'save_role_permission':
            require_api_permission($conn, 'manage_admins');
            break;

        case 'get_users':
        case 'suspend_user':
        case 'delete_user':
        case 'restore_user':
        case 'get_user_history':
            require_api_permission($conn, 'manage_users');
            break;

        case 'get_pets':
        case 'create_pet':
        case 'update_pet':
        case 'delete_pet':
        case 'archive_pet':
        case 'unarchive_pet':
        case 'transfer_pet':
            require_api_permission($conn, 'manage_pets');
            break;

        case 'get_applications':
        case 'override_application':
        case 'reopen_application':
            require_api_permission($conn, 'manage_applications');
            break;

        case 'get_audit_logs':
        case 'get_audit_search':
            require_api_permission($conn, 'view_audit_logs');
            break;

        case 'get_sessions':
        case 'terminate_session':
            require_api_permission($conn, 'terminate_sessions');
            break;

        case 'get_shelters':
        case 'get_system_settings':
        case 'save_setting':
        case 'send_test_email':
        case 'create_shelter':
        case 'update_shelter':
        case 'delete_shelter':
            require_api_permission($conn, 'configure_system');
            break;
        case 'get_alert_items':
        case 'send_alert_notification':
            require_api_permission($conn, 'manage_notifications');
            break;
        case 'generate_report':
            require_api_permission($conn, 'generate_reports');
            break;
        case 'get_report_schedules':
        case 'save_report_schedule':
        case 'delete_report_schedule':
        case 'run_report_immediately':
        case 'run_due_reports':
            require_api_permission($conn, 'configure_system');
            break;

        case 'backup_database':
        case 'export_database':
        case 'restore_database':
        case 'get_backups':
            require_api_permission($conn, 'backup_restore_database');
            break;

        case 'save_password_policy':
            require_api_permission($conn, 'update_security_policy');
            break;

        case 'create_sso_token':
            require_api_permission($conn, 'configure_system');
            break;

        case 'cleanup_sso_tokens':
            require_api_permission($conn, 'configure_system');
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
    }
}

function logAudit($conn, $actorId, $type, $targetType = null, $targetId = null, $details = null, $beforeData = null, $afterData = null) {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, target_type, target_id, details, before_data, after_data, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $beforeDataJson = $beforeData !== null ? json_encode($beforeData) : null;
        $afterDataJson = $afterData !== null ? json_encode($afterData) : null;
        $stmt->bind_param('isssssss', $actorId, $type, $targetType, $targetId, $details, $beforeDataJson, $afterDataJson, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

ensureRolePermissionsTable($conn);
ensureScheduledReportsTable($conn);

// Note: pets table auto-migration now lives in db_connect.php so every entry
// point self-heals consistently (not just requests that hit this file first).

authorize_action($conn, $action);

try {
    switch ($action) {
        case 'get_admins':
            $result = $conn->query("SELECT id, username, full_name, email, role, is_verified, is_suspended, is_deleted, created_at FROM users WHERE role IN ('admin', 'super_admin', 'super') ORDER BY created_at DESC");
            $admins = [];
            while ($row = $result->fetch_assoc()) { $admins[] = $row; }
            respond(['success' => true, 'admins' => $admins]);
            break;
        case 'get_alert_items':
            respond(['success' => true, 'alerts' => getAlertItems($conn)]);
            break;
        case 'send_alert_notification':
            list($success, $message) = sendAlertEmailIfNeeded($conn);
            logAudit($conn, $actor_id, 'send_alert_notification', null, null, 'Triggered alert notification: ' . ($success ? 'sent' : 'failed'));
            if ($success) {
                respond(['success' => true, 'message' => $message]);
            }
            respond(['success' => false, 'message' => $message]);
            break;

        case 'create_sso_token':
            // Generate a secure random token and return it to the app
            // The app will use this token to authenticate in the browser
            $tokenRaw = bin2hex(random_bytes(32)); // 64-char hex string
            $tokenHash = hash('sha256', $tokenRaw);
            $tokenId = bin2hex(random_bytes(16));
            $expiresAt = date('Y-m-d H:i:s', time() + 180); // 3 minutes
            
            $stmt = $conn->prepare("INSERT INTO sso_tokens (id, user_id, token_hash, expires_at, used) VALUES (?, ?, ?, ?, 0)");
            if (!$stmt) {
                respond(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
            }
            $stmt->bind_param('siss', $tokenId, $actor_id, $tokenHash, $expiresAt);
            if ($stmt->execute()) {
                $stmt->close();
                logAudit($conn, $actor_id, 'create_sso_token', 'sso_tokens', $tokenId, 'Generated SSO token for browser login');
                respond(['success' => true, 'token' => $tokenRaw, 'expires_in' => 180]);
            } else {
                respond(['success' => false, 'message' => 'Failed to create token: ' . $conn->error]);
            }
            break;

        case 'cleanup_sso_tokens':
            // Delete expired and used SSO tokens
            $deleteStmt = $conn->prepare("DELETE FROM sso_tokens WHERE used = 1 OR expires_at < NOW()");
            if (!$deleteStmt) {
                respond(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
            }
            if ($deleteStmt->execute()) {
                $deletedCount = $conn->affected_rows;
                $deleteStmt->close();
                logAudit($conn, $actor_id, 'cleanup_sso_tokens', 'sso_tokens', null, "Cleaned up expired/used SSO tokens: $deletedCount rows deleted");
                respond(['success' => true, 'message' => "Cleaned up $deletedCount expired tokens", 'deleted_count' => $deletedCount]);
            } else {
                respond(['success' => false, 'message' => 'Failed to cleanup tokens: ' . $conn->error]);
            }
            break;

        case 'get_audit_search':
            respond(['success' => true, 'logs' => getAuditSearchResults($conn)]);
            break;
        case 'get_report_schedules':
            respond(['success' => true, 'schedules' => getReportSchedules($conn), 'actions' => getScheduledReportActions($conn), 'frequencies' => getScheduleFrequencies($conn)]);
            break;
        case 'save_report_schedule':
            $result = saveReportSchedule($conn);
            if ($result['success']) {
                logAudit($conn, $actor_id, 'save_report_schedule', 'report_schedules', null, 'Saved scheduled report: ' . ($_POST['name'] ?? '')); 
            }
            respond($result);
            break;
        case 'delete_report_schedule':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing schedule id']); }
            $result = deleteReportSchedule($conn, $id);
            if ($result['success']) {
                logAudit($conn, $actor_id, 'delete_report_schedule', 'report_schedules', $id, 'Deleted scheduled report');
            }
            respond($result);
            break;
        case 'run_report_immediately':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing schedule id']); }
            $result = runReportImmediately($conn, $id);
            if ($result['success']) {
                logAudit($conn, $actor_id, 'run_report_immediately', 'report_schedules', $id, 'Manually ran scheduled report');
            }
            respond($result);
            break;
        case 'run_due_reports':
            $results = runDueReports($conn);
            logAudit($conn, $actor_id, 'run_due_reports', null, null, 'Executed due report schedules: ' . count($results));
            respond(['success' => true, 'results' => $results]);
            break;

        case 'get_users':
            $result = $conn->query("SELECT id, username, full_name, email, role, is_verified, is_suspended, is_deleted, created_at FROM users WHERE role NOT IN ('admin', 'super_admin', 'super') ORDER BY created_at DESC");
            $users = [];
            while ($row = $result->fetch_assoc()) { $users[] = $row; }
            respond(['success' => true, 'users' => $users]);
            break;

        case 'get_pets':
            $result = $conn->query("SELECT id, name, breed, age, gender, status, health_status, description, image, is_archived, shelter_id, created_at FROM pets ORDER BY created_at DESC");
            $pets = [];
            while ($row = $result->fetch_assoc()) { $pets[] = $row; }
            respond(['success' => true, 'pets' => $pets]);
            break;

        case 'get_applications':
            $result = $conn->query("SELECT aa.id, aa.pet_id, aa.user_id, aa.applicant_name, aa.status, aa.admin_notes, aa.interview_datetime, aa.created_at, p.name AS pet_name, u.full_name AS applicant_name_full FROM adoption_applications aa JOIN pets p ON aa.pet_id = p.id JOIN users u ON aa.user_id = u.id ORDER BY aa.created_at DESC");
            $apps = [];
            while ($row = $result->fetch_assoc()) { $apps[] = $row; }
            respond(['success' => true, 'applications' => $apps]);
            break;

        case 'get_audit_logs':
            $result = $conn->query("SELECT al.id, al.user_id, al.action_type, al.target_type, al.target_id, al.details, al.before_data, al.after_data, al.ip_address, al.created_at, u.full_name AS actor_name, u.username, u.email FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 100");
            $logs = [];
            while ($row = $result->fetch_assoc()) { $logs[] = $row; }
            respond(['success' => true, 'logs' => $logs]);
            break;

        case 'get_sessions':
            $result = $conn->query("SELECT us.id, us.user_id, us.session_id, us.ip_address, us.user_agent, us.created_at, us.last_active_at, us.is_active, u.username, u.email, u.full_name, u.role FROM user_sessions us LEFT JOIN users u ON us.user_id = u.id ORDER BY us.last_active_at DESC LIMIT 100");
            $sessions = [];
            while ($row = $result->fetch_assoc()) { $sessions[] = $row; }
            respond(['success' => true, 'sessions' => $sessions]);
            break;

        case 'terminate_session':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing session id']); }
            $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'terminate_session', 'user_sessions', $id, 'Terminated an active login session');
                respond(['success' => true, 'message' => 'Session terminated']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'get_system_settings':
            $result = $conn->query("SELECT id, setting_key, setting_value, description, updated_at FROM system_settings ORDER BY setting_key ASC");
            $settings = [];
            while ($row = $result->fetch_assoc()) { $settings[] = $row; }
            respond(['success' => true, 'settings' => $settings]);
            break;

        case 'get_role_permissions':
            $result = $conn->query("SELECT id, role, permission_key, is_allowed FROM role_permissions ORDER BY role ASC, permission_key ASC");
            $permissions = [];
            while ($row = $result->fetch_assoc()) { $permissions[] = $row; }
            respond(['success' => true, 'permissions' => $permissions]);
            break;

        case 'save_role_permission':
            $role = safeValue($conn, $_POST['role'] ?? '');
            $permissionKey = safeValue($conn, $_POST['permission_key'] ?? '');
            $isAllowed = isset($_POST['is_allowed']) ? intval($_POST['is_allowed']) : 0;
            if (!$role || !$permissionKey) { respond(['success' => false, 'message' => 'Missing role or permission key']); }
            $stmt = $conn->prepare("INSERT INTO role_permissions (role, permission_key, is_allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)");
            $stmt->bind_param('ssi', $role, $permissionKey, $isAllowed);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'save_role_permission', 'role_permissions', null, "Updated permission {$permissionKey} for role {$role}", null, ['permission_key' => $permissionKey, 'is_allowed' => $isAllowed]);
                respond(['success' => true, 'message' => 'Permission updated']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'save_password_policy':
            $minLength = intval($_POST['min_length'] ?? 8);
            $requireLetters = isset($_POST['require_letters']) ? intval($_POST['require_letters']) : 0;
            $requireNumbers = isset($_POST['require_numbers']) ? intval($_POST['require_numbers']) : 0;
            $requireSpecial = isset($_POST['require_special_chars']) ? intval($_POST['require_special_chars']) : 0;
            saveSystemSetting($conn, 'password_min_length', strval($minLength), 'Minimum password length');
            saveSystemSetting($conn, 'password_require_letters', strval($requireLetters), 'Require letters in password');
            saveSystemSetting($conn, 'password_require_numbers', strval($requireNumbers), 'Require digits in password');
            saveSystemSetting($conn, 'password_require_special_chars', strval($requireSpecial), 'Require special characters in password');
            logAudit($conn, $actor_id, 'update_security_policy', 'system_settings', null, 'Updated password policy', null, ['min_length' => $minLength, 'require_letters' => $requireLetters, 'require_numbers' => $requireNumbers, 'require_special_chars' => $requireSpecial]);
            respond(['success' => true, 'message' => 'Password policy saved']);
            break;

        case 'create_pet':
    $name = safeValue($conn, $_POST['name'] ?? '');
    $breed = safeValue($conn, $_POST['breed'] ?? '');
    $age = safeValue($conn, $_POST['age'] ?? '');
    $gender = safeValue($conn, $_POST['gender'] ?? '');
    $description = safeValue($conn, $_POST['description'] ?? '');
    $health_status = safeValue($conn, $_POST['health_status'] ?? '');
    $status = safeValue($conn, $_POST['status'] ?? 'available');
    if (!in_array($status, ['available', 'reserved', 'in_adoption', 'adopted', 'under_treatment'])) $status = 'available';
    $shelter_id = intval($_POST['shelter_id'] ?? 0);

    if (!$name || !$breed) { respond(['success' => false, 'message' => 'Name and breed are required']); }

    try {
    $uploadedFilenames =
        handleMultiplePetImageUploads(
            $_FILES['images'] ?? null
        );
} catch (Throwable $error) {
    respond([
        'success' => false,
        'message' => $error->getMessage()
    ], 422);
}

    if (count($uploadedFilenames) > 10) { respond(['success' => false, 'message' => 'Maximum 10 photos per pet']); }
    $imageString = $uploadedFilenames ? implode(',', $uploadedFilenames) : null;

    $stmt = $conn->prepare("INSERT INTO pets (name, breed, age, gender, description, health_status, image, status, shelter_id, is_archived, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param('ssssssssi', $name, $breed, $age, $gender, $description, $health_status, $imageString, $status, $shelter_id);
    if ($stmt->execute()) {
        logAudit($conn, $actor_id, 'create_pet', 'pet', $stmt->insert_id, "Created pet {$name}");
        respond(['success' => true, 'message' => 'Pet created successfully']);
    }
    respond(['success' => false, 'message' => $conn->error]);
    break;

        case 'update_pet':
    $id = intval($_POST['id'] ?? 0);
    $name = safeValue($conn, $_POST['name'] ?? '');
    $breed = safeValue($conn, $_POST['breed'] ?? '');
    $age = safeValue($conn, $_POST['age'] ?? '');
    $gender = safeValue($conn, $_POST['gender'] ?? '');
    $description = safeValue($conn, $_POST['description'] ?? '');
    $health_status = safeValue($conn, $_POST['health_status'] ?? '');
    $status = safeValue($conn, $_POST['status'] ?? 'available');
    if (!in_array($status, ['available', 'reserved', 'in_adoption', 'adopted', 'under_treatment'])) $status = 'available';
    $shelter_id = intval($_POST['shelter_id'] ?? 0);

    if (!$id || !$name || !$breed) { respond(['success' => false, 'message' => 'Pet id, name, and breed are required']); }

    // Load current images
    $cur = $conn->prepare("SELECT image FROM pets WHERE id = ?");
    $cur->bind_param('i', $id);
    $cur->execute();
    $row = $cur->get_result()->fetch_assoc();
    $cur->close();
    $existing = $row && $row['image'] ? explode(',', $row['image']) : [];

    // Remove any the super admin deleted in the UI
    $removed = !empty($_POST['removed_images']) ? explode(',', $_POST['removed_images']) : [];
    if ($removed) {
        foreach ($removed as $rf) {
            $rf = trim($rf);
            if ($rf === '') continue;
            @unlink(__DIR__ . '/images/' . $rf);
        }
        $existing = array_values(array_diff($existing, $removed));
    }

    // Add newly uploaded files
    try {
    $uploadedFilenames =
        handleMultiplePetImageUploads(
            $_FILES['images'] ?? null
        );
} catch (Throwable $error) {
    respond([
        'success' => false,
        'message' => $error->getMessage()
    ], 422);
}

    if (count($existing) + count($uploadedFilenames) > 10) {
        respond(['success' => false, 'message' => 'Maximum 10 photos per pet']);
    }
    $existing = array_merge($existing, $uploadedFilenames);
    $imageString = $existing ? implode(',', $existing) : null;

    $stmt = $conn->prepare("UPDATE pets SET name = ?, breed = ?, age = ?, gender = ?, description = ?, health_status = ?, image = ?, status = ?, shelter_id = ? WHERE id = ?");
    $stmt->bind_param('ssssssssii', $name, $breed, $age, $gender, $description, $health_status, $imageString, $status, $shelter_id, $id);
    if ($stmt->execute()) {
        logAudit($conn, $actor_id, 'update_pet', 'pet', $id, "Updated pet {$name}");
        respond(['success' => true, 'message' => 'Pet updated successfully']);
    }
    respond(['success' => false, 'message' => $conn->error]);
    break;

        case 'delete_pet':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing pet id']); }
            $stmt = $conn->prepare("DELETE FROM pets WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'delete_pet', 'pet', $id, 'Deleted pet record');
                respond(['success' => true, 'message' => 'Pet deleted successfully']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'get_shelters':
            $result = $conn->query("SELECT id, name, address, phone, email, status, created_at FROM shelters ORDER BY name ASC");
            $shelters = [];
            while ($row = $result->fetch_assoc()) { $shelters[] = $row; }
            respond(['success' => true, 'shelters' => $shelters]);
            break;

        case 'create_admin':
            $full_name = safeValue($conn, $_POST['full_name'] ?? '');
            $email = safeValue($conn, $_POST['email'] ?? '');
            $role = safeValue($conn, $_POST['role'] ?? 'admin');
            $password = $_POST['password'] ?? '';
            if (!$full_name || !$email || !$password) {
                respond(['success' => false, 'message' => 'Missing required admin fields']);
            }
            if (!validatePasswordPolicy($conn, $password)) {
                respond(['success' => false, 'message' => 'Password does not meet current security policy']);
            }
            $nameParts = preg_split('/\s+/', trim($full_name));
            $first_name = $nameParts[0];
            $last_name = count($nameParts) > 1 ? end($nameParts) : $nameParts[0];
            $username = strtolower(preg_replace('/[^a-z0-9._-]+/i', '.', $first_name));
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, full_name, first_name, last_name, email, password, role, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmt->bind_param('sssssss', $username, $full_name, $first_name, $last_name, $email, $hashed, $role);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'create_admin', 'user', $stmt->insert_id, "Created admin {$email}");
                respond(['success' => true, 'message' => 'Admin created successfully']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'reset_admin_password':
            $id = intval($_POST['id'] ?? 0);
            $password = $_POST['password'] ?? '';
            if (!$id || !$password) {
                respond(['success' => false, 'message' => 'Missing required fields']);
            }
            if (!validatePasswordPolicy($conn, $password)) {
                respond(['success' => false, 'message' => 'Password does not meet current security policy']);
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role IN ('admin', 'super_admin', 'super')");
            $stmt->bind_param('si', $hash, $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'reset_admin_password', 'user', $id, 'Reset admin password');
                respond(['success' => true, 'message' => 'Password reset successfully']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'update_admin':
            $id = intval($_POST['id'] ?? 0);
            $full_name = safeValue($conn, $_POST['full_name'] ?? '');
            $email = safeValue($conn, $_POST['email'] ?? '');
            $role = safeValue($conn, $_POST['role'] ?? 'admin');
            $is_suspended = isset($_POST['is_suspended']) ? intval($_POST['is_suspended']) : 0;
            $is_deleted = isset($_POST['is_deleted']) ? intval($_POST['is_deleted']) : 0;
            if (!$id || !$full_name || !$email) { respond(['success' => false, 'message' => 'Missing required admin fields']); }
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, is_suspended = ?, is_deleted = ? WHERE id = ? AND role IN ('admin', 'super_admin', 'super')");
            $stmt->bind_param('sssiii', $full_name, $email, $role, $is_suspended, $is_deleted, $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'update_admin', 'user', $id, "Updated admin {$email}");
                respond(['success' => true, 'message' => 'Admin profile updated']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'delete_admin':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing admin id']); }

            if ($id === intval($actor_id)) {
                respond(['success' => false, 'message' => 'You cannot permanently delete your own account.']);
            }

            $targetStmt = $conn->prepare("SELECT id, username, full_name, email, role FROM users WHERE id = ? AND role IN ('admin', 'super_admin', 'super') LIMIT 1");
            $targetStmt->bind_param('i', $id);
            $targetStmt->execute();
            $targetAdmin = $targetStmt->get_result()->fetch_assoc();
            $targetStmt->close();

            if (!$targetAdmin) {
                respond(['success' => false, 'message' => 'Admin account not found.']);
            }

            if (in_array($targetAdmin['role'], ['super_admin', 'super'], true)) {
                $countResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role IN ('super_admin', 'super') AND is_deleted = 0");
                $activeSuperAdmins = intval($countResult->fetch_assoc()['total'] ?? 0);
                if ($activeSuperAdmins <= 1) {
                    respond(['success' => false, 'message' => 'The last active Super Admin cannot be deleted.']);
                }
            }

            $conn->begin_transaction();
            try {
                $relatedTables = [
                    'user_notifications',
                    'password_reset_otps',
                    'sso_tokens',
                    'user_sessions',
                    'return_requests',
                    'appointments',
                    'adoption_applications',
                    'adoption_records',
                    'audit_logs'
                ];

                foreach ($relatedTables as $table) {
                    $deleteRelated = $conn->prepare("DELETE FROM `{$table}` WHERE user_id = ?");
                    if (!$deleteRelated) {
                        throw new RuntimeException("Unable to prepare cleanup for {$table}: " . $conn->error);
                    }
                    $deleteRelated->bind_param('i', $id);
                    if (!$deleteRelated->execute()) {
                        throw new RuntimeException("Unable to clean {$table}: " . $deleteRelated->error);
                    }
                    $deleteRelated->close();
                }

                if (!empty($targetAdmin['email'])) {
                    $otpDelete = $conn->prepare("DELETE FROM otps WHERE email = ?");
                    if ($otpDelete) {
                        $otpDelete->bind_param('s', $targetAdmin['email']);
                        $otpDelete->execute();
                        $otpDelete->close();
                    }
                }

                $deleteUser = $conn->prepare("DELETE FROM users WHERE id = ? AND role IN ('admin', 'super_admin', 'super')");
                $deleteUser->bind_param('i', $id);
                if (!$deleteUser->execute() || $deleteUser->affected_rows !== 1) {
                    throw new RuntimeException('Admin account could not be permanently deleted.');
                }
                $deleteUser->close();

                $conn->commit();

                logAudit(
                    $conn,
                    $actor_id,
                    'permanent_delete_admin',
                    'user',
                    $id,
                    'Permanently deleted admin account ID ' . $id
                );

                respond(['success' => true, 'message' => 'Admin account permanently deleted from AniPet.']);
            } catch (Throwable $deleteError) {
                $conn->rollback();
                respond(['success' => false, 'message' => 'Permanent deletion failed: ' . $deleteError->getMessage()]);
            }
            break;

        case 'restore_admin':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing admin id']); }
            $stmt = $conn->prepare("UPDATE users SET is_deleted = 0, is_suspended = 0 WHERE id = ? AND role IN ('admin', 'super_admin', 'super')");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'restore_admin', 'user', $id, 'Restored admin account');
                respond(['success' => true, 'message' => 'Admin restored successfully']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'suspend_user':
        case 'delete_user':
        case 'restore_user':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing user id']); }

            if ($action === 'delete_user') {
                if ($id === intval($actor_id)) {
                    respond(['success' => false, 'message' => 'You cannot permanently delete your own account.']);
                }

                $targetStmt = $conn->prepare("SELECT id, username, full_name, email, role FROM users WHERE id = ? AND role NOT IN ('admin', 'super_admin', 'super') LIMIT 1");
                $targetStmt->bind_param('i', $id);
                $targetStmt->execute();
                $targetUser = $targetStmt->get_result()->fetch_assoc();
                $targetStmt->close();

                if (!$targetUser) {
                    respond(['success' => false, 'message' => 'User account not found.']);
                }

                $conn->begin_transaction();
                try {
                    $relatedTables = [
                        'user_notifications',
                        'password_reset_otps',
                        'sso_tokens',
                        'user_sessions',
                        'return_requests',
                        'appointments',
                        'adoption_applications',
                        'adoption_records',
                        'audit_logs'
                    ];

                    foreach ($relatedTables as $table) {
                        $deleteRelated = $conn->prepare("DELETE FROM `{$table}` WHERE user_id = ?");
                        if (!$deleteRelated) {
                            throw new RuntimeException("Unable to prepare cleanup for {$table}: " . $conn->error);
                        }
                        $deleteRelated->bind_param('i', $id);
                        if (!$deleteRelated->execute()) {
                            throw new RuntimeException("Unable to clean {$table}: " . $deleteRelated->error);
                        }
                        $deleteRelated->close();
                    }

                    if (!empty($targetUser['email'])) {
                        $otpDelete = $conn->prepare("DELETE FROM otps WHERE email = ?");
                        if ($otpDelete) {
                            $otpDelete->bind_param('s', $targetUser['email']);
                            $otpDelete->execute();
                            $otpDelete->close();
                        }
                    }

                    $deleteUser = $conn->prepare("DELETE FROM users WHERE id = ? AND role NOT IN ('admin', 'super_admin', 'super')");
                    $deleteUser->bind_param('i', $id);
                    if (!$deleteUser->execute() || $deleteUser->affected_rows !== 1) {
                        throw new RuntimeException('User account could not be permanently deleted.');
                    }
                    $deleteUser->close();

                    $conn->commit();

                    logAudit(
                        $conn,
                        $actor_id,
                        'permanent_delete_user',
                        'user',
                        $id,
                        'Permanently deleted user account ID ' . $id
                    );

                    respond(['success' => true, 'message' => 'User account permanently deleted from AniPet.']);
                } catch (Throwable $deleteError) {
                    $conn->rollback();
                    respond(['success' => false, 'message' => 'Permanent deletion failed: ' . $deleteError->getMessage()]);
                }
            }

            if ($action === 'suspend_user') {
                $stmt = $conn->prepare("UPDATE users SET is_suspended = 1 WHERE id = ? AND role NOT IN ('admin', 'super_admin', 'super')");
            } else {
                $stmt = $conn->prepare("UPDATE users SET is_deleted = 0, is_suspended = 0 WHERE id = ? AND role NOT IN ('admin', 'super_admin', 'super')");
            }

            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, $action, 'user', $id, ucfirst(str_replace('_', ' ', $action)) . ' executed');
                respond(['success' => true, 'message' => 'User updated successfully']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'archive_pet':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing pet id']); }
            $stmt = $conn->prepare("UPDATE pets SET is_archived = 1 WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'archive_pet', 'pet', $id, 'Archived pet record');
                respond(['success' => true, 'message' => 'Pet archived']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'unarchive_pet':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing pet id']); }
            $stmt = $conn->prepare("UPDATE pets SET is_archived = 0 WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'unarchive_pet', 'pet', $id, 'Unarchived pet record');
                respond(['success' => true, 'message' => 'Pet unarchived']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'transfer_pet':
            $id = intval($_POST['id'] ?? 0);
            $shelter_id = intval($_POST['shelter_id'] ?? 0);
            if (!$id || !$shelter_id) { respond(['success' => false, 'message' => 'Missing pet or shelter id']); }
            $stmt = $conn->prepare("UPDATE pets SET shelter_id = ? WHERE id = ?");
            $stmt->bind_param('ii', $shelter_id, $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'transfer_pet', 'pet', $id, "Transferred pet to shelter {$shelter_id}");
                respond(['success' => true, 'message' => 'Pet transferred']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'override_application':
            $id = intval($_POST['id'] ?? 0);
            $status = safeValue($conn, $_POST['status'] ?? 'pending');
            $admin_notes = safeValue($conn, $_POST['admin_notes'] ?? 'Overridden by Super Admin');
            if (!$id) { respond(['success' => false, 'message' => 'Missing application id']); }

            // Routed through the shared helper (not a raw UPDATE) so an override to
            // 'approved' still generates/emails the QR and keeps pets.status in sync —
            // same pipeline the regular admin side and the Android app rely on.
            $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
            $result = applyApplicationStatusChange($conn, $id, $status, $actor_id, $admin_notes, null, $base_url);
            if ($result['success']) {
                logAudit($conn, $actor_id, 'override_application', 'adoption_application', $id, "Force status {$status}");
                respond(['success' => true, 'message' => 'Application updated', 'qr_code' => $result['qr_code'] ?? null]);
            }
            respond(['success' => false, 'message' => $result['message']]);
            break;

        case 'reopen_application':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing application id']); }

            $noteStmt = $conn->prepare("SELECT admin_notes FROM adoption_applications WHERE id = ?");
            $noteStmt->bind_param('i', $id);
            $noteStmt->execute();
            $existingNotes = $noteStmt->get_result()->fetch_assoc()['admin_notes'] ?? '';
            $noteStmt->close();
            $newNotes = trim($existingNotes . ' Reopened by Super Admin.');

            // Reopening to 'pending' also releases the pet back to available and
            // clears the stale QR from the prior outcome (see application_status_helper.php).
            $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
            $result = applyApplicationStatusChange($conn, $id, 'pending', $actor_id, $newNotes, null, $base_url);
            if ($result['success']) {
                logAudit($conn, $actor_id, 'reopen_application', 'adoption_application', $id, 'Reopened rejected/completed application');
                respond(['success' => true, 'message' => 'Application reopened']);
            }
            respond(['success' => false, 'message' => $result['message']]);
            break;

        case 'save_setting':
            $key = safeValue($conn, $_POST['key'] ?? '');
            $value = safeValue($conn, $_POST['value'] ?? '');
            if (!$key) { respond(['success' => false, 'message' => 'Missing setting key']); }
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, '') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->bind_param('ss', $key, $value);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'save_setting', 'system_settings', null, "Saved setting {$key}");
                respond(['success' => true, 'message' => 'Setting saved']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'send_test_email':
            $recipient = safeValue($conn, $_POST['recipient_email'] ?? '');
            $subject = safeValue($conn, $_POST['subject'] ?? 'AniPet SMTP Test Email');
            $body = safeValue($conn, $_POST['body'] ?? '<p>This is a test email from AniPet Super Admin.</p>');
            if (!$recipient) { respond(['success' => false, 'message' => 'Missing recipient email']); }
            list($success, $message) = sendEmail($recipient, $subject, $body);
            if ($success) {
                logAudit($conn, $actor_id, 'send_test_email', 'system_settings', null, "Sent SMTP test email to {$recipient}");
                respond(['success' => true, 'message' => $message]);
            }
            respond(['success' => false, 'message' => $message]);
            break;

        case 'get_backups':
            $backupDir = __DIR__ . '/backups';
            $backups = [];
            if (is_dir($backupDir)) {
                $files = array_values(array_filter(scandir($backupDir), function ($file) use ($backupDir) {
                    return is_file($backupDir . '/' . $file) && preg_match('/\.sql$/i', $file);
                }));
                rsort($files);
                foreach ($files as $file) {
                    $backups[] = ['file' => $file, 'path' => 'php-backend/backups/' . $file];
                }
            }
            respond(['success' => true, 'backups' => $backups]);
            break;

        case 'export_database':
            $backupDir = __DIR__ . '/backups';
            if (!is_dir($backupDir)) { mkdir($backupDir, 0755, true); }
            $filename = 'anipet_export_' . date('Ymd_His') . '.sql';
            $tempPath = $backupDir . '/' . $filename . '.tmp';
            $finalPath = $backupDir . '/' . $filename;
            $tables = ['users', 'pets', 'adoption_applications', 'adoption_records', 'appointments', 'shelters', 'system_settings', 'audit_logs'];
            $dump = "-- AniPet export generated " . date('c') . "\n";
            foreach ($tables as $table) {
                $res = $conn->query("SHOW CREATE TABLE `{$table}`");
                if ($res && $row = $res->fetch_assoc()) {
                    $dump .= "\nDROP TABLE IF EXISTS `{$table}`;\n" . $row['Create Table'] . ";\n\n";
                    $data = $conn->query("SELECT * FROM `{$table}`");
                    while ($record = $data->fetch_assoc()) {
                        $columns = array_map(function($col) { return "`{$col}`"; }, array_keys($record));
                        $values = array_map(function($value) use ($conn) {
                            if ($value === null) return 'NULL';
                            return "'" . $conn->real_escape_string($value) . "'";
                        }, array_values($record));
                        $dump .= "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ");\n";
                    }
                    $dump .= "\n";
                }
            }
            // write atomically
            if (file_put_contents($tempPath, $dump) === false || !rename($tempPath, $finalPath)) {
                if (is_file($tempPath)) { @unlink($tempPath); }
                respond(['success' => false, 'message' => 'Failed to create export file']);
            }
            logAudit($conn, $actor_id, 'export_database', null, null, "Database exported: {$filename}");
            respond(['success' => true, 'message' => 'Database export created', 'path' => 'php-backend/backups/' . $filename]);
            break;

        case 'restore_database':
            $fileInput = $_POST['file'] ?? '';
            $file = basename($fileInput);
            if (!$file) { respond(['success' => false, 'message' => 'Missing backup file']); }
            // ensure filename matches expected pattern to avoid traversal
            if (!preg_match('/^(anipet_backup_|anipet_export_).+\.sql$/i', $file)) {
                respond(['success' => false, 'message' => 'Invalid backup file name']);
            }
            $backupDir = __DIR__ . '/backups';
            $path = $backupDir . '/' . $file;
            if (!is_file($path) || !is_readable($path)) { respond(['success' => false, 'message' => 'Backup file not found or unreadable']); }
            $sql = file_get_contents($path);
            if ($sql === false) { respond(['success' => false, 'message' => 'Could not read backup file']); }
            // execute in transaction where possible
            try {
                $conn->begin_transaction();
                if (!$conn->multi_query($sql)) {
                    $conn->rollback();
                    respond(['success' => false, 'message' => 'Restore failed to start: ' . $conn->error]);
                }
                // iterate through results
                do {
                    if ($res = $conn->store_result()) { $res->free(); }
                } while ($conn->more_results() && $conn->next_result());
                $conn->commit();
                logAudit($conn, $actor_id, 'restore_database', null, null, "Database restored from: {$file}");
                respond(['success' => true, 'message' => 'Database restore executed successfully']);
            } catch (Throwable $e) {
                $conn->rollback();
                respond(['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()]);
            }
            break;

        case 'create_shelter':
            $name = safeValue($conn, $_POST['name'] ?? '');
            $address = safeValue($conn, $_POST['address'] ?? '');
            $phone = safeValue($conn, $_POST['phone'] ?? '');
            $email = safeValue($conn, $_POST['email'] ?? '');
            $status = safeValue($conn, $_POST['status'] ?? 'active');
            if (!$name) { respond(['success' => false, 'message' => 'Missing shelter name']); }
            $stmt = $conn->prepare("INSERT INTO shelters (name, address, phone, email, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('sssss', $name, $address, $phone, $email, $status);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'create_shelter', 'shelter', $stmt->insert_id, "Created shelter {$name}");
                respond(['success' => true, 'message' => 'Shelter created successfully']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'update_shelter':
            $id = intval($_POST['id'] ?? 0);
            $name = safeValue($conn, $_POST['name'] ?? '');
            $address = safeValue($conn, $_POST['address'] ?? '');
            $phone = safeValue($conn, $_POST['phone'] ?? '');
            $email = safeValue($conn, $_POST['email'] ?? '');
            $status = safeValue($conn, $_POST['status'] ?? 'active');
            if (!$id || !$name) { respond(['success' => false, 'message' => 'Missing required shelter fields']); }
            $stmt = $conn->prepare("UPDATE shelters SET name = ?, address = ?, phone = ?, email = ?, status = ? WHERE id = ?");
            $stmt->bind_param('sssssi', $name, $address, $phone, $email, $status, $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'update_shelter', 'shelter', $id, "Updated shelter {$name}");
                respond(['success' => true, 'message' => 'Shelter updated successfully']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'delete_shelter':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { respond(['success' => false, 'message' => 'Missing shelter id']); }
            $stmt = $conn->prepare("DELETE FROM shelters WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                logAudit($conn, $actor_id, 'delete_shelter', 'shelter', $id, 'Deleted shelter record');
                respond(['success' => true, 'message' => 'Shelter deleted successfully']);
            }
            respond(['success' => false, 'message' => $conn->error]);
            break;

        case 'backup_database':
            $backupDir = __DIR__ . '/backups';
            if (!is_dir($backupDir)) { mkdir($backupDir, 0755, true); }
            $filename = 'anipet_backup_' . date('Ymd_His') . '.sql';
            $path = $backupDir . '/' . $filename;
            $tables = ['users', 'pets', 'adoption_applications', 'adoption_records', 'appointments', 'shelters', 'system_settings', 'audit_logs'];
            $dump = "-- AniPet backup generated " . date('c') . "\n";
            foreach ($tables as $table) {
                $res = $conn->query("SHOW CREATE TABLE `{$table}`");
                if ($res && $row = $res->fetch_assoc()) {
                    $dump .= "\nDROP TABLE IF EXISTS `{$table}`;\n" . $row['Create Table'] . ";\n\n";
                    $data = $conn->query("SELECT * FROM `{$table}`");
                    while ($record = $data->fetch_assoc()) {
                        $columns = array_map(function($col) { return "`{$col}`"; }, array_keys($record));
                        $values = array_map(function($value) use ($conn) {
                            if ($value === null) return 'NULL';
                            return "'" . $conn->real_escape_string($value) . "'";
                        }, array_values($record));
                        $dump .= "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ");\n";
                    }
                    $dump .= "\n";
                }
            }
            file_put_contents($path, $dump);
            logAudit($conn, $actor_id, 'backup_database', null, null, "Database backup created: {$filename}");
            respond(['success' => true, 'message' => 'Backup created', 'path' => 'php-backend/backups/' . $filename]);
            break;

        default:
            respond(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Throwable $t) {
    respond(['success' => false, 'message' => 'Server error: ' . $t->getMessage()]);
} finally {
    $conn->close();
}