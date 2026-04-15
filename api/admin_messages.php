<?php
require 'config.php';
// NOTE: Run this migration once if upgrading:
// ALTER TABLE admin_messages ADD COLUMN IF NOT EXISTS sent_by INT DEFAULT NULL;
// sent_by = admin_id means admin sent it; sent_by = renter_id means rentee sent it.
$method = $_SERVER['REQUEST_METHOD'];
// Support action in URL query string OR in POST JSON body
$action = $_GET['action'] ?? '';
if (!$action && $method === 'POST') {
  $bodyRaw = file_get_contents('php://input');
  $bodyArr = json_decode($bodyRaw, true);
  $action  = $bodyArr['action'] ?? 'list';
}
if (!$action) $action = 'list';

// GET list of messages (admin or renter view)
if ($action === 'list' && $method === 'GET') {
  $adminId  = intval($_GET['admin_id'] ?? 0);
  $renterId = intval($_GET['renter_id'] ?? 0);

  if ($adminId) {
    // Admin sees all threads (latest per renter)
    $stmt = $conn->prepare(
      "SELECT am.*, u.name as renter_name, u.email as renter_email,
        (SELECT COUNT(*) FROM admin_messages WHERE renter_id = am.renter_id AND is_read = 0 AND admin_id = am.admin_id) as unread
       FROM admin_messages am
       JOIN users u ON am.renter_id = u.id
       WHERE am.admin_id = ?
       GROUP BY am.renter_id
       ORDER BY am.created_at DESC"
    );
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));

  } elseif ($renterId) {
    // Renter sees their thread
    $stmt = $conn->prepare(
      "SELECT am.*, u.name as admin_name FROM admin_messages am
       JOIN users u ON am.admin_id = u.id
       WHERE am.renter_id = ?
       ORDER BY am.created_at ASC"
    );
    $stmt->bind_param("i", $renterId);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
  } else {
    echo json_encode([]);
  }
}

// GET full conversation between admin and renter (by renter_id only — for renter view)
if ($action === 'conversation_renter' && $method === 'GET') {
  $renterId = intval($_GET['renter_id'] ?? 0);
  if (!$renterId) { echo json_encode([]); exit; }

  $stmt = $conn->prepare(
    "SELECT am.*, am.sent_by,
            a.name as admin_name,
            r.name as renter_name
     FROM admin_messages am
     JOIN users a ON am.admin_id = a.id
     JOIN users r ON am.renter_id = r.id
     WHERE am.renter_id = ?
     ORDER BY am.created_at ASC"
  );
  $stmt->bind_param("i", $renterId);
  $stmt->execute();
  echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

// GET full conversation between admin and renter
if ($action === 'conversation' && $method === 'GET') {
  $adminId  = intval($_GET['admin_id'] ?? 0);
  $renterId = intval($_GET['renter_id'] ?? 0);

  $stmt = $conn->prepare(
    "SELECT am.*, am.sent_by,
            a.name as admin_name,
            r.name as renter_name
     FROM admin_messages am
     JOIN users a ON am.admin_id = a.id
     JOIN users r ON am.renter_id = r.id
     WHERE am.admin_id = ? AND am.renter_id = ?
     ORDER BY am.created_at ASC"
  );
  $stmt->bind_param("ii", $adminId, $renterId);
  $stmt->execute();
  echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

// GET list of all renters (for admin to choose who to contact)
if ($action === 'renters' && $method === 'GET') {
  $result = $conn->query(
    "SELECT id, name, email, created_at FROM users WHERE role = 'renter' AND status = 'active' ORDER BY name ASC"
  );
  echo json_encode($result->fetch_all(MYSQLI_ASSOC));
}

// POST send message (admin to renter)
if ($action === 'send' && $method === 'POST') {
  $data     = getInput();
  $adminId  = intval($data['admin_id'] ?? 0);
  $renterId = intval($data['renter_id'] ?? 0);
  $message  = trim($data['message'] ?? '');

  if (!$adminId || !$renterId || !$message) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
  }

  // Verify sender is admin
  $chk = $conn->query("SELECT role FROM users WHERE id = $adminId")->fetch_assoc();
  if (!$chk || $chk['role'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
  }

  $stmt = $conn->prepare(
    "INSERT INTO admin_messages (admin_id, renter_id, message, sent_by) VALUES (?,?,?,?)"
  );
  $stmt->bind_param("iisi", $adminId, $renterId, $message, $adminId);
  $stmt->execute();
  $newMsgId = $conn->insert_id; // Capture before any other query resets it

  // Notify the renter
  $admin = $conn->query("SELECT name FROM users WHERE id = $adminId")->fetch_assoc();
  $notifMsg = "Admin {$admin['name']} sent you a message.";
  $ns = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'admin_message', ?, ?)");
  $ns->bind_param("isi", $renterId, $notifMsg, $newMsgId);
  $ns->execute();

  logActivity($conn, $adminId, "admin_message", "Sent message to renter #$renterId");
  echo json_encode(["success" => true, "id" => $newMsgId]);
}

// POST send message (renter/rentee to admin)
if ($action === 'send_renter' && $method === 'POST') {
  $data     = getInput();
  $renterId = intval($data['renter_id'] ?? 0);
  $message  = trim($data['message'] ?? '');

  if (!$renterId || !$message) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit;
  }

  // Verify sender exists and is a rentee/renter
  $chk = $conn->query("SELECT role, name FROM users WHERE id = $renterId")->fetch_assoc();
  if (!$chk) {
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit;
  }

  // Find any admin to associate the thread with
  $adminRow = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
  if (!$adminRow) {
    echo json_encode(["success" => false, "message" => "No admin available"]);
    exit;
  }
  $adminId = $adminRow['id'];

  $stmt = $conn->prepare(
    "INSERT INTO admin_messages (admin_id, renter_id, message, sent_by) VALUES (?,?,?,?)"
  );
  $stmt->bind_param("iisi", $adminId, $renterId, $message, $renterId);
  $stmt->execute();
  $newId = $conn->insert_id; // Capture before any other query resets it

  // Notify the admin
  $renterName = $chk['name'];
  $notifMsg = "Renter {$renterName} sent you a message.";
  $ns = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'admin_message', ?, ?)");
  $ns->bind_param("isi", $adminId, $notifMsg, $newId);
  $ns->execute();

  logActivity($conn, $renterId, "admin_message", "Sent message to admin");
  echo json_encode(["success" => true, "id" => $newId]);
}

// POST mark messages as read
if ($action === 'mark_read' && $method === 'POST') {
  $data     = getInput();
  $renterId = intval($data['renter_id'] ?? 0);
  $adminId  = intval($data['admin_id'] ?? 0);

  if ($renterId) {
    $stmt = $conn->prepare("UPDATE admin_messages SET is_read = 1 WHERE renter_id = ?");
    $stmt->bind_param("i", $renterId);
    $stmt->execute();
  }
  echo json_encode(["success" => true]);
}

// GET unread count for renter
if ($action === 'unread_count' && $method === 'GET') {
  $renterId = intval($_GET['renter_id'] ?? 0);
  $count = $conn->query("SELECT COUNT(*) as c FROM admin_messages WHERE renter_id = $renterId AND is_read = 0")->fetch_assoc()['c'];
  echo json_encode(["count" => $count]);
}
?>