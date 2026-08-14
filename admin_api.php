<?php
header('Content-Type: application/json');
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/return_policy_helper.php';

$action = $_REQUEST['action'] ?? '';
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

function respondJSON(bool $success, string $message = '', array $data = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if (!in_array(current_user_role(), ['admin', 'super_admin'])) {
    respondJSON(false, 'Unauthorized');
}

// Note: pets/notifications auto-migrations now live in db_connect.php so every
// entry point self-heals consistently (not just requests that hit this file first).

try {
    switch ($action) {

        // ================================================================
        // PET MANAGEMENT
        // ================================================================

        case 'add_pet':
    require_permission($conn, 'manage_pets');
    $name    = trim($_POST['name']    ?? '');
    $species = trim($_POST['species'] ?? '');
    $breed   = trim($_POST['breed']   ?? '');
    $age     = trim($_POST['age']     ?? '');
    $gender  = trim($_POST['gender']  ?? '');
    $status  = trim($_POST['status']  ?? 'available');
    $health_status         = trim($_POST['health_status']         ?? '');
    $description           = trim($_POST['description']           ?? '');
    $vaccination_records   = trim($_POST['vaccination_records']   ?? '');
    $medical_records       = trim($_POST['medical_records']       ?? '');

    if (!$name || !$breed) respondJSON(false, 'Name and breed are required');

    $validStatuses = ['available', 'reserved', 'adopted', 'under_treatment'];
    if (!in_array($status, $validStatuses)) $status = 'available';

    // ---- Handle multiple image uploads (stored comma-separated in `image`) ----
    $uploadedFilenames = [];
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . '/images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileCount = count($_FILES['images']['name']);
        if ($fileCount > 10) respondJSON(false, 'Maximum 10 photos per pet');

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) respondJSON(false, 'Invalid image type (jpg/png/gif/webp only)');
            if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) respondJSON(false, 'Each image must be under 5 MB');

            $filename = 'pet_' . uniqid() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $filename)) {
                $uploadedFilenames[] = $filename;
            }
        }
    }
    $imageString = $uploadedFilenames ? implode(',', $uploadedFilenames) : null;

    $stmt = $conn->prepare(
        "INSERT INTO pets (name, species, breed, age, gender, status, health_status, description,
         vaccination_records, medical_records, image, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('sssssssssss',
        $name, $species, $breed, $age, $gender, $status, $health_status,
        $description, $vaccination_records, $medical_records, $imageString
    );
    if ($stmt->execute()) {
        respondJSON(true, 'Pet added successfully', ['pet_id' => $stmt->insert_id]);
    }
    respondJSON(false, $conn->error);
    break;

        case 'get_pet':
            require_permission($conn, 'manage_pets');
            $id = intval($_GET['id'] ?? 0);
            if (!$id) respondJSON(false, 'Missing pet ID');

            $stmt = $conn->prepare("SELECT * FROM pets WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $pet = $stmt->get_result()->fetch_assoc();
            if (!$pet) respondJSON(false, 'Pet not found');
            respondJSON(true, '', ['pet' => $pet]);
            break;

        case 'update_pet':
    require_permission($conn, 'manage_pets');
    $id = intval($_POST['id'] ?? 0);
    if (!$id) respondJSON(false, 'Missing pet ID');

    $name    = trim($_POST['name']    ?? '');
    $species = trim($_POST['species'] ?? '');
    $breed   = trim($_POST['breed']   ?? '');
    $age     = trim($_POST['age']     ?? '');
    $gender  = trim($_POST['gender']  ?? '');
    $status  = trim($_POST['status']  ?? 'available');
    $health_status       = trim($_POST['health_status']       ?? '');
    $description         = trim($_POST['description']         ?? '');
    $vaccination_records = trim($_POST['vaccination_records'] ?? '');
    $medical_records     = trim($_POST['medical_records']     ?? '');

    if (!$name || !$breed) respondJSON(false, 'Name and breed are required');

    $validStatuses = ['available', 'reserved', 'in_adoption', 'adopted', 'under_treatment'];
    if (!in_array($status, $validStatuses)) $status = 'available';

    // Load current images
    $cur = $conn->prepare("SELECT image FROM pets WHERE id = ?");
    $cur->bind_param('i', $id);
    $cur->execute();
    $row = $cur->get_result()->fetch_assoc();
    $existing = $row && $row['image'] ? explode(',', $row['image']) : [];

    // Remove any the user deleted in the UI
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
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . '/images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileCount = count($_FILES['images']['name']);
        if (count($existing) + $fileCount > 10) respondJSON(false, 'Maximum 10 photos per pet');

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) respondJSON(false, 'Invalid image type (jpg/png/gif/webp only)');
            if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) respondJSON(false, 'Each image must be under 5 MB');

            $filename = 'pet_' . uniqid() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $filename)) {
                $existing[] = $filename;
            }
        }
    }

    $imageString = $existing ? implode(',', $existing) : null;

    $stmt = $conn->prepare(
        "UPDATE pets SET name=?, species=?, breed=?, age=?, gender=?, status=?,
         health_status=?, description=?, vaccination_records=?, medical_records=?, image=? WHERE id=?"
    );
    $stmt->bind_param('sssssssssssi',
        $name, $species, $breed, $age, $gender, $status,
        $health_status, $description, $vaccination_records, $medical_records, $imageString, $id
    );
    if ($stmt->execute()) {
        respondJSON(true, 'Pet updated successfully');
    }
    respondJSON(false, $conn->error);
    break;

        case 'delete_pet':
            require_permission($conn, 'manage_pets');
            $id = intval($_POST['id'] ?? 0);
            if (!$id) respondJSON(false, 'Missing pet ID');

            $stmt = $conn->prepare("DELETE FROM pets WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                respondJSON(true, 'Pet deleted');
            }
            respondJSON(false, $conn->error);
            break;

        case 'archive_pet':
        case 'unarchive_pet':
            require_permission($conn, 'manage_pets');
            $id = intval($_POST['id'] ?? 0);
            if (!$id) respondJSON(false, 'Missing pet ID');

            $archivedFlag = $action === 'archive_pet' ? 1 : 0;
            $stmt = $conn->prepare("UPDATE pets SET is_archived = ? WHERE id = ?");
            $stmt->bind_param('ii', $archivedFlag, $id);
            if ($stmt->execute()) {
                respondJSON(true, $action === 'archive_pet' ? 'Pet archived' : 'Pet unarchived');
            }
            respondJSON(false, $conn->error);
            break;

        // ================================================================
        // ADOPTION APPLICATIONS
        // ================================================================

        case 'get_application':
            require_permission($conn, 'manage_applications');
            $id = intval($_GET['id'] ?? 0);
            if (!$id) respondJSON(false, 'Missing application ID');

            $stmt = $conn->prepare(
                "SELECT aa.*, p.name AS pet_name, p.breed AS pet_breed, p.image AS pet_image,
                        u.full_name, u.email, u.phone, u.address
                 FROM adoption_applications aa
                 LEFT JOIN pets p  ON aa.pet_id  = p.id
                 LEFT JOIN users u ON aa.user_id  = u.id
                 WHERE aa.id = ? LIMIT 1"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $app = $stmt->get_result()->fetch_assoc();
            if (!$app) respondJSON(false, 'Application not found');
            respondJSON(true, '', ['application' => $app]);
            break;

        case 'update_application_status':
            require_permission($conn, 'manage_applications');
            require_once __DIR__ . '/application_status_helper.php';
            $id           = intval($_POST['id']               ?? 0);
            $status       = trim($_POST['status']             ?? '');
            $admin_notes  = trim($_POST['admin_notes']        ?? '');
            $interview_dt = trim($_POST['interview_datetime'] ?? '') ?: null;

            if (!$id || !$status) respondJSON(false, 'Missing ID or status');

            // Shared with update_application_status.php / approve_application.php so QR
            // generation, approval emails, and pet-status sync stay in one place and in
            // sync with the pending -> screening -> approved -> for_releasing -> ready_pickup -> completed pipeline.
            $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
            $result = applyApplicationStatusChange($conn, $id, $status, current_user_id(), $admin_notes, $interview_dt, $base_url);

            /*
             * Once the adoption application moves past the interview stage,
             * remove the linked interview appointment so it no longer appears
             * in either the Admin Appointment Management page or the user's
             * Android Appointments screen.
             *
             * Approved   = interview is finished and application passed
             * Completed  = adoption workflow is finished
             * Rejected   = application is closed
             */
            if (
                !empty($result['success']) &&
                in_array($status, ['approved', 'completed', 'rejected'], true)
            ) {
                $deleteAppointment = $conn->prepare("
                    DELETE FROM appointments
                    WHERE application_id = ?
                      AND appointment_type = 'interview'
                ");

                if ($deleteAppointment) {
                    $deleteAppointment->bind_param('i', $id);
                    $deleteAppointment->execute();
                    $deleteAppointment->close();
                }
            }

            respondJSON(
                $result['success'],
                $result['message'],
                ['qr_code' => $result['qr_code'] ?? null]
            );
            break;

        // ================================================================
        // APPOINTMENTS
        // ================================================================

        case 'get_appointment':
            require_permission($conn, 'manage_appointments');
            $id = intval($_GET['id'] ?? 0);
            if (!$id) respondJSON(false, 'Missing appointment ID');

            $stmt = $conn->prepare(
                "SELECT a.*, p.name AS pet_name, p.breed AS pet_breed,
                        u.full_name, u.email, u.phone
                 FROM appointments a
                 LEFT JOIN pets  p ON a.pet_id  = p.id
                 LEFT JOIN users u ON a.user_id = u.id
                 WHERE a.id = ? LIMIT 1"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $apt = $stmt->get_result()->fetch_assoc();
            if (!$apt) respondJSON(false, 'Appointment not found');
            respondJSON(true, '', ['appointment' => $apt]);
            break;

case 'update_appointment_status':
    require_permission($conn, 'manage_appointments');

    $id     = intval($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if (!$id || !$status) {
        respondJSON(false, 'Missing ID or status');
    }

    if (!in_array($status, ['pending', 'approved', 'rejected'])) {
        respondJSON(false, 'Invalid status');
    }

    $stmt = $conn->prepare("UPDATE appointments SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {

        // Only notify when appointment is approved
        if ($status === 'approved') {

            $get = $conn->prepare("
                SELECT
                    a.scheduled_at,
                    a.application_id,
                    u.id AS user_id,
                    u.full_name,
                    u.email,
                    p.name AS pet_name
                FROM appointments a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN pets p ON a.pet_id = p.id
                WHERE a.id = ?
                LIMIT 1
            ");

            $get->bind_param("i", $id);
            $get->execute();
            $appointment = $get->get_result()->fetch_assoc();
            $get->close();

            if ($appointment) {

                $schedule = date(
                    "F d, Y \\a\\t h:i A",
                    strtotime($appointment['scheduled_at'])
                );

                $subject = "Interview Schedule Confirmed";

                // Plain text notification (saved to database)
                $notificationMessage =
                    "Good day {$appointment['full_name']},\n\n" .
                    "Your adoption interview for {$appointment['pet_name']} has been confirmed.\n\n" .
                    "Interview Schedule:\n" .
                    $schedule .
                    "\n\nPlease arrive at least 15 minutes before your scheduled interview.\n\n" .
                    "Thank you,\nAniPet Adoption Team";

                // Save notification
                $notif = $conn->prepare("
                    INSERT INTO user_notifications
                    (user_id, title, message, type, is_read, created_at)
                    VALUES (?, ?, ?, 'appointment', 0, NOW())
                ");

                $notif->bind_param(
                    "iss",
                    $appointment['user_id'],
                    $subject,
                    $notificationMessage
                );

                $notif->execute();
                $notif->close();

                // HTML Email
                $emailBody = "
                    <h2>Interview Schedule Confirmed</h2>

                    <p>Good day <strong>{$appointment['full_name']}</strong>,</p>

                    <p>Your adoption interview for <strong>{$appointment['pet_name']}</strong> has been confirmed.</p>

                    <p>
                        <strong>Interview Schedule:</strong><br>
                        {$schedule}
                    </p>

                    <p>Please arrive at least <strong>15 minutes before</strong> your scheduled interview.</p>

                    <p>
                        Thank you,<br>
                        <strong>AniPet Adoption Team</strong>
                    </p>
                ";

                require_once __DIR__ . '/admin_pages/send_email.php';

                if (function_exists('sendEmail')) {
                    sendEmail(
                        $appointment['email'],
                        $subject,
                        $emailBody,
                        true
                    );
                }
            }
        }

        respondJSON(true, 'Appointment updated');
    }

    respondJSON(false, $conn->error);
    break;

        case 'reschedule_appointment':
            require_permission($conn, 'manage_appointments');
            $id           = intval($_POST['id']           ?? 0);
            $scheduled_at = trim($_POST['scheduled_at']   ?? '');
            if (!$id || !$scheduled_at) respondJSON(false, 'Missing ID or date');

            $stmt = $conn->prepare("UPDATE appointments SET scheduled_at=?, status='pending' WHERE id=?");
            $stmt->bind_param('si', $scheduled_at, $id);
            if ($stmt->execute()) {
                // If this appointment is a linked adoption interview, mirror the new
                // date onto the application so the Application Tracking screen (Android)
                // shows the same date without a second round trip.
                $link = $conn->prepare("SELECT application_id FROM appointments WHERE id = ? AND appointment_type = 'interview' AND application_id IS NOT NULL");
                $link->bind_param('i', $id);
                $link->execute();
                $linkRow = $link->get_result()->fetch_assoc();
                $link->close();
                if ($linkRow) {
                    $syncStmt = $conn->prepare("UPDATE adoption_applications SET interview_datetime = ? WHERE id = ?");
                    $syncStmt->bind_param('si', $scheduled_at, $linkRow['application_id']);
                    $syncStmt->execute();
                    $syncStmt->close();
                }
                respondJSON(true, 'Appointment rescheduled');
            }
            respondJSON(false, $conn->error);
            break;

        // ================================================================
        // USER MANAGEMENT
        // ================================================================

        case 'update_user_status':
            require_permission($conn, 'manage_users');
            $id           = intval($_POST['id']           ?? 0);
            $is_suspended = intval($_POST['is_suspended'] ?? 0);
            if (!$id) respondJSON(false, 'Missing user ID');

            $stmt = $conn->prepare("UPDATE users SET is_suspended=? WHERE id=? AND role='user'");
            $stmt->bind_param('ii', $is_suspended, $id);
            if ($stmt->execute()) {
                respondJSON(true, 'User status updated');
            }
            respondJSON(false, $conn->error);
            break;

        case 'get_user_history':
            require_permission($conn, 'manage_users');
            $id = intval($_GET['id'] ?? 0);
            if (!$id) respondJSON(false, 'Missing user ID');

            $stmt = $conn->prepare(
                "SELECT aa.id, aa.status, aa.created_at, aa.interview_datetime, aa.admin_notes,
                        p.name AS pet_name, p.breed AS pet_breed, p.image AS pet_image
                 FROM adoption_applications aa
                 LEFT JOIN pets p ON aa.pet_id = p.id
                 WHERE aa.user_id = ?
                 ORDER BY aa.created_at DESC"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result  = $stmt->get_result();
            $history = [];
            while ($row = $result->fetch_assoc()) $history[] = $row;
            respondJSON(true, '', ['history' => $history]);
            break;

        // ================================================================
        // NOTIFICATIONS
        // ================================================================

        case 'send_notification':

    require_permission($conn, 'manage_notifications');

    require_once __DIR__ . '/admin_pages/send_email.php';

    $recipient_group =
        trim($_POST['recipient_group'] ?? '');

    $notification_type =
        trim($_POST['notification_type'] ?? 'announcement');

    $subject =
        trim($_POST['subject'] ?? '');

    $message =
        trim($_POST['message'] ?? '');

    if (
        !$recipient_group ||
        !$subject ||
        !$message
    ) {
        respondJSON(
            false,
            'Missing required fields'
        );
    }

    $stmt = $conn->prepare("
        INSERT INTO notifications
        (
            recipient_group,
            notification_type,
            subject,
            message,
            created_at
        )
        VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        'ssss',
        $recipient_group,
        $notification_type,
        $subject,
        $message
    );

    if (!$stmt->execute()) {
        respondJSON(
            false,
            $conn->error
        );
    }

    $stmt->close();

    $sent = 0;
    $failed = 0;
    $errors = [];

    $sendToUser = function (
        array $user
    ) use (
        $subject,
        $message,
        &$sent,
        &$failed,
        &$errors
    ): void {
        $personalizedMessage = nl2br(
            str_replace(
                ['[Name]', '[name]'],
                [
                    $user['full_name'],
                    $user['full_name']
                ],
                $message
            )
        );

        $result = sendEmail(
            $user['email'],
            $subject,
            $personalizedMessage,
            true
        );

        if (
            isset($result['success']) &&
            $result['success'] === true
        ) {
            $sent++;
        } else {
            $failed++;

            $errors[] =
                $user['email'] .
                ': ' .
                ($result['message'] ?? 'Unknown error');

            error_log(
                'Notification email failed for ' .
                $user['email'] .
                ': ' .
                ($result['message'] ?? 'Unknown error')
            );
        }
    };

    if ($recipient_group === 'applicant') {
        $applicationId = intval(
            $_POST['applicant_id'] ?? 0
        );

        if ($applicationId <= 0) {
            respondJSON(
                false,
                'Invalid applicant ID'
            );
        }

        $getUser = $conn->prepare("
            SELECT
                u.email,
                u.full_name
            FROM adoption_applications aa
            INNER JOIN users u
                ON aa.user_id = u.id
            WHERE aa.id = ?
            LIMIT 1
        ");

        $getUser->bind_param(
            'i',
            $applicationId
        );

        $getUser->execute();

        $user = $getUser
            ->get_result()
            ->fetch_assoc();

        $getUser->close();

        if (!$user) {
            respondJSON(
                false,
                'Applicant email was not found'
            );
        }

        $sendToUser($user);
    } elseif ($recipient_group === 'all') {
        $users = $conn->query("
            SELECT
                email,
                full_name
            FROM users
            WHERE role = 'user'
            AND is_verified = 1
            AND email IS NOT NULL
            AND email != ''
        ");

        if (!$users) {
            respondJSON(
                false,
                $conn->error
            );
        }

        while (
            $user = $users->fetch_assoc()
        ) {
            $sendToUser($user);
        }
    } elseif ($recipient_group === 'applicants') {
        $users = $conn->query("
            SELECT DISTINCT
                u.email,
                u.full_name
            FROM adoption_applications aa
            INNER JOIN users u
                ON aa.user_id = u.id
            WHERE u.email IS NOT NULL
            AND u.email != ''
        ");

        if (!$users) {
            respondJSON(
                false,
                $conn->error
            );
        }

        while (
            $user = $users->fetch_assoc()
        ) {
            $sendToUser($user);
        }
    } else {
        respondJSON(
            false,
            'Unsupported recipient group'
        );
    }

    if ($sent === 0) {
        respondJSON(
            false,
            $errors[0]
                ?? 'No notification emails were sent'
        );
    }

    respondJSON(
        true,
        "Sent to {$sent} recipient(s). " .
        "Failed: {$failed}."
    );

    break;

        case 'get_notifications':
            require_permission($conn, 'manage_notifications');
            $result = $conn->query(
                "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 30"
            );
            $notifications = [];
            if ($result) while ($row = $result->fetch_assoc()) $notifications[] = $row;
            respondJSON(true, '', ['notifications' => $notifications]);
            break;

        // ================================================================
        // RETURN REQUESTS & PENALTIES
        // ================================================================

        case 'get_return_requests':
            require_permission($conn, 'manage_returns');
            $validStatuses = ['pending', 'approved', 'rejected', 'completed'];
            $filter = trim($_GET['status'] ?? '');
            if (!in_array($filter, $validStatuses)) $filter = '';

            $sql = "SELECT rr.*, p.name AS pet_name, p.breed AS pet_breed, p.image AS pet_image,
                           u.full_name, u.email, u.phone
                    FROM return_requests rr
                    LEFT JOIN pets  p ON rr.pet_id  = p.id
                    LEFT JOIN users u ON rr.user_id = u.id
                    WHERE 1=1";
            if ($filter) $sql .= " AND rr.status = '" . $conn->real_escape_string($filter) . "'";
            $sql .= " ORDER BY rr.created_at DESC";

            $returns = [];
            $result  = $conn->query($sql);
            if ($result) while ($row = $result->fetch_assoc()) $returns[] = $row;
            respondJSON(true, '', ['return_requests' => $returns]);
            break;

        case 'get_return_request':
            require_permission($conn, 'manage_returns');
            $id = intval($_GET['id'] ?? 0);
            if (!$id) respondJSON(false, 'Missing return request ID');

            $stmt = $conn->prepare(
                "SELECT rr.*, p.name AS pet_name, p.breed AS pet_breed, p.image AS pet_image,
                        u.full_name, u.email, u.phone, u.address
                 FROM return_requests rr
                 LEFT JOIN pets  p ON rr.pet_id  = p.id
                 LEFT JOIN users u ON rr.user_id = u.id
                 WHERE rr.id = ? LIMIT 1"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $rr = $stmt->get_result()->fetch_assoc();
            if (!$rr) respondJSON(false, 'Return request not found');
            respondJSON(true, '', ['return_request' => $rr]);
            break;

        case 'update_return_request':
            require_permission($conn, 'manage_returns');
            $id             = intval($_POST['id'] ?? 0);
            $status         = trim($_POST['status'] ?? '');
            $penalty_paid   = isset($_POST['penalty_paid']) && $_POST['penalty_paid'] !== '' ? intval($_POST['penalty_paid']) : null;
            $penalty_amount = isset($_POST['penalty_amount']) && $_POST['penalty_amount'] !== '' ? (float)$_POST['penalty_amount'] : null;
            $admin_notes    = trim($_POST['admin_notes'] ?? '');

            if (!$id) respondJSON(false, 'Missing return request ID');
            $validStatuses = ['pending', 'approved', 'rejected', 'completed'];
            if ($status !== '' && !in_array($status, $validStatuses)) respondJSON(false, 'Invalid status');

            $current = $conn->prepare("SELECT pet_id FROM return_requests WHERE id = ? LIMIT 1");
            $current->bind_param('i', $id);
            $current->execute();
            $row = $current->get_result()->fetch_assoc();
            $current->close();
            if (!$row) respondJSON(false, 'Return request not found');

            $setClauses = [];
            $params     = [];
            $types      = '';
            if ($status !== '')        { $setClauses[] = 'status = ?';         $params[] = $status;         $types .= 's'; }
            if ($penalty_paid !== null) { $setClauses[] = 'penalty_paid = ?';   $params[] = $penalty_paid;   $types .= 'i'; }
            if ($penalty_amount !== null) { $setClauses[] = 'penalty_amount = ?'; $params[] = $penalty_amount; $types .= 'd'; }
            $setClauses[] = 'admin_notes = ?';
            $params[]     = $admin_notes;
            $types       .= 's';

            $params[] = $id;
            $types   .= 'i';
            $stmt = $conn->prepare("UPDATE return_requests SET " . implode(', ', $setClauses) . " WHERE id = ?");
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) respondJSON(false, $conn->error);

            // Completing a return means the pet is physically back at the shelter —
            // release it back into the adoptable pool (mirrors the rejected/pending
            // application flow in application_status_helper.php).
            if ($status === 'completed') {
                $petStmt = $conn->prepare("UPDATE pets SET status = 'available' WHERE id = ?");
                $petStmt->bind_param('i', $row['pet_id']);
                $petStmt->execute();
                $petStmt->close();
            }

            respondJSON(true, 'Return request updated');
            break;

        // ================================================================
        // RETURN PENALTY & DONATION SETTINGS
        // ================================================================

        case 'get_return_policy_settings':
            require_permission($conn, 'manage_settings');
            $settings = [];
            foreach (RETURN_POLICY_KEYS as $key) $settings[$key] = get_return_policy_setting($conn, $key, '');
            respondJSON(true, '', ['settings' => $settings]);
            break;

        case 'update_return_policy_settings':
            require_permission($conn, 'manage_settings');

            $donationKeys = ['donation_gcash_name', 'donation_gcash_number', 'donation_notes'];
            $touchesDonationSettings = !empty($_FILES['donation_qr']['name']);
            foreach ($donationKeys as $dk) {
                if (isset($_POST[$dk])) { $touchesDonationSettings = true; break; }
            }
            // Payment/donation info is a financial surface — restrict edits to super_admin
            // even though admins otherwise share the 'manage_settings' permission.
            if ($touchesDonationSettings && current_user_role() !== 'super_admin') {
                respondJSON(false, 'Only Super Admin can edit donation settings');
            }

            if (isset($_POST['return_penalty_type']) && !in_array($_POST['return_penalty_type'], ['fixed', 'percentage'])) {
                respondJSON(false, 'Invalid penalty type');
            }

            foreach (RETURN_POLICY_KEYS as $key) {
                if ($key === 'donation_qr_filename') continue; // handled via file upload below
                if (isset($_POST[$key])) {
                    save_return_policy_setting($conn, $key, trim($_POST[$key]));
                }
            }

            if (!empty($_FILES['donation_qr']['name'])) {
                $uploadDir = __DIR__ . '/images/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext     = strtolower(pathinfo($_FILES['donation_qr']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($ext, $allowed)) respondJSON(false, 'Invalid QR image type (jpg/png/gif/webp only)');
                if ($_FILES['donation_qr']['size'] > 5 * 1024 * 1024) respondJSON(false, 'QR image too large (max 5 MB)');
                $filename = 'donation_qr_' . uniqid() . '.' . $ext;
                if (!move_uploaded_file($_FILES['donation_qr']['tmp_name'], $uploadDir . $filename)) {
                    respondJSON(false, 'QR image upload failed');
                }
                save_return_policy_setting($conn, 'donation_qr_filename', $filename);
            }

            respondJSON(true, 'Settings saved successfully');
            break;

        // ================================================================
        // REPORTS
        // ================================================================

        case 'generate_report':
            require_permission($conn, 'generate_reports');
            $report_type  = trim($_POST['report_type']  ?? '');
            $date_from    = trim($_POST['date_from']    ?? '');
            $date_to      = trim($_POST['date_to']      ?? '');

            if (!$report_type) respondJSON(false, 'Missing report type');

            $rows        = [];
            $summary     = [];
            $dateClause  = '';
            $dateParams  = [];
            $dateTypes   = '';

            if ($date_from && $date_to) {
                $dateClause = "AND aa.created_at BETWEEN ? AND ?";
                $dateParams = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
                $dateTypes  = 'ss';
            } elseif ($date_from) {
                $dateClause = "AND aa.created_at >= ?";
                $dateParams = [$date_from . ' 00:00:00'];
                $dateTypes  = 's';
            } elseif ($date_to) {
                $dateClause = "AND aa.created_at <= ?";
                $dateParams = [$date_to . ' 23:59:59'];
                $dateTypes  = 's';
            }

            if ($report_type === 'adoption') {
                $sql = "SELECT aa.id, aa.applicant_name, aa.status, DATE_FORMAT(aa.created_at,'%Y-%m-%d') as applied_date,
                               DATE_FORMAT(aa.interview_datetime,'%Y-%m-%d %H:%i') as interview_date,
                               aa.admin_notes,
                               p.name AS pet_name, p.breed AS pet_breed
                        FROM adoption_applications aa
                        LEFT JOIN pets p ON aa.pet_id = p.id
                        WHERE 1=1 {$dateClause}
                        ORDER BY aa.created_at DESC LIMIT 200";

                if ($dateParams) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($dateTypes, ...$dateParams);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query($sql);
                }
                if ($result) while ($r = $result->fetch_assoc()) $rows[] = $r;

                $summary = [
                    'total'    => count($rows),
                    'pending'  => count(array_filter($rows, fn($r) => $r['status'] === 'pending')),
                    'approved' => count(array_filter($rows, fn($r) => in_array($r['status'], ['approved', 'for_releasing', 'ready_pickup', 'completed']))),
                    'rejected' => count(array_filter($rows, fn($r) => $r['status'] === 'rejected')),
                    'review'   => count(array_filter($rows, fn($r) => $r['status'] === 'screening')),
                ];

            } elseif ($report_type === 'appointment') {
                $dateClauseApt = str_replace('aa.', 'a.', $dateClause);
                $sql = "SELECT a.id, a.title, a.status,
                               DATE_FORMAT(a.scheduled_at,'%Y-%m-%d %H:%i') as scheduled_at,
                               DATE_FORMAT(a.created_at,'%Y-%m-%d') as created_at,
                               u.full_name AS client_name, u.email AS client_email,
                               p.name AS pet_name
                        FROM appointments a
                        LEFT JOIN users u ON a.user_id = u.id
                        LEFT JOIN pets  p ON a.pet_id  = p.id
                        WHERE 1=1 {$dateClauseApt}
                        ORDER BY a.scheduled_at DESC LIMIT 200";

                if ($dateParams) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($dateTypes, ...$dateParams);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query($sql);
                }
                if ($result) while ($r = $result->fetch_assoc()) $rows[] = $r;

                $summary = [
                    'total'    => count($rows),
                    'pending'  => count(array_filter($rows, fn($r) => $r['status'] === 'pending')),
                    'approved' => count(array_filter($rows, fn($r) => $r['status'] === 'approved')),
                    'rejected' => count(array_filter($rows, fn($r) => $r['status'] === 'rejected')),
                ];

            } elseif ($report_type === 'inventory') {
                $status_filter  = trim($_POST['status_filter'] ?? '');
                $statusClause   = '';
                $statusParams   = [];
                $statusTypes    = '';
                if ($status_filter) {
                    $statusClause = "AND status = ?";
                    $statusParams = [$status_filter];
                    $statusTypes  = 's';
                }

                $sql = "SELECT id, name, species, breed, age, gender, status, health_status,
                               DATE_FORMAT(created_at,'%Y-%m-%d') as created_at
                        FROM pets WHERE 1=1 {$statusClause}
                        ORDER BY created_at DESC LIMIT 200";

                if ($statusParams) {
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($statusTypes, ...$statusParams);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query($sql);
                }
                if ($result) while ($r = $result->fetch_assoc()) $rows[] = $r;

                $summary = [
                    'total'           => count($rows),
                    'available'       => count(array_filter($rows, fn($r) => $r['status'] === 'available')),
                    'reserved'        => count(array_filter($rows, fn($r) => $r['status'] === 'reserved')),
                    'in_adoption'     => count(array_filter($rows, fn($r) => $r['status'] === 'in_adoption')),
                    'adopted'         => count(array_filter($rows, fn($r) => $r['status'] === 'adopted')),
                    'under_treatment' => count(array_filter($rows, fn($r) => $r['status'] === 'under_treatment')),
                ];
            } else {
                respondJSON(false, 'Unknown report type');
            }

            respondJSON(true, 'Report generated', [
                'report_type' => $report_type,
                'summary'     => $summary,
                'rows'        => $rows,
            ]);
            break;

        // ================================================================
        // DASHBOARD CHARTS DATA
        // ================================================================

        case 'get_dashboard_charts':
            // Monthly adoptions — last 6 calendar months
            $months = [];
            $monthMap = [];
            for ($i = 5; $i >= 0; $i--) {
                $label = date('M Y', strtotime("-{$i} months"));
                $key   = date('Y-m',  strtotime("-{$i} months"));
                $months[]       = $label;
                $monthMap[$key] = 0;
            }
            $result = $conn->query(
                "SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, COUNT(*) as cnt
                 FROM adoption_applications
                 WHERE status IN ('approved','for_releasing','ready_pickup','completed')
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY ym"
            );
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    if (isset($monthMap[$row['ym']])) $monthMap[$row['ym']] = (int)$row['cnt'];
                }
            }
            $monthlyAdoptions = ['labels' => $months, 'data' => array_values($monthMap)];

            // Application status distribution
            $appStatus = [];
            $result = $conn->query(
                "SELECT status, COUNT(*) as cnt FROM adoption_applications GROUP BY status"
            );
            if ($result) while ($row = $result->fetch_assoc()) $appStatus[] = $row;

            // Appointment status
            $aptStatus = [];
            $result = $conn->query(
                "SELECT status, COUNT(*) as cnt FROM appointments GROUP BY status"
            );
            if ($result) while ($row = $result->fetch_assoc()) $aptStatus[] = $row;

            // Pet status
            $petStatus = [];
            $result = $conn->query(
                "SELECT status, COUNT(*) as cnt FROM pets GROUP BY status"
            );
            if ($result) while ($row = $result->fetch_assoc()) $petStatus[] = $row;

            respondJSON(true, '', [
                'monthlyAdoptions' => $monthlyAdoptions,
                'appStatus'        => $appStatus,
                'aptStatus'        => $aptStatus,
                'petStatus'        => $petStatus,
            ]);
            break;

        default:
            respondJSON(false, 'Invalid action: ' . htmlspecialchars($action));
    }
} catch (Throwable $e) {
    respondJSON(false, 'Server error: ' . $e->getMessage());
} finally {
    if (isset($conn) && $conn instanceof mysqli) $conn->close();
}
?>