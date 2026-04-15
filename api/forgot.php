<?php
require 'config.php';
$data  = getInput();
$email = trim($data['email'] ?? '');

if (!$email) {
  echo json_encode(["success" => false, "message" => "Email is required"]);
  exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
  echo json_encode(["success" => false, "message" => "No account found with that email"]);
  exit;
}

$code    = strval(rand(100000, 999999));
$expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

$upd = $conn->prepare("UPDATE users SET reset_code = ?, reset_expires = ? WHERE email = ?");
$upd->bind_param("sss", $code, $expires, $email);
$upd->execute();

logActivity($conn, $user['id'], "forgot_password", "Reset code requested for $email");

// In production: send real email via PHPMailer
// For now: return code in response (remove in production)
echo json_encode([
  "success" => true,
  "code"    => $code, // REMOVE THIS IN PRODUCTION
  "message" => "Reset code sent to $email"
]);
?>
