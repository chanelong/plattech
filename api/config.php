<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Dynamically pull connection details from Railway environment variables
// If the variables aren't found, it falls back to your current hardcoded values
$host = getenv('MYSQLHOST') ?: "mysql.railway.internal";
$port = getenv('MYSQLPORT') ?: 3306;
$user = getenv('MYSQLUSER') ?: "root";
$pass = getenv('MYSQLPASSWORD') ?: "dxwtczmsRQevehKwkWubwFcGmBymdvVx";
$db   = getenv('MYSQLDATABASE') ?: "railway";

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    http_response_code(500);
    // Returning the error as JSON so your frontend can read it
    die(json_encode([
        "success" => false, 
        "message" => "DB connection failed: " . $conn->connect_error
    ]));
}

$conn->set_charset("utf8mb4");

/**
 * Logs user activity into the 'logs' table
 */
function logActivity($conn, $userId, $action, $desc) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // Ensure the 'logs' table exists in your MySQL dashboard!
    $stmt = $conn->prepare("INSERT INTO logs (user_id, action, description, ip_address) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $userId, $action, $desc, $ip);
    $stmt->execute();
}

/**
 * Gets JSON input from the request body
 */
function getInput() {
    return json_decode(file_get_contents("php://input"), true) ?? [];
}
?>