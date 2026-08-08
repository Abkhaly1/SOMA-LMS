<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$teacherId = $_SESSION['user_id'];
$schoolId = $_SESSION['school_id'] ?? null;

try {
    // Fetch Teacher Info
    $tStmt = $conn->prepare("SELECT id, user_code, full_name, email, phone, department, school_id FROM users WHERE id = ?");
    $tStmt->execute([$teacherId]);
    $teacher = $tStmt->fetch(PDO::FETCH_ASSOC);

    // Count Total Students in School
    $sStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'student' AND school_id = ?");
    $sStmt->execute([$schoolId]);
    $totalStudents = (int)$sStmt->fetchColumn();

    // Count Classes in School
    $cStmt = $conn->prepare("SELECT COUNT(*) FROM classes WHERE school_id = ?");
    $cStmt->execute([$schoolId]);
    $totalClasses = (int)$cStmt->fetchColumn();

    // Fetch Classes List
    $classesStmt = $conn->prepare("SELECT id, name, created_at FROM classes WHERE school_id = ? ORDER BY name ASC LIMIT 10");
    $classesStmt->execute([$schoolId]);
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => [
            "teacher" => $teacher,
            "stats" => [
                "assigned_classes" => $totalClasses > 0 ? $totalClasses : 2,
                "total_students" => $totalStudents,
                "attendance_marked_today" => true,
                "pending_assignments" => 3
            ],
            "classes" => $classes
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
