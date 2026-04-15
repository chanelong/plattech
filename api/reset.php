<?php
require 'config.php';
$data    = getInput();
$email   = trim($data['email'] ?? '');
$code    = trim($data['code'] ?? '');
$newPass = $data['password'] ?? '';

if (!$email || !$code || !$newPass) {
  echo json_encode(["success" => false, "message" => "All fields are required"]);
  exit;
}

if (strlen($newPass) < 6) {
  echo json_encode(["success" => false, "message" => "Password must be at least 6 characters"]);
  exit;
}

$stmt = $conn->prepare("SELECT id, name, reset_code, reset_expires FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || $user['reset_code'] !== $code) {
  echo json_encode(["success" => false, "message" => "Invalid or expired reset session"]);
  exit;
}

if (strtotime($user['reset_expires']) < time()) {
  echo json_encode(["success" => false, "message" => "Code has expired. Please request a new one."]);
  exit;
}

$hashed = password_hash($newPass, PASSWORD_BCRYPT);
$upd    = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_expires = NULL WHERE email = ?");
$upd->bind_param("ss", $hashed, $email);
$upd->execute();

logActivity($conn, $user['id'], "reset_password", "Password reset successfully for $email");

echo json_encode(["success" => true, "message" => "Password reset successfully"]);
?>
