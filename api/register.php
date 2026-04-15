<?php
require 'config.php';
$data  = getInput();
$name  = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$pass  = $data['password'] ?? '';
$role  = $data['role'] ?? 'rentee';

if (!$name || !$email || !$pass) {
  echo json_encode(["success" => false, "message" => "All fields are required"]);
  exit;
}

if (!in_array($role, ['renter', 'rentee'])) {
  $role = 'rentee';
}

$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
  echo json_encode(["success" => false, "message" => "Email already registered"]);
  exit;
}

$hashed = password_hash($pass, PASSWORD_BCRYPT);
$stmt   = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
$stmt->bind_param("ssss", $name, $email, $hashed, $role);
$stmt->execute();
$userId = $conn->insert_id;

logActivity($conn, $userId, "register", "New $role account created: $name");

echo json_encode([
  "success" => true,
  "user" => [
    "id"        => $userId,
    "name"      => $name,
    "email"     => $email,
    "role"      => $role,
    "status"    => "active",
    "createdAt" => date("Y-m-d")
  ]
]);
?>
