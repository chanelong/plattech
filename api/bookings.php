<?php
require 'config.php';
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// GET bookings
if ($action === 'list' && $method === 'GET') {
  $userId  = intval($_GET['user_id'] ?? 0);
  $ownerId = intval($_GET['owner_id'] ?? 0);

  if ($userId) {
    $stmt = $conn->prepare(
      "SELECT b.*, d.name as dorm_name, d.address, d.price, u2.name as owner_name
       FROM bookings b
       JOIN dorms d ON b.dorm_id = d.id
       JOIN users u2 ON d.owner_id = u2.id
       WHERE b.user_id = ? ORDER BY b.id DESC"
    );
    $stmt->bind_param("i", $userId);
  } elseif ($ownerId) {
    $stmt = $conn->prepare(
      "SELECT b.*, d.name as dorm_name, u.name as student_name, u.email as student_email
       FROM bookings b
       JOIN dorms d ON b.dorm_id = d.id
       JOIN users u ON b.user_id = u.id
       WHERE d.owner_id = ? ORDER BY b.id DESC"
    );
    $stmt->bind_param("i", $ownerId);
  } else {
    $stmt = $conn->prepare(
      "SELECT b.*, d.name as dorm_name, u.name as student_name FROM bookings b
       JOIN dorms d ON b.dorm_id = d.id JOIN users u ON b.user_id = u.id ORDER BY b.id DESC"
    );
  }
  $stmt->execute();
  echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

// POST create booking
if ($action === 'add' && $method === 'POST') {
  $data   = getInput();
  $userId = intval($data['user_id'] ?? 0);
  $dormId = intval($data['dorm_id'] ?? 0);
  $msg    = $data['message'] ?? '';
  $date   = date("Y-m-d");

  // One active booking per user across ALL dorms (pending or approved)
  $chk = $conn->prepare(
    "SELECT b.id, d.name as dorm_name FROM bookings b
     JOIN dorms d ON b.dorm_id = d.id
     WHERE b.user_id = ? AND b.status IN ('pending', 'approved')"
  );
  $chk->bind_param("i", $userId);
  $chk->execute();
  $existing = $chk->get_result()->fetch_assoc();
  if ($existing) {
    echo json_encode([
      "success" => false,
      "message" => "You already have an active booking for \"{$existing['dorm_name']}\". You can only be a resident of one dorm at a time. Please vacate or wait for your current booking to be resolved first."
    ]);
    exit;
  }

  $stmt = $conn->prepare("INSERT INTO bookings (user_id, dorm_id, message, date) VALUES (?,?,?,?)");
  $stmt->bind_param("iiss", $userId, $dormId, $msg, $date);
  $stmt->execute();
  $bookingId = $conn->insert_id;

  $d = $conn->query("SELECT name, owner_id FROM dorms WHERE id = $dormId")->fetch_assoc();
  $u = $conn->query("SELECT name FROM users WHERE id = $userId")->fetch_assoc();
  logActivity($conn, $userId, "booking", "Booked dorm: " . ($d['name'] ?? "#$dormId"));

  // Notify the dorm owner
  if ($d && $u) {
    $notifMsg = "{$u['name']} applied to rent {$d['name']}.";
    $ns = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'new_booking', ?, ?)");
    $ns->bind_param("isi", $d['owner_id'], $notifMsg, $bookingId);
    $ns->execute();
  }

  echo json_encode(["success" => true, "id" => $bookingId]);
}

// POST update booking status
if ($action === 'update' && $method === 'POST') {
  $data    = getInput();
  $id      = intval($data['id'] ?? 0);
  $status  = $data['status'] ?? 'pending';
  $ownerId = intval($data['owner_id'] ?? 0);

  if (!in_array($status, ['approved', 'rejected', 'pending', 'vacated'])) {
    echo json_encode(["success" => false, "message" => "Invalid status"]);
    exit;
  }

  // Get current booking info before update
  $b = $conn->query(
    "SELECT b.*, d.name as dorm_name, d.owner_id, u.name as rentee_name
     FROM bookings b
     JOIN dorms d ON b.dorm_id = d.id
     JOIN users u ON b.user_id = u.id
     WHERE b.id = $id"
  )->fetch_assoc();

  if (!$b) {
    echo json_encode(["success" => false, "message" => "Booking not found"]);
    exit;
  }

  $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
  $stmt->bind_param("si", $status, $id);
  $stmt->execute();

  if ($status === 'approved') {
    // Decrease availability
    $conn->query("UPDATE dorms SET availability = availability - 1 WHERE id = {$b['dorm_id']} AND availability > 0");
    logActivity($conn, $ownerId, "booking_update", "Approved booking #{$id} for {$b['dorm_name']}");

    // Notify the rentee
    $msg = "🎉 Your booking for {$b['dorm_name']} has been approved! You can now chat with the owner.";
    $ns  = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'booking_approved', ?, ?)");
    $ns->bind_param("isi", $b['user_id'], $msg, $id);
    $ns->execute();

    // Notify owner confirmation
    $ownerMsg = "You approved {$b['rentee_name']}'s booking for {$b['dorm_name']}.";
    $no = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'booking_confirmed', ?, ?)");
    $no->bind_param("isi", $b['owner_id'], $ownerMsg, $id);
    $no->execute();

  } elseif ($status === 'rejected') {
    logActivity($conn, $ownerId, "booking_update", "Rejected booking #{$id} for {$b['dorm_name']}");

    // Notify rentee of rejection
    $msg = "Your booking request for {$b['dorm_name']} was not accepted. You may apply to other dorms.";
    $ns  = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'booking_rejected', ?, ?)");
    $ns->bind_param("isi", $b['user_id'], $msg, $id);
    $ns->execute();

  } elseif ($status === 'vacated') {
    // Increase availability when rentee vacates
    $vacateDate = date("Y-m-d");
    $conn->query("UPDATE bookings SET vacate_date = '$vacateDate' WHERE id = $id");
    $conn->query("UPDATE dorms SET availability = availability + 1 WHERE id = {$b['dorm_id']} AND availability < total_slots");
    logActivity($conn, $b['user_id'], "vacated", "{$b['rentee_name']} vacated {$b['dorm_name']}");

    // Notify owner
    $ownerMsg = "{$b['rentee_name']} has vacated their room at {$b['dorm_name']}. 1 slot is now available.";
    $no = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'tenant_vacated', ?, ?)");
    $no->bind_param("isi", $b['owner_id'], $ownerMsg, $id);
    $no->execute();

    // Notify rentee confirmation
    $renteeMsg = "You have successfully vacated {$b['dorm_name']}. Thank you for staying with us!";
    $nr = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'vacated_confirmed', ?, ?)");
    $nr->bind_param("isi", $b['user_id'], $renteeMsg, $id);
    $nr->execute();
  }

  echo json_encode(["success" => true]);
}