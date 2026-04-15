<?php
require 'config.php';
$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

function parseImages($str) {
    if (!$str) return [];
    return array_values(array_filter(explode(",", $str)));
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

    $stmt = $conn->prepare("
        SELECT d.*, u.name as owner_name,
        (SELECT AVG(rating) FROM reviews WHERE dorm_id = d.id) as avg_rating,
        (SELECT COUNT(*) FROM reviews WHERE dorm_id = d.id) as review_count
        FROM dorms d
        JOIN users u ON d.owner_id = u.id
        WHERE d.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $dorm = $stmt->get_result()->fetch_assoc();

    if (!$dorm) {
        echo json_encode(["success" => false, "message" => "Dorm not found"]);
        exit;
    }

    $dorm['amenities']  = $dorm['amenities'] ? explode(",", $dorm['amenities']) : [];
    $dorm['images']     = parseImages($dorm['images'] ?? '');
    $dorm['avg_rating'] = $dorm['avg_rating'] ? round((float)$dorm['avg_rating'], 1) : 0;

    echo json_encode($dorm);
    exit;
}

// ============================================
// POST upload images (Linux compatible)
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

    $allowed  = ['image/jpeg', 'image/png', 'image/webp'];
    $uploaded = [];

    if (!empty($_FILES)) {
        foreach ($_FILES as $file) {
            $names = is_array($file['name']) ? $file['name'] : [$file['name']];
            $tmps  = is_array($file['tmp_name']) ? $file['tmp_name'] : [$file['tmp_name']];

            foreach ($names as $i => $name) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $filename = uniqid('dorm_', true) . '.' . $ext;
                if (move_uploaded_file($tmps[$i], $uploadDir . $filename)) {
                    $uploaded[] = 'api/uploads/dorms/' . $dormId . '/' . $filename;
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

    echo json_encode(["success" => true, "uploaded" => $uploaded]);
    exit;
}
?>
