<?php
// Single source of truth for the adoption-application status pipeline so the
// Android tracking screen, the QR feature, and every admin entry point
// (approve link, admin_api.php status modal, update_application_status.php)
// stay in sync.
require_once __DIR__ . '/smtp_config.php';
require_once __DIR__ . '/gmail_api_helper.php';
require_once __DIR__ . '/admin_pages/send_email.php';
require_once __DIR__ . '/admin_pages/email_templates.php';
require_once __DIR__ . '/firebase_helper.php';

const APPLICATION_STATUS_PIPELINE = ['pending', 'screening', 'approved', 'for_releasing', 'ready_pickup', 'completed', 'rejected'];


function getApplicationStatusEmailContent(
    string $status,
    string $petName,
    ?string $interviewDatetime = null
): array {
    $safePetName = htmlspecialchars($petName, ENT_QUOTES, 'UTF-8');

    switch ($status) {
        case 'pending':
            return [
                'subject' => 'AniPet Application Update: Pending',
                'heading' => 'Your application is pending',
                'message' => "
                    Your adoption application for <strong>{$safePetName}</strong>
                    is currently marked as <strong>Pending</strong>.
                    It is waiting for review by the AniPet team.
                "
            ];

        case 'screening':
            $scheduleMessage = '';
            if ($interviewDatetime) {
                $formattedDate = date(
                    'F j, Y \a\t g:i A',
                    strtotime($interviewDatetime)
                );
                $safeDate = htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8');
                $scheduleMessage = "
                    <p>
                        <strong>Interview schedule:</strong> {$safeDate}
                    </p>
                ";
            }

            return [
                'subject' => 'AniPet Application Update: Screening',
                'heading' => 'Your application is now under screening',
                'message' => "
                    Your adoption application for <strong>{$safePetName}</strong>
                    has moved to the <strong>Screening</strong> stage.
                    Our team is reviewing your submitted information.
                    {$scheduleMessage}
                "
            ];

        case 'for_releasing':
            return [
                'subject' => 'AniPet Application Update: For Releasing',
                'heading' => 'Your adopted pet is being prepared for release',
                'message' => "
                    Your application for <strong>{$safePetName}</strong>
                    has moved to <strong>For Releasing</strong>.
                    The AniPet team is preparing the remaining release requirements.
                "
            ];

        case 'ready_pickup':
            return [
                'subject' => 'AniPet Application Update: Ready for Pickup',
                'heading' => 'Your adopted pet is ready for pickup',
                'message' => "
                    Good news! <strong>{$safePetName}</strong> is now marked as
                    <strong>Ready for Pickup</strong>.
                </p>

                <p>
                    You may pick up your pet at:
                </p>

                <p>
                    <strong>Caloocan City Animal Pound</strong><br>
                    Q356+R7W, Barangay 178, Caloocan, Metro Manila
                </p>

                <p>
                    <a
                     href='https://www.google.com/maps/search/?api=1&amp;query=Caloocan+City+Animal+Pound%2C+Q356%2BR7W%2C+Barangay+178%2C+Caloocan%2C+Metro+Manila'
                        target='_blank'
                        rel='noopener noreferrer'
                        style='display:inline-block;padding:10px 16px;background:#7a3e2b;color:#ffffff;text-decoration:none;border-radius:6px;'
                    >
                        Open location in Google Maps
                    </a>
                "
            ];

        case 'completed':
            return [
                'subject' => 'AniPet Adoption Completed',
                'heading' => 'Your adoption has been completed',
                'message' => "
                    Your adoption process for <strong>{$safePetName}</strong>
                    is now marked as <strong>Completed</strong>.
                    Thank you for giving a pet a loving home.
                "
            ];

        case 'rejected':
            return [
                'subject' => 'AniPet Application Update',
                'heading' => 'Update regarding your adoption application',
                'message' => "
                    Your adoption application for <strong>{$safePetName}</strong>
                    has been marked as <strong>Rejected</strong>.
                    Please check your AniPet tracking page or contact the AniPet
                    team if additional details were provided.
                "
            ];

        default:
            return [
                'subject' => 'AniPet Application Status Update',
                'heading' => 'Your application status has changed',
                'message' => "
                    The status of your adoption application for
                    <strong>{$safePetName}</strong> has been updated.
                "
            ];
    }
}

function sendApplicationStatusEmail(
    string $recipientEmail,
    string $applicantName,
    string $petName,
    int $applicationId,
    string $status,
    ?string $interviewDatetime = null,
    string $adminNotes = '',
    ?string $completedPhoto = null,
    string $baseUrl = ''
): bool {
    $safeName = htmlspecialchars(
        trim($applicantName) !== '' ? $applicantName : 'Applicant',
        ENT_QUOTES,
        'UTF-8'
    );
    $safePetName = htmlspecialchars(
        $petName,
        ENT_QUOTES,
        'UTF-8'
    );
    $safeNotes = htmlspecialchars(trim($adminNotes), ENT_QUOTES, 'UTF-8');

    $content = getApplicationStatusEmailContent(
        $status,
        $petName,
        $interviewDatetime
    );

    $notesBlock = $safeNotes !== ''
        ? "
            <div style=\"margin-top:20px;padding:14px;background:#f7f3f1;border-radius:8px;\">
                <strong>Message from AniPet:</strong><br>
                " . nl2br($safeNotes) . "
            </div>
        "
        : '';

    $completedPhotoBlock = '';

    if ($status === 'completed' && !empty($completedPhoto) && !empty($baseUrl)) {
        $photoUrl = rtrim($baseUrl, '/') . '/' . ltrim($completedPhoto, '/');
        $safePhotoUrl = htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8');
        $completedPhotoBlock = "
            <div style=\"margin-top:24px;padding:16px;background:#f7f3f1;border-radius:10px;text-align:center;\">
                <h3 style=\"color:#7a3e2b;\">Completed Adoption Photo</h3>
                <p>Here is a photo from the successful release of <strong>{$safePetName}</strong>.</p>
                <img src=\"{$safePhotoUrl}\" style=\"width:100%;max-width:520px;border-radius:10px;border:1px solid #ddd;\">
            </div>";
    }

    $htmlBody = "
        <div style=\"font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#333;line-height:1.6;\">
            <h2 style=\"color:#7a3e2b;\">{$content['heading']}</h2>

            <p>Hello {$safeName},</p>

            <p>{$content['message']}</p>

            {$notesBlock}
            {$completedPhotoBlock}
            <p>
                Please continue checking your AniPet application tracker and
                your email for further updates.
            </p>

            <p style=\"margin-top:30px;\">
                Regards,<br>
                <strong>AniPet Team</strong>
            </p>
        </div>
    ";

    try {
        sendGmailMessage(
            $recipientEmail,
            $content['subject'],
            $htmlBody
        );
        return true;
    } catch (Throwable $e) {
        error_log(
            'Application status email failed for application ' .
            $applicationId .
            ' (' .
            $status .
            '): ' .
            $e->getMessage()
        );
        return false;
    }
}

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

function getApplicationPushContent(
    string $status,
    string $petName
): array {
    switch ($status) {
        case 'pending':
            return [
                'title' => 'Application Reopened',
                'message' => "Your application for {$petName} has been moved back to Pending."
            ];

        case 'screening':
            return [
                'title' => 'Application Under Screening',
                'message' => "Your application for {$petName} is now under screening."
            ];

        case 'approved':
            return [
                'title' => 'Application Approved',
                'message' => "Congratulations! Your application for {$petName} has been approved."
            ];

        case 'for_releasing':
            return [
                'title' => 'For Releasing',
                'message' => "{$petName} is now being prepared for release."
            ];

        case 'ready_pickup':
            return [
                'title' => 'Ready for Pickup',
                'message' => "{$petName} is ready for pickup."
            ];

        case 'completed':
            return [
                'title' => 'Adoption Completed',
                'message' => "Your adoption of {$petName} has been completed."
            ];

        case 'rejected':
            return [
                'title' => 'Application Rejected',
                'message' => "Your application for {$petName} has been rejected."
            ];

        default:
            return [
                'title' => 'AniPet Application Update',
                'message' => "Your application for {$petName} has been updated."
            ];
    }
}

function createApplicationNotification(
    mysqli $conn,
    int $userId,
    int $applicationId,
    string $status,
    string $petName
): void {

    switch ($status) {

        case 'pending':
            $title = "Application Reopened";
            $message = "Your application for {$petName} has been moved back to Pending.";
            break;

        case 'screening':
            $title = "Application Under Screening";
            $message = "Your application for {$petName} is now under screening.";
            break;

        case 'approved':
            $title = "Application Approved";
            $message = "Congratulations! Your application for {$petName} has been approved.";
            break;

        case 'for_releasing':
            $title = "For Releasing";
            $message = "Your adopted pet {$petName} is now being prepared for release.";
            break;

        case 'ready_pickup':
            $title = "Ready for Pickup";
            $message = "Your adopted pet {$petName} is ready for pickup.";
            break;

        case 'completed':
            $title = "Adoption Completed";
            $message = "Congratulations! Your adoption of {$petName} has been completed.";
            break;

        case 'rejected':
            $title = "Application Rejected";
            $message = "We're sorry. Your application for {$petName} has been rejected.";
            break;

        default:
            return;
    }

    $stmt = $conn->prepare("
        INSERT INTO user_notifications
        (
            user_id,
            application_id,
            title,
            message,
            type,
            is_read,
            created_at
        )
        VALUES
        (
            ?, ?, ?, ?, 'application', 0, NOW()
        )
    ");

    $stmt->bind_param(
        "iiss",
        $userId,
        $applicationId,
        $title,
        $message
    );

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
            SELECT
            aa.pet_id,
            aa.user_id,
            aa.applicant_name,
            aa.form_data,
            aa.status AS current_status,
            aa.qr_code AS existing_qr_code,
            aa.qr_data AS existing_qr_data,
            aa.completed_photo,
            p.name AS pet_name,
            u.email AS user_email,
            u.full_name AS user_full_name
            u.fcm_token AS user_fcm_token
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
        $previousStatus = $appData['current_status'] ?? '';
$isNewApproval = (
    $status === 'approved'
    && $previousStatus !== 'approved'
);

$statusChanged = ($status !== $previousStatus);

$qr_code = null;
$qr_data = null;

if ($status === 'approved') {
    if (
        !empty($appData['existing_qr_code'])
        && !empty($appData['existing_qr_data'])
    ) {
        $qr_code = $appData['existing_qr_code'];
        $qr_data = $appData['existing_qr_data'];
    } else {
        $qr_data =
            "ANIPET"
            . "|APP:" . $application_id
            . "|PET:" . $appData['pet_id']
            . "|DATE:" . date('Y-m-d');

        $qr_code = generateApplicationQRCode(
            $qr_data,
            $application_id
        );

        if ($qr_code === null) {
            throw new Exception(
                'The application could not be approved because QR generation failed.'
            );
        }
    }
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

$pushSent = null;

if ($statusChanged) {
    createApplicationNotification(
        $conn,
        (int)$appData['user_id'],
        $application_id,
        $status,
        $appData['pet_name']
    );

    if (!empty($appData['user_fcm_token'])) {
        $pushContent = getApplicationPushContent(
            $status,
            $appData['pet_name']
        );

        $pushSent = sendFirebaseNotification(
            $appData['user_fcm_token'],
            $pushContent['title'],
            $pushContent['message'],
            [
                'type' => 'application',
                'application_id' => (string)$application_id,
                'status' => $status
            ]
        );
    } else {
        error_log(
            "No FCM token found for user " . $appData['user_id']
        );

        $pushSent = false;
    }
}

$emailSent = null;

        if (
            $statusChanged
            && !empty($appData['user_email'])
        ) {
            if (
                $status === 'approved'
                && $qr_code
            ) {
                // The existing approval email already includes the QR code,
                // so do not send a second generic status email.
                $emailSent = sendApplicationApproved(
                    $appData['user_email'],
                    $appData['user_full_name'],
                    $base_url . $qr_code,
                    $appData['pet_name']
                );
            } else {
                $emailSent = sendApplicationStatusEmail(
                    $appData['user_email'],
                    $appData['user_full_name'] ?: $appData['applicant_name'],
                    $appData['pet_name'],
                    $application_id,
                    $status,
                    $interview_datetime,
                    $admin_notes,
                    $appData['completed_photo'] ?? null,
                    $base_url
                );
            }
        }

        return [
            "success" => true,
            "message" => !$statusChanged
                ? "Application remains " . $status . ". No duplicate email was sent."
                : (
                    $emailSent === true
                        ? "Application status updated to " . $status . " and the applicant was emailed."
                        : "Application status updated to " . $status . ", but the email could not be sent."
                ),
            "status" => $status,
            "qr_code" => $qr_code,
            "email_sent" => $emailSent,
            "push_sent" => $pushSent
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return ["success" => false, "message" => "Error: " . $e->getMessage()];
    }
}
