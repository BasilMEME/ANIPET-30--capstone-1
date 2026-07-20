<?php
// Single source of truth for the adoption-application status pipeline so the
// Android tracking screen, the QR feature, and every admin entry point
// (approve link, admin_api.php status modal, update_application_status.php)
// stay in sync.
require_once __DIR__ . '/smtp_config.php';
require_once __DIR__ . '/admin_pages/send_email.php';
require_once __DIR__ . '/admin_pages/email_templates.php';

const APPLICATION_STATUS_PIPELINE = ['pending', 'screening', 'approved', 'for_releasing', 'ready_pickup', 'completed', 'rejected'];

function generateApplicationQRCode($data, $app_id) {
    $qr_dir = __DIR__ . '/qrcodes';
    if (!is_dir($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }

    $qr_filename = "qr_app_{$app_id}_" . time() . ".png";
    $qr_path = "qrcodes/{$qr_filename}";

    try {
        $encoded_data = urlencode($data);
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . $encoded_data;

        $context = stream_context_create([
            'http' => [
                'timeout' => 5
            ]
        ]);

        $qr_image = @file_get_contents($qr_url, false, $context);

        if ($qr_image && strlen($qr_image) > 0) {
            file_put_contents($qr_dir . '/' . $qr_filename, $qr_image);
            return $qr_path;
        }
    } catch (Exception $e) {
        error_log("QR code generation failed: " . $e->getMessage());
    }

    return null;
}

function sendApplicationApprovalQrEmail($toEmail, $toName, $qrFilePath, $qrDownloadUrl) {
    if (!defined('USE_SMTP') || !USE_SMTP) {
        return false;
    }

    if (!file_exists(PHPMailer_AUTOLOAD)) {
        error_log("PHPMailer autoload not found; cannot send approval QR email");
        return false;
    }

    try {
        require_once PHPMailer_AUTOLOAD;
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        if (USE_SMTP) {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
        }

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName ?: 'Applicant');
        $mail->Subject = 'AniPet Adoption Approved - Your QR Code';
        $mail->isHTML(true);
        $mail->Body = "<p>Congratulations! Your AniPet adoption application has been approved.</p>"
            . "<p>Your QR code is attached below. Show this code when visiting the shelter.</p>"
            . "<p style='text-align:center;margin:20px 0;'><img src=\"cid:{$qrFilePath}\" alt='QR Code' style='max-width:200px;'></p>"
            . "<p><strong>💚 Support AniPet:</strong></p>"
            . "<p>Help us continue our mission! Scan the QR Ph code in the app or use one of these links:</p>"
            . "<ul><li>GCash: www.qrph.com/anipet</li><li>PayMaya: www.qrph.com/anipet</li></ul>"
            . "<p style='color:#666;font-size:12px;'>Questions? Contact us at support@anipet.com</p>";

        if (file_exists($qrFilePath)) {
            $mail->addAttachment($qrFilePath, basename($qrFilePath));
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Approval QR email failed: ' . $e->getMessage());
        return false;
    }
}

// Creates (or updates the schedule of) the appointment that represents this
// application's adoption interview, so the existing Appointments booking/management
// UI is the one surface used to actually schedule/approve/reschedule it — instead of
// a second bespoke interview-scheduling feature.
function syncInterviewAppointment(mysqli $conn, int $application_id, array $appData, ?string $interview_datetime): void {
    $existing = $conn->prepare("SELECT id FROM appointments WHERE application_id = ? AND appointment_type = 'interview' LIMIT 1");
    $existing->bind_param("i", $application_id);
    $existing->execute();
    $existingRow = $existing->get_result()->fetch_assoc();
    $existing->close();

    $form = json_decode($appData['form_data'] ?? '', true) ?: [];
    $method = $form['interaction_method'] ?? '';
    $details = "Adoption interview for {$appData['pet_name']}.";
    if ($method === 'Zoom') {
        $details .= ' Via Zoom' . (!empty($form['zoom_details']) ? ': ' . $form['zoom_details'] : '.');
    } elseif ($method === 'Phone') {
        $details .= ' Via phone call to the applicant\'s registered number.';
    }

    if ($existingRow) {
    if ($interview_datetime) {
        $stmt = $conn->prepare("UPDATE appointments SET scheduled_at = ?, details = ? WHERE id = ?");
        $stmt->bind_param("ssi", $interview_datetime, $details, $existingRow['id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("UPDATE appointments SET details = ? WHERE id = ?");
        $stmt->bind_param("si", $details, $existingRow['id']);
        $stmt->execute();
        $stmt->close();
    }
    return;
}

    $title = "Adoption Interview – {$appData['pet_name']}";
    $stmt = $conn->prepare("
        INSERT INTO appointments (user_id, pet_id, application_id, appointment_type, title, details, scheduled_at, status)
        VALUES (?, ?, ?, 'interview', ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("iiisss", $appData['user_id'], $appData['pet_id'], $application_id, $title, $details, $interview_datetime);
    $stmt->execute();
    $stmt->close();
}

// Moves an application to a new pipeline status, generating/emailing the QR on
// approval and keeping the linked pet's status in sync. Returns the same
// success/message/qr_code shape regardless of caller (HTTP endpoint or admin action).
function applyApplicationStatusChange(mysqli $conn, int $application_id, string $status, ?int $admin_id, string $admin_notes, ?string $interview_datetime, string $base_url): array {
    if (!in_array($status, APPLICATION_STATUS_PIPELINE)) {
        return ["success" => false, "message" => "Invalid status value"];
    }

    // Self-heal older installs that predate the qr_data column
    if (!function_exists('columnExists')) {
    function columnExists(mysqli $conn, string $table, string $column): bool
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $table, $column);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0) > 0;
    }
}

if (!columnExists($conn, 'adoption_applications', 'qr_data')) {
    $conn->query("
        ALTER TABLE `adoption_applications`
        ADD COLUMN `qr_data` VARCHAR(255) DEFAULT NULL AFTER `qr_code`
    ");
}

    try {
        $conn->begin_transaction();

        $getApp = $conn->prepare("
            SELECT aa.pet_id, aa.user_id, aa.applicant_name, aa.form_data, p.name AS pet_name,
                   u.email AS user_email, u.full_name AS user_full_name
            FROM adoption_applications aa
            JOIN users u ON aa.user_id = u.id
            JOIN pets p ON aa.pet_id = p.id
            WHERE aa.id = ?
        ");
        $getApp->bind_param("i", $application_id);
        $getApp->execute();
        $appResult = $getApp->get_result();

        if ($appResult->num_rows === 0) {
            $conn->rollback();
            return ["success" => false, "message" => "Application not found"];
        }

        $appData = $appResult->fetch_assoc();
        $qr_code = null;
        $qr_data = null;

        if ($status === 'approved') {
            // qr_data is the string encoded INTO the QR image (what a scanner reads back);
            // qr_code is the image file path (used for display/email). They must be stored
            // separately — verify_qr.php looks a scan up by qr_data, not the file path.
            $qr_data = "ANIPET|APP:{$application_id}|PET:{$appData['pet_id']}|DATE:" . date('Y-m-d');
            $qr_code = generateApplicationQRCode($qr_data, $application_id);
        }

        $stmt = $conn->prepare("
            UPDATE adoption_applications
            SET status = ?, admin_notes = ?, screened_by = ?,
                qr_code = IF(? IS NOT NULL, ?, qr_code),
                qr_data = IF(? IS NOT NULL, ?, qr_data),
                interview_datetime = COALESCE(?, interview_datetime)
            WHERE id = ?
        ");
        $stmt->bind_param("ssisssssi", $status, $admin_notes, $admin_id, $qr_code, $qr_code, $qr_data, $qr_data, $interview_datetime, $application_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to update application");
        }

        if ($status === 'approved' && $qr_code && !empty($appData['user_email'])) {
            sendApplicationApprovalQrEmail(
                $appData['user_email'],
                $appData['user_full_name'] ?? '',
                __DIR__ . '/' . $qr_code,
                $base_url . $qr_code
            );
        }

        if ($status === 'approved') {
            $petStmt = $conn->prepare("UPDATE pets SET status = 'in_adoption' WHERE id = ?");
            $petStmt->bind_param("i", $appData['pet_id']);
            $petStmt->execute();
            $petStmt->close();
        } elseif ($status === 'completed') {
            $petStmt = $conn->prepare("UPDATE pets SET status = 'adopted' WHERE id = ?");
            $petStmt->bind_param("i", $appData['pet_id']);
            $petStmt->execute();
            $petStmt->close();
        } elseif ($status === 'rejected') {
            $petStmt = $conn->prepare("UPDATE pets SET status = 'available' WHERE id = ?");
            $petStmt->bind_param("i", $appData['pet_id']);
            $petStmt->execute();
            $petStmt->close();
        } elseif ($status === 'screening') {
            syncInterviewAppointment($conn, $application_id, $appData, $interview_datetime);
        } elseif ($status === 'pending') {
            // Reaching 'pending' only happens via an explicit reopen — undo the prior
            // outcome: release the pet back to the pool and drop the now-stale QR.
            $petStmt = $conn->prepare("UPDATE pets SET status = 'available' WHERE id = ?");
            $petStmt->bind_param("i", $appData['pet_id']);
            $petStmt->execute();
            $petStmt->close();

            $clearStmt = $conn->prepare("UPDATE adoption_applications SET qr_code = NULL, qr_data = NULL WHERE id = ?");
            $clearStmt->bind_param("i", $application_id);
            $clearStmt->execute();
            $clearStmt->close();
        }

        $conn->commit();

        return [
            "success" => true,
            "message" => "Application status updated to " . $status,
            "status" => $status,
            "qr_code" => $qr_code
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return ["success" => false, "message" => "Error: " . $e->getMessage()];
    }
}
