<?php
require 'config.php';
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

function parseImages($str) {
    if (!$str) return [];
    return array_values(array_filter(explode(",", $str)));
}

// ============================================
// GET all approved dorms (list)
// ============================================
if ($action === 'list' && $method === 'GET') {
    $university = $_GET['university'] ?? '';
    $minPrice   = $_GET['min_price'] ?? 0;
    $maxPrice   = $_GET['max_price'] ?? 999999;
    $available  = $_GET['available'] ?? '';
    $search     = $_GET['search'] ?? '';

    $sql = "SELECT d.*, u.name as owner_name,
            (SELECT AVG(rating) FROM reviews WHERE dorm_id = d.id) as avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE dorm_id = d.id) as review_count
            FROM dorms d
            JOIN users u ON d.owner_id = u.id
            WHERE d.status = 'approved' AND d.price BETWEEN ? AND ?";

    $params = "dd";
    $vals   = [(float)$minPrice, (float)$maxPrice];

    if ($university) {
        $sql .= " AND d.university = ?";
        $params .= "s";
        $vals[] = $university;
    }

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
// GET single dorm detail
// ============================================
if ($action === 'detail' && $method === 'GET') {
    $id = intval($_GET['id'] ?? 0);

    $stmt = $conn->prepare(
        "SELECT d.*, u.name as owner_name,
         (SELECT AVG(rating) FROM reviews WHERE dorm_id = d.id) as avg_rating,
         (SELECT COUNT(*) FROM reviews WHERE dorm_id = d.id) as review_count
         FROM dorms d
         JOIN users u ON d.owner_id = u.id
         WHERE d.id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();

    if (!$r) {
        echo json_encode(["success" => false, "message" => "Dorm not found"]);
        exit;
    }

    $r['amenities']  = $r['amenities'] ? explode(",", $r['amenities']) : [];
    $r['images']     = parseImages($r['images'] ?? '');
    $r['avg_rating'] = $r['avg_rating'] ? round((float)$r['avg_rating'], 1) : 0;

    // Get reviews
    $rs = $conn->prepare(
        "SELECT r.*, u.name as reviewer_name FROM reviews r
         JOIN users u ON r.user_id = u.id
         WHERE r.dorm_id = ? ORDER BY r.id DESC"
    );
    $rs->bind_param("i", $id);
    $rs->execute();
    $r['reviews'] = $rs->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode($r);
    exit;
}

// ============================================
// POST add new dorm listing (pending approval)
// ============================================
if ($action === 'add' && $method === 'POST') {
    $data        = getInput();
    $ownerId     = intval($data['owner_id'] ?? 0);
    $name        = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $address     = $data['address'] ?? '';
    $university  = $data['university'] ?? '';
    $price       = floatval($data['price'] ?? 0);
    $lat         = floatval($data['lat'] ?? 0);
    $lng         = floatval($data['lng'] ?? 0);
    $availability = intval($data['availability'] ?? 0);
    $totalSlots  = intval($data['total_slots'] ?? $availability);
    $amenities   = isset($data['amenities']) && is_array($data['amenities'])
                    ? implode(",", $data['amenities'])
                    : ($data['amenities'] ?? '');

    if (!$ownerId || !$name || !$address || !$price) {
        echo json_encode(["success" => false, "message" => "Missing required fields"]);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO dorms (owner_id, name, description, address, university, price, lat, lng, availability, total_slots, amenities, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
    );
    $stmt->bind_param(
        "issssddiiss",
        $ownerId, $name, $description, $address, $university,
        $price, $lat, $lng, $availability, $totalSlots, $amenities
    );

    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "DB error: " . $stmt->error]);
        exit;
    }

    $dormId = $conn->insert_id;
    logActivity($conn, $ownerId, "dorm", "Submitted new listing: $name");

    // Notify all admins
    $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'")->fetch_all(MYSQLI_ASSOC);
    foreach ($admins as $admin) {
        $notifMsg = "New dorm listing \"$name\" submitted for approval.";
        $ns = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'new_listing', ?, ?)");
        $ns->bind_param("isi", $admin['id'], $notifMsg, $dormId);
        $ns->execute();
    }

    echo json_encode(["success" => true, "id" => $dormId]);
    exit;
}

// ============================================
// POST edit dorm
// ============================================
if ($action === 'edit' && $method === 'POST') {
    $data        = getInput();
    $id          = intval($data['id'] ?? 0);
    $userId      = intval($data['user_id'] ?? 0);
    $userRole    = $data['user_role'] ?? '';
    $description = $data['description'] ?? '';
    $availability = intval($data['availability'] ?? 0);
    $amenities   = isset($data['amenities']) && is_array($data['amenities'])
                    ? implode(",", $data['amenities'])
                    : ($data['amenities'] ?? '');

    // Verify ownership or admin
    $dorm = $conn->query("SELECT owner_id FROM dorms WHERE id = $id")->fetch_assoc();
    if (!$dorm) {
        echo json_encode(["success" => false, "message" => "Dorm not found"]);
        exit;
    }
    if ($userRole !== 'admin' && $dorm['owner_id'] != $userId) {
        echo json_encode(["success" => false, "message" => "Permission denied"]);
        exit;
    }

    if ($userRole === 'admin') {
        // Admin can edit all fields
        $name       = $data['name'] ?? '';
        $address    = $data['address'] ?? '';
        $university = $data['university'] ?? '';
        $price      = floatval($data['price'] ?? 0);
        $totalSlots = intval($data['total_slots'] ?? 0);

        $stmt = $conn->prepare(
            "UPDATE dorms SET name=?, address=?, university=?, price=?, total_slots=?,
             description=?, availability=?, amenities=? WHERE id=?"
        );
        $stmt->bind_param("sssdiissi", $name, $address, $university, $price, $totalSlots, $description, $availability, $amenities, $id);
    } else {
        // Owner can only edit description, availability, amenities
        $stmt = $conn->prepare(
            "UPDATE dorms SET description=?, availability=?, amenities=? WHERE id=?"
        );
        $stmt->bind_param("sisi", $description, $availability, $amenities, $id);
    }

    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "message" => "DB error: " . $stmt->error]);
        exit;
    }

    logActivity($conn, $userId, "dorm", "Edited dorm #$id");
    echo json_encode(["success" => true]);
    exit;
}

// ============================================
// POST delete dorm
// ============================================
if ($action === 'delete' && $method === 'POST') {
    $data    = getInput();
    $id      = intval($data['id'] ?? 0);
    $userId  = intval($data['user_id'] ?? 0);

    $dorm = $conn->query("SELECT owner_id, name FROM dorms WHERE id = $id")->fetch_assoc();
    if (!$dorm) {
        echo json_encode(["success" => false, "message" => "Dorm not found"]);
        exit;
    }

    // Only owner or admin can delete
    $user = $conn->query("SELECT role FROM users WHERE id = $userId")->fetch_assoc();
    if ($dorm['owner_id'] != $userId && ($user['role'] ?? '') !== 'admin') {
        echo json_encode(["success" => false, "message" => "Permission denied"]);
        exit;
    }

    $conn->query("DELETE FROM dorms WHERE id = $id");
    logActivity($conn, $userId, "dorm", "Deleted dorm: " . $dorm['name']);
    echo json_encode(["success" => true]);
    exit;
}

// ============================================
// POST approve dorm (admin only)
// ============================================
if ($action === 'approve' && $method === 'POST') {
    $data   = getInput();
    $id     = intval($data['id'] ?? 0);
    $userId = intval($data['user_id'] ?? 0);

    $dorm = $conn->query("SELECT owner_id, name FROM dorms WHERE id = $id")->fetch_assoc();
    if (!$dorm) {
        echo json_encode(["success" => false, "message" => "Dorm not found"]);
        exit;
    }

    $conn->query("UPDATE dorms SET status = 'approved' WHERE id = $id");
    logActivity($conn, $userId, "admin", "Approved dorm #$id: " . $dorm['name']);

    // Notify the owner
    $msg = "🎉 Your listing \"{$dorm['name']}\" has been approved and is now live!";
    $ns  = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'listing_approved', ?, ?)");
    $ns->bind_param("isi", $dorm['owner_id'], $msg, $id);
    $ns->execute();

    echo json_encode(["success" => true]);
    exit;
}

// ============================================
// POST reject dorm (admin only)
// ============================================
if ($action === 'reject' && $method === 'POST') {
    $data   = getInput();
    $id     = intval($data['id'] ?? 0);
    $userId = intval($data['user_id'] ?? 0);

    $dorm = $conn->query("SELECT owner_id, name FROM dorms WHERE id = $id")->fetch_assoc();
    if (!$dorm) {
        echo json_encode(["success" => false, "message" => "Dorm not found"]);
        exit;
    }

    $conn->query("UPDATE dorms SET status = 'rejected' WHERE id = $id");
    logActivity($conn, $userId, "admin", "Rejected dorm #$id: " . $dorm['name']);

    // Notify the owner
    $msg = "Your listing \"{$dorm['name']}\" was not approved. Please review and resubmit.";
    $ns  = $conn->prepare("INSERT INTO notifications (user_id, type, message, related_id) VALUES (?, 'listing_rejected', ?, ?)");
    $ns->bind_param("isi", $dorm['owner_id'], $msg, $id);
    $ns->execute();

    echo json_encode(["success" => true]);
    exit;
}

// ============================================
// POST upload images (Linux/Railway compatible)
// ============================================
if ($action === 'upload_images' && $method === 'POST') {
    $dormId = intval($_POST['dorm_id'] ?? 0);
    $userId = intval($_POST['user_id'] ?? 0);

    $dorm = $conn->query("SELECT owner_id, images FROM dorms WHERE id = $dormId")->fetch_assoc();
    if (!$dorm || $dorm['owner_id'] != $userId) {
        echo json_encode(["success" => false, "message" => "Permission denied"]);
        exit;
    }

    $uploadDir = __DIR__ . '/uploads/dorms/' . $dormId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $uploaded = [];
    $errors   = [];

    if (!empty($_FILES)) {
        foreach ($_FILES as $file) {
            $names = is_array($file['name'])     ? $file['name']     : [$file['name']];
            $tmps  = is_array($file['tmp_name']) ? $file['tmp_name'] : [$file['tmp_name']];
            $types = is_array($file['type'])     ? $file['type']     : [$file['type']];

            foreach ($names as $i => $name) {
                $mime = $types[$i] ?? '';
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
                    $errors[] = "$name: invalid type";
                    continue;
                }
                $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $filename = uniqid('dorm_', true) . '.' . $ext;
                if (move_uploaded_file($tmps[$i], $uploadDir . $filename)) {
                    $uploaded[] = 'api/uploads/dorms/' . $dormId . '/' . $filename;
                } else {
                    $errors[] = "$name: upload failed";
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
    }

    echo json_encode(["success" => true, "uploaded" => $uploaded, "errors" => $errors]);
    exit;
}

// ============================================
// POST delete a single image
// ============================================
if ($action === 'delete_image' && $method === 'POST') {
    $data     = getInput();
    $dormId   = intval($data['dorm_id'] ?? 0);
    $userId   = intval($data['user_id'] ?? 0);
    $userRole = $data['user_role'] ?? '';
    $imgPath  = $data['image'] ?? '';

    $dorm = $conn->query("SELECT owner_id, images FROM dorms WHERE id = $dormId")->fetch_assoc();
    if (!$dorm) {
        echo json_encode(["success" => false, "message" => "Dorm not found"]);
        exit;
    }
    if ($userRole !== 'admin' && $dorm['owner_id'] != $userId) {
        echo json_encode(["success" => false, "message" => "Permission denied"]);
        exit;
    }

    // Remove from DB
    $images  = parseImages($dorm['images'] ?? '');
    $images  = array_values(array_filter($images, fn($img) => $img !== $imgPath));
    $newImgs = implode(",", $images);
    $stmt    = $conn->prepare("UPDATE dorms SET images = ? WHERE id = ?");
    $stmt->bind_param("si", $newImgs, $dormId);
    $stmt->execute();

    // Delete file from disk
    $filePath = __DIR__ . '/../' . $imgPath;
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    echo json_encode(["success" => true]);
    exit;
}
