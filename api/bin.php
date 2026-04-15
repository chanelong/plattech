<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'config.php';
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// ============================================================
// RECENTLY DELETED BIN
// ============================================================

// GET recently deleted items
if ($action === 'list' && $method === 'GET') {
  $type   = $_GET['type'] ?? '';
  $search = $_GET['search'] ?? '';

  $sql = "SELECT rd.*, u.name as deleted_by_name FROM recently_deleted rd
          LEFT JOIN users u ON rd.deleted_by = u.id WHERE 1=1";
  $params = "";
  $vals   = [];

  if ($type) { $sql .= " AND rd.type = ?"; $params .= "s"; $vals[] = $type; }
  if ($search) {
    $sql    .= " AND rd.data LIKE ?";
    $params .= "s";
    $vals[]  = "%$search%";
  }
  $sql .= " ORDER BY rd.deleted_at DESC LIMIT 100";

  if ($vals) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($params, ...$vals);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  } else {
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
  }

  // Decode JSON data for display
  foreach ($rows as &$r) {
    $r['data_parsed'] = json_decode($r['data'], true);
  }
  echo json_encode($rows);
}

// POST permanently delete from bin
if ($action === 'purge' && $method === 'POST') {
  $data    = getInput();
  $id      = intval($data['id'] ?? 0);
  $adminId = intval($data['admin_id'] ?? 0);

  $stmt = $conn->prepare("DELETE FROM recently_deleted WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  logActivity($conn, $adminId, "purge_deleted", "Permanently purged recently_deleted #$id");
  echo json_encode(["success" => true]);
}

// POST restore a deleted item
if ($action === 'restore' && $method === 'POST') {
  $data    = getInput();
  $id      = intval($data['id'] ?? 0);
  $adminId = intval($data['admin_id'] ?? 0);

  $row = $conn->query("SELECT * FROM recently_deleted WHERE id = $id")->fetch_assoc();
  if (!$row) { echo json_encode(["success" => false, "message" => "Item not found"]); exit; }

  $item = json_decode($row['data'], true);
  if (!$item) { echo json_encode(["success" => false, "message" => "Cannot restore: data corrupt"]); exit; }

  if ($row['type'] === 'user') {
    // Check if user already exists (already restored or never fully deleted)
    $exists = $conn->query("SELECT id FROM users WHERE id = {$item['id']} OR email = '" . $conn->real_escape_string($item['email']) . "'")->fetch_assoc();
    if ($exists) {
      // Already restored — just clean up the bin record
      $conn->query("DELETE FROM recently_deleted WHERE id = $id");
      logActivity($conn, $adminId, "restore_user", "Cleaned stale bin entry for user #{$item['id']}: {$item['name']} (already exists)");
      echo json_encode(["success" => true, "message" => "User already exists — bin entry removed."]);
      exit;
    }

    $stmt = $conn->prepare(
      "INSERT INTO users (id, name, email, password, role, status, created_at) VALUES (?,?,?,?,?,?,?)"
    );
    $stmt->bind_param("issssss",
      $item['id'], $item['name'], $item['email'], $item['password'],
      $item['role'], $item['status'], $item['created_at']
    );
    if ($stmt->execute()) {
      $conn->query("DELETE FROM recently_deleted WHERE id = $id");
      logActivity($conn, $adminId, "restore_user", "Restored user #{$item['id']}: {$item['name']}");
      echo json_encode(["success" => true]);
    } else {
      echo json_encode(["success" => false, "message" => "Restore failed: " . $conn->error]);
    }

  } elseif ($row['type'] === 'dorm') {
    $dormOrigId = intval($item['id']);

    // Check if dorm already exists (already restored)
    $exists = $conn->query("SELECT id FROM dorms WHERE id = $dormOrigId")->fetch_assoc();
    if ($exists) {
      // Already restored — just clean up the stale bin record
      $conn->query("DELETE FROM recently_deleted WHERE id = $id");
      logActivity($conn, $adminId, "restore_dorm", "Cleaned stale bin entry for dorm #$dormOrigId: {$item['name']} (already exists)");
      echo json_encode(["success" => true, "message" => "Dorm already exists — bin entry removed."]);
      exit;
    }

    // Restore dorm (set status to pending for re-review)
    $stmt = $conn->prepare(
      "INSERT INTO dorms (id, owner_id, name, description, address, university, price, lat, lng, availability, total_slots, amenities, images, status, created_at)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',?)"
    );
    $owner_id     = intval($item['owner_id']);
    $name         = $item['name'] ?? '';
    $description  = $item['description'] ?? '';
    $address      = $item['address'] ?? '';
    $university   = $item['university'] ?? '';
    $price        = floatval($item['price']);
    $lat          = floatval($item['lat']);
    $lng          = floatval($item['lng']);
    $availability = intval($item['availability']);
    $total_slots  = intval($item['total_slots']);
    $amenities    = $item['amenities'] ?? '';
    $images       = $item['images'] ?? '';
    $created_at   = $item['created_at'] ?? date("Y-m-d H:i:s");
    // 14 placeholders: id(i), owner_id(i), name(s), description(s), address(s),
    //   university(s), price(d), lat(d), lng(d), availability(i), total_slots(i),
    //   amenities(s), images(s), created_at(s)
    $stmt->bind_param("iissssdddiisss",
      $dormOrigId, $owner_id, $name, $description,
      $address, $university, $price,
      $lat, $lng, $availability, $total_slots,
      $amenities, $images, $created_at
    );
    if ($stmt->execute()) {
      $conn->query("DELETE FROM recently_deleted WHERE id = $id");
      logActivity($conn, $adminId, "restore_dorm", "Restored dorm #$dormOrigId: {$item['name']}");
      echo json_encode(["success" => true]);
    } else {
      echo json_encode(["success" => false, "message" => "Restore failed: " . $conn->error]);
    }
  }
}

// POST clear expired items from bin
if ($action === 'clear_expired' && $method === 'POST') {
  $data    = getInput();
  $adminId = intval($data['admin_id'] ?? 0);
  $conn->query("DELETE FROM recently_deleted WHERE auto_purge_at < NOW()");
  logActivity($conn, $adminId, "clear_bin", "Cleared expired items from recently deleted bin");
  echo json_encode(["success" => true]);
}

// POST create a bin entry manually (called from JS before physical delete)
if ($action === 'create_entry' && $method === 'POST') {
  $data      = getInput();
  $type      = $data['type'] ?? 'dorm';
  $origId    = intval($data['original_id'] ?? 0);
  $jsonData  = $data['data'] ?? '{}';
  $deletedBy = intval($data['deleted_by'] ?? 0);
  $purgeAt   = $data['auto_purge_at'] ?? date("Y-m-d H:i:s", strtotime("+30 days"));

  if (!in_array($type, ['dorm','user'])) {
    echo json_encode(["success" => false, "message" => "Invalid type"]);
    exit;
  }

  $stmt = $conn->prepare(
    "INSERT INTO recently_deleted (type, original_id, data, deleted_by, auto_purge_at) VALUES (?,?,?,?,?)"
  );
  $stmt->bind_param("sisss", $type, $origId, $jsonData, $deletedBy, $purgeAt);
  $stmt->execute();
  echo json_encode(["success" => true, "id" => $conn->insert_id]);
  exit;
}

// ============================================================
// INACTIVE NOTICES
// ============================================================

// GET inactive notices list
if ($action === 'notices' && $method === 'GET') {
  $result = $conn->query(
    "SELECT n.*, d.name as dorm_name, d.owner_id, d.availability, d.last_activity,
            u.name as owner_name, u.email as owner_email,
            a.name as admin_name
     FROM inactive_notices n
     JOIN dorms d ON n.dorm_id = d.id
     JOIN users d_owner ON d.owner_id = d_owner.id
     LEFT JOIN users a ON n.admin_id = a.id
     JOIN users u ON d.owner_id = u.id
     WHERE n.status = 'active'
     ORDER BY n.notice_date DESC"
  );
  echo json_encode($result->fetch_all(MYSQLI_ASSOC));
}

// POST send inactive notice to a dorm
if ($action === 'send_notice' && $method === 'POST') {
  $data    = getInput();
  $dormId  = intval($data['dorm_id'] ?? 0);
  $adminId = intval($data['admin_id'] ?? 0);

  // Check if notice already exists
  $existing = $conn->query("SELECT id FROM inactive_notices WHERE dorm_id = $dormId AND status = 'active'")->fetch_assoc();
  if ($existing) {
    echo json_encode(["success" => false, "message" => "This dorm already has an active notice"]);
    exit;
  }

  $expiresAt = date("Y-m-d H:i:s", strtotime("+30 days"));
  $stmt = $conn->prepare("INSERT INTO inactive_notices (dorm_id, admin_id, expires_at) VALUES (?,?,?)");
  $stmt->bind_param("iis", $dormId, $adminId, $expiresAt);
  $stmt->execute();

  // Notify the owner
  $dorm = $conn->query("SELECT name, owner_id FROM dorms WHERE id = $dormId")->fetch_assoc();
  if ($dorm) {
    $notifMsg = "⚠️ Your listing \"{$dorm['name']}\" has been marked as inactive. Please update it within 30 days or it will be automatically removed.";
    $ns = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'inactive_notice', ?, ?)");
    $ns->bind_param("isi", $dorm['owner_id'], $notifMsg, $dormId);
    $ns->execute();
  }

  logActivity($conn, $adminId, "inactive_notice", "Sent inactive notice for dorm #$dormId: {$dorm['name']}");
  echo json_encode(["success" => true]);
}

// POST resolve a notice (admin marks dorm active again)
if ($action === 'resolve_notice' && $method === 'POST') {
  $data    = getInput();
  $id      = intval($data['id'] ?? 0);
  $adminId = intval($data['admin_id'] ?? 0);

  $stmt = $conn->prepare("UPDATE inactive_notices SET status = 'resolved' WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  logActivity($conn, $adminId, "resolve_notice", "Resolved inactive notice #$id");
  echo json_encode(["success" => true]);
}

// POST auto-process expired notices (delete dorms, move to bin)
if ($action === 'process_expired' && $method === 'POST') {
  $data    = getInput();
  $adminId = intval($data['admin_id'] ?? 0);

  $expired = $conn->query(
    "SELECT n.*, d.name as dorm_name FROM inactive_notices n
     JOIN dorms d ON n.dorm_id = d.id
     WHERE n.status = 'active' AND n.expires_at < NOW()"
  )->fetch_all(MYSQLI_ASSOC);

  $deleted = 0;
  foreach ($expired as $notice) {
    $dormId = $notice['dorm_id'];
    // Get full dorm data for the bin
    $dorm = $conn->query("SELECT * FROM dorms WHERE id = $dormId")->fetch_assoc();
    if (!$dorm) continue;

    // Move to recently deleted
    $json = $conn->real_escape_string(json_encode($dorm));
    $purgeAt = date("Y-m-d H:i:s", strtotime("+30 days"));
    $conn->query("INSERT INTO recently_deleted (type, original_id, data, deleted_by, auto_purge_at) VALUES ('dorm', $dormId, '$json', $adminId, '$purgeAt')");

    // Delete the dorm
    $conn->query("DELETE FROM dorms WHERE id = $dormId");

    // Update notice
    $conn->query("UPDATE inactive_notices SET status = 'auto_deleted' WHERE id = {$notice['id']}");
    logActivity($conn, $adminId, "auto_delete_dorm", "Auto-deleted inactive dorm #{$dormId}: {$notice['dorm_name']}");
    $deleted++;
  }

  echo json_encode(["success" => true, "deleted" => $deleted]);
}
?>
