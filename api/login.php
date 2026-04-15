<?php
require 'config.php';
$data  = getInput();
$email = trim($data['email'] ?? '');
$pass  = $data['password'] ?? '';

if (!$email || !$pass) {
  echo json_encode(["success" => false, "message" => "Email and password are required"]);
  exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($pass, $user['password'])) {
  echo json_encode(["success" => false, "message" => "Invalid email or password"]);
  exit;
}

if ($user['status'] === 'banned') {
  echo json_encode(["success" => false, "message" => "Your account has been banned. Contact support."]);
  exit;
}

logActivity($conn, $user['id'], "login", "User {$user['name']} logged in");

unset($user['password'], $user['reset_code']);
echo json_encode(["success" => true, "user" => $user]);
?>
