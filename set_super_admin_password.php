<?php
$mysqli = new mysqli('127.0.0.1', 'root', '');
if ($mysqli->connect_errno) { echo 'CONNECT_ERROR: '.$mysqli->connect_error; exit(1); }
$hash = '$2y$10$/GCfi5MolnPs6A9EWTNADuhFLyK9tsHmLClprXoe8WHAOBxTaruB.';
$user = 'super_admin123';
$stmt = $mysqli->prepare('UPDATE anipet_db.users SET password=? WHERE username=?');
if (!$stmt) { echo 'PREPARE_ERROR: '.$mysqli->error; exit(1); }
$stmt->bind_param('ss', $hash, $user);
$stmt->execute();
echo $stmt->affected_rows ? 'UPDATED' : 'NOCHANGE';
$stmt->close();
$mysqli->close();
