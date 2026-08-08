<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$school_id = $_SESSION['school_id'] ?? $_GET['school_id'] ?? null;

if (!$school_id && $_SESSION['role'] === 'super_admin') {
    $stmt = $conn->query("SELECT id FROM schools LIMIT 1");
    $school_id = $stmt->fetchColumn();
}

try {
    $query = "SELECT id, full_name, phone, email, status, created_at FROM users WHERE role = 'parent'";
    $params = [];

    if ($school_id) {
        $query .= " AND school_id = ?";
        $params[] = $school_id;
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["success" => true, "data" => $parents]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
