<?php
require 'config.php';
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

// Helper: parse images CSV into array
function parseImages($str) {
  if (!$str) return [];
  return array_values(array_filter(explode(",", $str)));
}

// ============================================
// POST upload images for a dorm
// ============================================
if ($action === 'upload_images' && $method === 'POST') {
  $dormId = intval($_POST['dorm_id'] ?? 0);
  $userId = intval($_POST['user_id'] ?? 0);

  if (!$dormId || !$userId) {
    echo json_encode(["success" => false, "message" => "Missing dorm_id or user_id"]);
    exit;
  }

  $dorm = $conn->query("SELECT owner_id, images FROM dorms WHERE id = $dormId")->fetch_assoc();
  if (!$dorm || $dorm['owner_id'] != $userId) {
    echo json_encode(["success" => false, "message" => "Permission denied"]);
    exit;
  }

  $uploadDir = __DIR__ . '/uploads/dorms/' . $dormId . '/';
  if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
      echo json_encode(["success" => false, "message" => "Cannot create upload folder at: $uploadDir — make sure api/uploads/dorms/ exists and is writable."]);
      exit;
    }
  }
  if (!is_writable($uploadDir)) {
    echo json_encode(["success" => false, "message" => "Upload folder is not writable: $uploadDir — on Windows/XAMPP right-click the uploads folder → Properties → Security → give Write permission."]);
    exit;
  }

  $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  $maxSize  = 5 * 1024 * 1024; // 5MB per file
  $uploaded = [];
  $errors   = [];

  if (!empty($_FILES)) {
    foreach ($_FILES as $file) {
      // Handle both single and multiple file inputs
      $names = is_array($file['name']) ? $file['name'] : [$file['name']];
      $tmps  = is_array($file['tmp_name']) ? $file['tmp_name'] : [$file['tmp_name']];
      $sizes = is_array($file['size']) ? $file['size'] : [$file['size']];
      $types = is_array($file['type']) ? $file['type'] : [$file['type']];
      $errs  = is_array($file['error']) ? $file['error'] : [$file['error']];

      foreach ($names as $i => $name) {
        if ($errs[$i] !== UPLOAD_ERR_OK) { $errors[] = "Upload error: $name"; continue; }
        if ($sizes[$i] > $maxSize)        { $errors[] = "$name exceeds 5MB limit"; continue; }
        if (!in_array($types[$i], $allowed)) { $errors[] = "$name is not a valid image type"; continue; }

        $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $filename = uniqid('dorm_', true) . '.' . $ext;
        if (move_uploaded_file($tmps[$i], $uploadDir . $filename)) {
          $uploaded[] = 'api/uploads/dorms/' . $dormId . '/' . $filename;
        } else {
          $errors[] = "Could not save $name";
        }
      }
    }
  }

  if (!empty($uploaded)) {
    $existing = parseImages($dorm['images'] ?? '');
    $all      = implode(",", array_merge($existing, $uploaded));
    $stmt     = $conn->prepare("UPDATE dorms SET images = ? WHERE id = ?");
    $stmt->bind_param("si", $all, $dormId);
    $stmt->execute();
    logActivity($conn, $userId, "upload_images", "Uploaded " . count($uploaded) . " image(s) for dorm #$dormId");
  }

  echo json_encode([
    "success"  => count($uploaded) > 0,
    "uploaded" => $uploaded,
    "errors"   => $errors,
    "message"  => count($uploaded) . " image(s) uploaded successfully" . (!empty($errors) ? "; " . implode("; ", $errors) : "")
  ]);
  exit;
}

// ============================================
// POST delete a single dorm image
// ============================================
if ($action === 'delete_image' && $method === 'POST') {
  $data     = getInput();
  $dormId   = intval($data['dorm_id'] ?? 0);
  $userId   = intval($data['user_id'] ?? 0);
  $userRole = $data['user_role'] ?? '';
  $img      = $data['image'] ?? '';

  $dorm = $conn->query("SELECT owner_id, images FROM dorms WHERE id = $dormId")->fetch_assoc();
  if (!$dorm || ($dorm['owner_id'] != $userId && $userRole !== 'admin')) {
    echo json_encode(["success" => false, "message" => "Permission denied"]);
    exit;
  }

  $images = array_filter(parseImages($dorm['images']), fn($i) => $i !== $img);
  $imgStr = implode(",", array_values($images));
  $stmt   = $conn->prepare("UPDATE dorms SET images = ? WHERE id = ?");
  $stmt->bind_param("si", $imgStr, $dormId);
  $stmt->execute();

  // Delete physical file
  $filePath = __DIR__ . '/' . ltrim(str_replace('api/', '', $img), '/');
  if (file_exists($filePath)) unlink($filePath);

  echo json_encode(["success" => true]);
  exit;
}

// ============================================
// GET all approved dorms
// ============================================
if ($action === 'list' && $method === 'GET') {
  $university = $_GET['university'] ?? '';
  $minPrice   = $_GET['min_price'] ?? 0;
  $maxPrice   = $_GET['max_price'] ?? 999999;
  $available  = $_GET['available'] ?? '';
  $search     = $_GET['search'] ?? '';

  $sql    = "SELECT d.*, u.name as owner_name,
             (SELECT AVG(rating) FROM reviews WHERE dorm_id = d.id) as avg_rating,
             (SELECT COUNT(*) FROM reviews WHERE dorm_id = d.id) as review_count
             FROM dorms d JOIN users u ON d.owner_id = u.id
             WHERE d.status = 'approved' AND d.price BETWEEN ? AND ?";
  $params = "dd";
  $vals   = [$minPrice, $maxPrice];

  if ($university) { $sql .= " AND d.university = ?"; $params .= "s"; $vals[] = $university; }
  if ($available === 'yes') $sql .= " AND d.availability > 0";
  if ($available === 'no')  $sql .= " AND d.availability = 0";
  if ($search) {
    $sql    .= " AND (d.name LIKE ? OR d.address LIKE ? OR d.university LIKE ?)";
    $params .= "sss";
    $s       = "%$search%";
    $vals    = array_merge($vals, [$s, $s, $s]);
  }
  $sql .= " ORDER BY d.created_at DESC";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param($params, ...$vals);
  $stmt->execute();
  $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  foreach ($rows as &$r) {
    $r['amenities']  = $r['amenities'] ? explode(",", $r['amenities']) : [];
    $r['images']     = parseImages($r['images'] ?? '');
    $r['avg_rating'] = $r['avg_rating'] ? round((float)$r['avg_rating'], 1) : 0;
  }
  echo json_encode($rows);
  exit;
}

// ============================================
// GET single dorm
// ============================================
if ($action === 'get' && $method === 'GET') {
  $id   = intval($_GET['id'] ?? 0);
  $stmt = $conn->prepare("SELECT d.*, u.name as owner_name FROM dorms d JOIN users u ON d.owner_id = u.id WHERE d.id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $dorm = $stmt->get_result()->fetch_assoc();
  if ($dorm) {
    $dorm['amenities'] = $dorm['amenities'] ? explode(",", $dorm['amenities']) : [];
    $dorm['images']    = parseImages($dorm['images'] ?? '');
    echo json_encode($dorm);
  } else {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Dorm not found"]);
  }
  exit;
}

// ============================================
// POST add dorm (images uploaded separately after)
// ============================================
if ($action === 'add' && $method === 'POST') {
  $data      = getInput();
  $amenities = implode(",", $data['amenities'] ?? []);
  $stmt      = $conn->prepare(
    "INSERT INTO dorms (owner_id,name,description,address,university,price,lat,lng,availability,total_slots,amenities)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)"
  );
  $stmt->bind_param(
    "isssssddiis",
    $data['owner_id'], $data['name'], $data['description'], $data['address'],
    $data['university'], $data['price'], $data['lat'], $data['lng'],
    $data['availability'], $data['total_slots'], $amenities
  );
  $stmt->execute();
  $newId = $conn->insert_id;
  logActivity($conn, $data['owner_id'], "create_dorm", "Created listing: {$data['name']}");
  echo json_encode(["success" => true, "id" => $newId]);
  exit;
}

// ============================================
// POST edit dorm
// ============================================
if ($action === 'edit' && $method === 'POST') {
  $data     = getInput();
  $id       = intval($data['id'] ?? 0);
  $userId   = intval($data['user_id'] ?? 0);
  $userRole = $data['user_role'] ?? 'rentee';

  $dorm = $conn->query("SELECT owner_id, name FROM dorms WHERE id = $id")->fetch_assoc();
  if (!$dorm) { echo json_encode(["success" => false, "message" => "Dorm not found"]); exit; }
  if ($userRole !== 'admin' && $dorm['owner_id'] != $userId) {
    echo json_encode(["success" => false, "message" => "Permission denied"]); exit;
  }

  $amenities = implode(",", $data['amenities'] ?? []);
  if ($userRole === 'admin') {
    $stmt = $conn->prepare(
      "UPDATE dorms SET name=?,description=?,address=?,university=?,price=?,availability=?,total_slots=?,amenities=? WHERE id=?"
    );
    $stmt->bind_param("ssssdiiisi",
      $data['name'], $data['description'], $data['address'], $data['university'],
      $data['price'], $data['availability'], $data['total_slots'], $amenities, $id
    );
  } else {
    $stmt = $conn->prepare("UPDATE dorms SET description=?,availability=?,amenities=? WHERE id=? AND owner_id=?");
    $stmt->bind_param("siisi", $data['description'], $data['availability'], $amenities, $id, $userId);
  }
  $stmt->execute();
  logActivity($conn, $userId, "edit_dorm", "Edited dorm #{$id}: {$dorm['name']}");
  echo json_encode(["success" => true]);
  exit;
}

// ============================================
// POST delete dorm
// ============================================
if ($action === 'delete' && $method === 'POST') {
  $data   = getInput();
  $id     = intval($data['id'] ?? 0);
  $userId = intval($data['user_id'] ?? 0);

  // Delete uploads folder for this dorm
  $uploadDir = __DIR__ . '/uploads/dorms/' . $id . '/';
  if (is_dir($uploadDir)) {
    array_map('unlink', glob("$uploadDir*"));
    rmdir($uploadDir);
  }

  $stmt = $conn->prepare("DELETE FROM dorms WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  logActivity($conn, $userId, "delete_dorm", "Deleted dorm #$id");
  echo json_encode(["success" => true]);
  exit;
}

// ============================================
// POST approve dorm (admin)
// ============================================
if ($action === 'approve' && $method === 'POST') {
  $data   = getInput();
  $id     = intval($data['id'] ?? 0);
  $userId = intval($data['user_id'] ?? 0);

  $dorm = $conn->query("SELECT owner_id, name FROM dorms WHERE id = $id")->fetch_assoc();
  $stmt = $conn->prepare("UPDATE dorms SET status = 'approved' WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  logActivity($conn, $userId, "approve_dorm", "Approved dorm #$id");

  if ($dorm) {
    $notifMsg = "🎉 Your listing \"{$dorm['name']}\" has been approved and is now live on dormMNL!";
    $ns = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'dorm_approved', ?, ?)");
    $ns->bind_param("isi", $dorm['owner_id'], $notifMsg, $id);
    $ns->execute();
  }
  echo json_encode(["success" => true]);
  exit;
}

// ============================================
// POST reject dorm (admin)
// ============================================
if ($action === 'reject' && $method === 'POST') {
  $data   = getInput();
  $id     = intval($data['id'] ?? 0);
  $userId = intval($data['user_id'] ?? 0);

  $dorm = $conn->query("SELECT owner_id, name FROM dorms WHERE id = $id")->fetch_assoc();
  $stmt = $conn->prepare("UPDATE dorms SET status = 'rejected' WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  logActivity($conn, $userId, "reject_dorm", "Rejected dorm #$id");

  if ($dorm) {
    $notifMsg = "Your listing \"{$dorm['name']}\" was not approved. Please contact admin for more details.";
    $ns = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'dorm_rejected', ?, ?)");
    $ns->bind_param("isi", $dorm['owner_id'], $notifMsg, $id);
    $ns->execute();
  }
  echo json_encode(["success" => true]);
  exit;
}