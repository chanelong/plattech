<?php
require 'config.php';
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// GET notifications for a user
if ($action === 'list' && $method === 'GET') {
  $userId = intval($_GET['user_id'] ?? 0);
  $limit  = intval($_GET['limit'] ?? 30);

  $stmt = $conn->prepare(
    "SELECT * FROM notifications WHERE user_id = ?
     ORDER BY created_at DESC LIMIT ?"
  );
  $stmt->bind_param("ii", $userId, $limit);
  $stmt->execute();
  echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

// GET unread count
if ($action === 'unread_count' && $method === 'GET') {
  $userId = intval($_GET['user_id'] ?? 0);
  $result = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id = $userId AND is_read = 0");
  echo json_encode(["count" => $result->fetch_assoc()['c']]);
}

// POST mark all as read
if ($action === 'mark_read' && $method === 'POST') {
  $data   = getInput();
  $userId = intval($data['user_id'] ?? 0);
  $id     = intval($data['id'] ?? 0);

  if ($id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
  } else {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
  }
  $stmt->execute();
  echo json_encode(["success" => true]);
}

// POST create notification (internal use / admin)
if ($action === 'create' && $method === 'POST') {
  $data      = getInput();
  $userId    = intval($data['user_id'] ?? 0);
  $type      = $data['type'] ?? 'info';
  $message   = $data['message'] ?? '';
  $relatedId = intval($data['related_id'] ?? 0);

  $stmt = $conn->prepare(
    "INSERT INTO notifications (user_id, type, message, related_id) VALUES (?,?,?,?)"
  );
  $stmt->bind_param("issi", $userId, $type, $message, $relatedId);
  $stmt->execute();
  echo json_encode(["success" => true, "id" => $conn->insert_id]);
}

// POST delete notification
if ($action === 'delete' && $method === 'POST') {
  $data   = getInput();
  $id     = intval($data['id'] ?? 0);
  $userId = intval($data['user_id'] ?? 0);

  $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
  $stmt->bind_param("ii", $id, $userId);
  $stmt->execute();
  echo json_encode(["success" => true]);
}
?>
