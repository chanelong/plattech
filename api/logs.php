<?php
require 'config.php';
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// GET all logs
if ($action === 'list' && $method === 'GET') {
  $filter = $_GET['filter'] ?? '';
  $limit  = intval($_GET['limit'] ?? 200);

  $sql = "SELECT l.*, u.name as user_name FROM logs l
          LEFT JOIN users u ON l.user_id = u.id";

  if ($filter) {
    $f   = "%$filter%";
    $sql .= " WHERE l.action LIKE '$f' OR u.name LIKE '$f' OR l.description LIKE '$f'";
  }

  $sql .= " ORDER BY l.created_at DESC LIMIT $limit";
  $result = $conn->query($sql);
  echo json_encode($result->fetch_all(MYSQLI_ASSOC));
}

// POST clear all logs
if ($action === 'clear' && $method === 'POST') {
  $data    = getInput();
  $adminId = intval($data['admin_id'] ?? 0);
  $conn->query("TRUNCATE TABLE logs");
  logActivity($conn, $adminId, "admin", "Cleared all system logs");
  echo json_encode(["success" => true]);
}
?>
