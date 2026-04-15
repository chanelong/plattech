<?php
require 'config.php';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Middleware: only allow admin role
// In production, verify session/token here

// GET all users
if ($action === 'users' && $method === 'GET') {
  $result = $conn->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
  echo json_encode($result->fetch_all(MYSQLI_ASSOC));
}

// GET dashboard stats
if ($action === 'stats' && $method === 'GET') {
  $users    = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
  $dorms    = $conn->query("SELECT COUNT(*) as c FROM dorms")->fetch_assoc()['c'];
  $pending  = $conn->query("SELECT COUNT(*) as c FROM dorms WHERE status='pending'")->fetch_assoc()['c'];
  $bookings = $conn->query("SELECT COUNT(*) as c FROM bookings")->fetch_assoc()['c'];
  $logs     = $conn->query("SELECT COUNT(*) as c FROM logs")->fetch_assoc()['c'];
  $banned   = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='banned'")->fetch_assoc()['c'];
  // New stats
  $bin_count = $conn->query("SELECT COUNT(*) as c FROM recently_deleted")->fetch_assoc()['c'];
  $notices   = $conn->query("SELECT COUNT(*) as c FROM inactive_notices WHERE status='active'")->fetch_assoc()['c'];
  echo json_encode(compact('users','dorms','pending','bookings','logs','banned','bin_count','notices'));
}

// POST change role
if ($action === 'change_role' && $method === 'POST') {
  $data    = getInput();
  $userId  = intval($data['user_id'] ?? 0);
  $role    = $data['role'] ?? '';
  $adminId = intval($data['admin_id'] ?? 0);

  if (!in_array($role, ['admin','renter','rentee'])) {
    echo json_encode(["success" => false, "message" => "Invalid role"]);
    exit;
  }

  $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
  $stmt->bind_param("si", $role, $userId);
  $stmt->execute();

  $u = $conn->query("SELECT name FROM users WHERE id = $userId")->fetch_assoc();
  logActivity($conn, $adminId, "role_change", "Changed user #$userId ({$u['name']}) role to $role");
  echo json_encode(["success" => true]);
}

// POST ban/unban user
if ($action === 'toggle_ban' && $method === 'POST') {
  $data    = getInput();
  $userId  = intval($data['user_id'] ?? 0);
  $adminId = intval($data['admin_id'] ?? 0);

  $cur = $conn->query("SELECT status, name FROM users WHERE id = $userId")->fetch_assoc();
  $newStatus = $cur['status'] === 'active' ? 'banned' : 'active';

  $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
  $stmt->bind_param("si", $newStatus, $userId);
  $stmt->execute();

  logActivity($conn, $adminId, "ban_user", ($newStatus === 'banned' ? 'Banned' : 'Unbanned') . " user {$cur['name']} #$userId");
  echo json_encode(["success" => true, "status" => $newStatus]);
}

// POST delete user (soft-delete: moved to recently_deleted bin first)
if ($action === 'delete_user' && $method === 'POST') {
  $data    = getInput();
  $userId  = intval($data['user_id'] ?? 0);
  $adminId = intval($data['admin_id'] ?? 0);

  $u = $conn->query("SELECT * FROM users WHERE id = $userId")->fetch_assoc();
  if (!$u) {
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit;
  }

  // Save snapshot to recently_deleted bin BEFORE deleting
  $snapshot = json_encode($u);
  $purgeAt  = date("Y-m-d H:i:s", strtotime("+30 days"));
  $binStmt  = $conn->prepare(
    "INSERT INTO recently_deleted (type, original_id, data, deleted_by, auto_purge_at) VALUES ('user', ?, ?, ?, ?)"
  );
  $binStmt->bind_param("isis", $userId, $snapshot, $adminId, $purgeAt);
  $binStmt->execute();

  // Now delete the user
  $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();

  logActivity($conn, $adminId, "delete_user", "Deleted user #$userId: " . ($u['name'] ?? 'Unknown') . " (moved to bin)");
  echo json_encode(["success" => true]);
}

// GET all dorms (admin view - all statuses)
if ($action === 'dorms' && $method === 'GET') {
  $result = $conn->query(
    "SELECT d.*, u.name as owner_name FROM dorms d JOIN users u ON d.owner_id = u.id ORDER BY d.created_at DESC"
  );
  $rows = $result->fetch_all(MYSQLI_ASSOC);
  foreach ($rows as &$r) $r['amenities'] = $r['amenities'] ? explode(",", $r['amenities']) : [];
  echo json_encode($rows);
}
?>
