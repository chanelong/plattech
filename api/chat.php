<?php
require 'config.php';
$method = $_SERVER['REQUEST_METHOD'];

// Support action in URL query string OR in POST JSON body
$action = $_GET['action'] ?? '';
if (!$action && $method === 'POST') {
  $bodyRaw = file_get_contents('php://input');
  $bodyArr = json_decode($bodyRaw, true);
  $action  = $bodyArr['action'] ?? '';
}
if (!$action) $action = 'list';

// GET messages for a booking (only if approved)
if ($action === 'messages' && $method === 'GET') {
  $bookingId = intval($_GET['booking_id'] ?? 0);
  $userId    = intval($_GET['user_id'] ?? 0);

  // Verify the booking is approved or vacated and user is part of it (rentee or dorm owner)
  $chk = $conn->prepare(
    "SELECT b.id FROM bookings b
     JOIN dorms d ON b.dorm_id = d.id
     WHERE b.id = ? AND b.status IN ('approved','vacated')
       AND (b.user_id = ? OR d.owner_id = ?)"
  );
  $chk->bind_param("iii", $bookingId, $userId, $userId);
  $chk->execute();
  if ($chk->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Access denied or booking not approved"]);
    exit;
  }

  $stmt = $conn->prepare(
    "SELECT m.*, u.name as sender_name, u.role as sender_role
     FROM chat_messages m
     JOIN users u ON m.sender_id = u.id
     WHERE m.booking_id = ?
     ORDER BY m.created_at ASC"
  );
  $stmt->bind_param("i", $bookingId);
  $stmt->execute();
  echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

// POST send message
if ($action === 'send' && $method === 'POST') {
  $data      = getInput();
  $bookingId = intval($data['booking_id'] ?? 0);
  $senderId  = intval($data['sender_id'] ?? 0);
  $message   = trim($data['message'] ?? '');

  if (!$message) {
    echo json_encode(["success" => false, "message" => "Message cannot be empty"]);
    exit;
  }

  // Verify access
  $chk = $conn->prepare(
    "SELECT b.id FROM bookings b
     JOIN dorms d ON b.dorm_id = d.id
     WHERE b.id = ? AND b.status = 'approved'
       AND (b.user_id = ? OR d.owner_id = ?)"
  );
  $chk->bind_param("iii", $bookingId, $senderId, $senderId);
  $chk->execute();
  if ($chk->get_result()->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Access denied"]);
    exit;
  }

  $stmt = $conn->prepare("INSERT INTO chat_messages (booking_id, sender_id, message) VALUES (?,?,?)");
  $stmt->bind_param("iis", $bookingId, $senderId, $message);
  $stmt->execute();
  $newMessageId = $conn->insert_id; // Capture before any other query resets it

  // Create notification for the other party
  $booking = $conn->query(
    "SELECT b.user_id as rentee_id, d.owner_id as renter_id, d.name as dorm_name
     FROM bookings b JOIN dorms d ON b.dorm_id = d.id WHERE b.id = $bookingId"
  )->fetch_assoc();

  if ($booking) {
    $recipientId = ($senderId == $booking['rentee_id']) ? $booking['renter_id'] : $booking['rentee_id'];
    $senderName = $conn->query("SELECT name FROM users WHERE id = $senderId")->fetch_assoc()['name'] ?? 'Someone';
    $notifMsg = "$senderName sent you a message about {$booking['dorm_name']}";
    $notifStmt = $conn->prepare(
      "INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'new_message', ?, ?)"
    );
    $notifStmt->bind_param("isi", $recipientId, $notifMsg, $newMessageId);
    $notifStmt->execute();
  }

  echo json_encode(["success" => true, "id" => $newMessageId]);
}

// GET list of approved bookings/chats for a user
if ($action === 'my_chats' && $method === 'GET') {
  $userId = intval($_GET['user_id'] ?? 0);

  $stmt = $conn->prepare(
    "SELECT
       b.id as booking_id,
       d.name as dorm_name,
       d.address,
       CASE WHEN b.user_id = ? THEN owner.name ELSE tenant.name END as other_user_name,
       CASE WHEN b.user_id = ? THEN owner.role ELSE tenant.role END as other_role,
       (SELECT message FROM chat_messages WHERE booking_id = b.id ORDER BY created_at DESC LIMIT 1) as last_message,
       (SELECT created_at FROM chat_messages WHERE booking_id = b.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
       (SELECT COUNT(*) FROM chat_messages WHERE booking_id = b.id AND sender_id != ? AND is_read = 0) as unread_count
     FROM bookings b
     JOIN dorms d ON b.dorm_id = d.id
     JOIN users owner ON d.owner_id = owner.id
     JOIN users tenant ON b.user_id = tenant.id
     WHERE b.status = 'approved' AND (b.user_id = ? OR d.owner_id = ?)
     ORDER BY last_message_time DESC"
  );
  $stmt->bind_param("iiiii", $userId, $userId, $userId, $userId, $userId);
  $stmt->execute();
  echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

// POST mark messages as read
if ($action === 'mark_read' && $method === 'POST') {
  $data      = getInput();
  $bookingId = intval($data['booking_id'] ?? 0);
  $userId    = intval($data['user_id'] ?? 0);

  $stmt = $conn->prepare(
    "UPDATE chat_messages SET is_read = 1 WHERE booking_id = ? AND sender_id != ?"
  );
  $stmt->bind_param("ii", $bookingId, $userId);
  $stmt->execute();
  echo json_encode(["success" => true]);
}
?>