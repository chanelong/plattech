<?php
require 'config.php';
$data  = getInput();
$email = trim($data['email'] ?? '');
$code  = trim($data['code'] ?? '');

if (!$email || !$code) {
  echo json_encode(["success" => false, "message" => "Email and code are required"]);
  exit;
}

$stmt = $conn->prepare("SELECT reset_code, reset_expires FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || $row['reset_code'] !== $code) {
  echo json_encode(["success" => false, "message" => "Incorrect reset code"]);
  exit;
}

if (strtotime($row['reset_expires']) < time()) {
  echo json_encode(["success" => false, "message" => "Code has expired. Please request a new one."]);
  exit;
}

echo json_encode(["success" => true, "message" => "Code verified"]);
?>
