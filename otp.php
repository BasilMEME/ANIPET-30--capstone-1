<?php
// CRITICAL: No space before <?php
ob_start();  // Start buffer
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/db_connect.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$otp = trim($input['otp'] ?? '');

$response = ['status' => 'error', 'message' => 'Missing data'];

if (!empty($email) && !empty($otp)) {
    $stmt = $conn->prepare("SELECT * FROM otps WHERE email = ? AND otp = ? AND expires_at > NOW()");
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $response = [
            'status' => 'success',
            'message' => 'OTP verified'
        ];
        // Delete used OTP
        $delStmt = $conn->prepare("DELETE FROM otps WHERE email = ?");
        $delStmt->bind_param("s", $email);
        $delStmt->execute();
    } else {
        $response['message'] = 'Invalid/expired OTP';
    }
    $stmt->close();
}

ob_end_clean();  // Clear buffer
echo json_encode($response);
$conn->close();
?>