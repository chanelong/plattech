<?php
require 'config.php';
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

if ($action === 'list' && $method === 'GET') {
  $dormId = intval($_GET['dorm_id'] ?? 0);
  $stmt   = $conn->prepare(
    "SELECT r.*, u.name as user_name FROM reviews r
     JOIN users u ON r.user_id = u.id WHERE r.dorm_id = ? ORDER BY r.created_at DESC"
  );
  $stmt->bind_param("i", $dormId);
  $stmt->execute();
  echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

if ($action === 'add' && $method === 'POST') {
  $data    = getInput();
  $userId  = intval($data['user_id'] ?? 0);
  $dormId  = intval($data['dorm_id'] ?? 0);
  $rating  = intval($data['rating'] ?? 0);
  $comment = trim($data['comment'] ?? '');

  if ($rating < 1 || $rating > 5) {
    echo json_encode(["success" => false, "message" => "Rating must be 1-5"]);
    exit;
  }

  // Only current or former tenants (approved or vacated bookings) may review
  $eligible = $conn->prepare(
    "SELECT id FROM bookings WHERE user_id = ? AND dorm_id = ? AND status IN ('approved','vacated')"
  );
  $eligible->bind_param("ii", $userId, $dormId);
  $eligible->execute();
  if ($eligible->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Only current or former tenants can leave a review for this dorm."]);
    exit;
  }

  $chk = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND dorm_id = ?");
  $chk->bind_param("ii", $userId, $dormId);
  $chk->execute();
  if ($chk->get_result()->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "You already reviewed this dorm"]);
    exit;
  }

  $stmt = $conn->prepare("INSERT INTO reviews (user_id, dorm_id, rating, comment) VALUES (?,?,?,?)");
  $stmt->bind_param("iiis", $userId, $dormId, $rating, $comment);
  $stmt->execute();
  $reviewId = $conn->insert_id;

  $d = $conn->query("SELECT name, owner_id FROM dorms WHERE id = $dormId")->fetch_assoc();
  $u = $conn->query("SELECT name FROM users WHERE id = $userId")->fetch_assoc();

  logActivity($conn, $userId, "review", "Reviewed " . ($d['name'] ?? "#$dormId") . " ($rating stars)");

  // Notify the dorm owner of the new review
  if ($d && $u) {
    $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    $notifMsg = "{$u['name']} left a {$rating}-star review on {$d['name']}: \"" . mb_substr($comment, 0, 60) . (strlen($comment) > 60 ? '...' : '') . "\"";
    $ns = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'new_review', ?, ?)");
    $ns->bind_param("isi", $d['owner_id'], $notifMsg, $dormId);
    $ns->execute();
  }

  echo json_encode(["success" => true, "id" => $reviewId]);
}
