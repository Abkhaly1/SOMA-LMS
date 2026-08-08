<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$school_id = $_GET['school_id'] ?? $_SESSION['school_id'] ?? null;

if (empty($school_id) && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $school_id = $row['id'] ?? null;
}

if (empty($school_id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "School ID is required."]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

try {
    $stmt = $conn->prepare("
        SELECT id, user_code, full_name, gender, email, phone, department, status, created_at 
        FROM users 
        WHERE role = 'teacher' AND school_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$school_id]);
    
    $teachers = $stmt->fetchAll();
    
    echo json_encode(["success" => true, "data" => $teachers]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error."]);
}
?>
