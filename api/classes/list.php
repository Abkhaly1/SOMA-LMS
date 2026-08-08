<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant_admin' || empty($_SESSION['school_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT id, name, created_at 
        FROM classes 
        WHERE school_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['school_id']]);
    
    $classes = $stmt->fetchAll();
    
    echo json_encode(["success" => true, "data" => $classes]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error: " . $e->getMessage()]);
}
?>
