<?php
header("Content-Type: application/json");

$host = getenv('MYSQLHOST');
$port = getenv('MYSQLPORT');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQLDATABASE');

echo json_encode([
    "MYSQLHOST"     => $host ?: "NOT SET",
    "MYSQLPORT"     => $port ?: "NOT SET",
    "MYSQLUSER"     => $user ?: "NOT SET",
    "MYSQLPASSWORD" => $pass ? "SET (hidden)" : "NOT SET",
    "MYSQLDATABASE" => $db   ?: "NOT SET",
]);

// Try connecting
$conn = new mysqli($host, $user, $pass, $db, (int)$port);

if ($conn->connect_error) {
    echo json_encode([
        "connection" => "FAILED",
        "error" => $conn->connect_error
    ]);
} else {
    echo json_encode([
        "connection" => "SUCCESS"
    ]);
}
?>