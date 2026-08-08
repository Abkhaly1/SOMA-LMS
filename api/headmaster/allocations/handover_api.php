<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query("SELECT id FROM schools LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';
$year = $_GET['year'] ?? $input['year'] ?? '2026';

try {
    // 1. GET: Fetch Summary of Outgoing Teacher's Active Responsibilities
    if ($method === 'GET' && $action === 'teacher_summary') {
        $teacherId = trim($_GET['teacher_id'] ?? '');
        if (!$teacherId) {
            echo json_encode(['success' => false, 'message' => 'teacher_id is required.']);
            exit();
        }

        // Outgoing Teacher Info
        $stmtT = $conn->prepare("SELECT id, full_name, user_code, phone, email, department FROM users WHERE id = ? AND school_id = ?");
        $stmtT->execute([$teacherId, $schoolId]);
        $teacher = $stmtT->fetch(PDO::FETCH_ASSOC);

        if (!$teacher) {
            echo json_encode(['success' => false, 'message' => 'Teacher not found.']);
            exit();
        }

        // Class Guider Responsibilities
        $stmtG = $conn->prepare("
            SELECT ct.class_stream_id, c.classroom_name, g.name AS grade_name
            FROM class_teachers ct
            JOIN classrooms c ON ct.class_stream_id = c.id
            JOIN grades g ON c.grade_id = g.id
            WHERE ct.teacher_id = ? AND ct.school_id = ? AND ct.academic_year_id = ?
        ");
        $stmtG->execute([$teacherId, $schoolId, $year]);
        $guiderRoles = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        // Subject Teacher Responsibilities
        $stmtS = $conn->prepare("
            SELECT tsa.class_stream_id, tsa.subject_code, s.name AS subject_name,
                   c.classroom_name, g.name AS grade_name
            FROM teacher_subject_assignments tsa
            LEFT JOIN subjects s ON (tsa.subject_code = s.code OR tsa.subject_code = s.id)
            JOIN classrooms c ON tsa.class_stream_id = c.id
            JOIN grades g ON c.grade_id = g.id
            WHERE tsa.teacher_id = ? AND tsa.school_id = ? AND tsa.academic_year_id = ?
            ORDER BY g.order_seq, c.classroom_name
        ");
        $stmtS->execute([$teacherId, $schoolId, $year]);
        $subjectRoles = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subjectRoles as &$sr) {
            if (!$sr['subject_name']) $sr['subject_name'] = $sr['subject_code'];
        }

        echo json_encode([
            'success' => true,
            'teacher' => $teacher,
            'guider_roles' => $guiderRoles,
            'subject_roles' => $subjectRoles,
            'total_responsibilities' => count($guiderRoles) + count($subjectRoles)
        ]);
        exit();
    }

    // 2. POST: Perform Atomic Handover & Replacement
    if ($method === 'POST' && $action === 'execute_handover') {
        $outgoingId  = trim($input['outgoing_teacher_id'] ?? '');
        $replacementId = trim($input['replacement_teacher_id'] ?? '');

        if (!$outgoingId || !$replacementId) {
            echo json_encode(['success' => false, 'message' => 'outgoing_teacher_id and replacement_teacher_id are required.']);
            exit();
        }

        if ($outgoingId === $replacementId) {
            echo json_encode(['success' => false, 'message' => 'Outgoing and replacement teachers cannot be the same person.']);
            exit();
        }

        // Fetch Names
        $stmtOut = $conn->prepare("SELECT full_name FROM users WHERE id = ? AND school_id = ?");
        $stmtOut->execute([$outgoingId, $schoolId]);
        $outName = $stmtOut->fetchColumn();

        $stmtRep = $conn->prepare("SELECT full_name FROM users WHERE id = ? AND school_id = ?");
        $stmtRep->execute([$replacementId, $schoolId]);
        $repName = $stmtRep->fetchColumn();

        if (!$outName || !$repName) {
            echo json_encode(['success' => false, 'message' => 'Invalid teacher selected.']);
            exit();
        }

        $conn->beginTransaction();

        // 1. Transfer Class Guider roles
        $stmtG = $conn->prepare("
            UPDATE class_teachers
            SET teacher_id = ?
            WHERE school_id = ? AND academic_year_id = ? AND teacher_id = ?
        ");
        $stmtG->execute([$replacementId, $schoolId, $year, $outgoingId]);
        $guiderTransferred = $stmtG->rowCount();

        // 2. Transfer Subject Assignments
        $stmtS = $conn->prepare("
            UPDATE teacher_subject_assignments
            SET teacher_id = ?
            WHERE school_id = ? AND academic_year_id = ? AND teacher_id = ?
        ");
        $stmtS->execute([$replacementId, $schoolId, $year, $outgoingId]);
        $subjectsTransferred = $stmtS->rowCount();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'guider_transferred'  => $guiderTransferred,
            'subjects_transferred' => $subjectsTransferred,
            'message' => "Successfully transferred {$guiderTransferred} Class Guider role(s) and {$subjectsTransferred} Subject Period(s) from {$outName} to {$repName}."
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
