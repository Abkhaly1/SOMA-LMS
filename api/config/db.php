<?php
require_once __DIR__ . '/auth_guard.php';

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = '127.0.0.1';
$db_name = 'soma_lms';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection error."]);
    exit();
}
?>
