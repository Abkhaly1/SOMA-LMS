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
        SELECT u.id, u.user_code, u.full_name, u.gender, u.email, u.phone, u.status, u.created_at,
               COALESCE(clr.classroom_name, c.name, 'Unassigned') AS class_name 
        FROM users u
        LEFT JOIN student_classroom_allocations sca ON (u.id = sca.student_id AND sca.status = 'Active')
        LEFT JOIN classrooms clr ON sca.classroom_id = clr.id
        LEFT JOIN classes c ON u.class_id = c.id
        WHERE u.role = 'student' AND u.school_id = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$school_id]);
    
    $students = $stmt->fetchAll();
    
    echo json_encode(["success" => true, "data" => $students]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "System error."]);
}
?>
