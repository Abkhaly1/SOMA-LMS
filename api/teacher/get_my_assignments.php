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
$academicYearId = $_GET['academic_year'] ?? '2026';

try {
    // 1. Fetch Subject Teacher Assignments (1 Teacher -> Many Subjects/Classes) - Combines legacy and new allocation engines
    $stmtSubjects = $conn->prepare("
        SELECT class_name, subject_code, subject_name, level_code, total_students
        FROM (
            SELECT COALESCE(c.classroom_name, tsa.class_stream_id) as class_name, tsa.subject_code, COALESCE(sas.subject_name, tsa.subject_code) AS subject_name, sas.level_code,
                   (SELECT COUNT(DISTINCT sca.student_id) 
                    FROM student_classroom_allocations sca 
                    JOIN classrooms c2 ON sca.classroom_id = c2.id 
                    WHERE (c2.classroom_name = tsa.class_stream_id OR CAST(c2.id AS CHAR) = tsa.class_stream_id) AND sca.status = 'Active') AS total_students
            FROM teacher_subject_assignments tsa
            LEFT JOIN classrooms c ON (tsa.class_stream_id = c.classroom_name OR tsa.class_stream_id = CAST(c.id AS CHAR))
            LEFT JOIN school_approved_subjects sas ON (tsa.school_id = sas.school_id AND tsa.subject_code = sas.subject_code)
            WHERE tsa.teacher_id = :teacher_id AND tsa.academic_year_id = :year_id

            UNION DISTINCT

            SELECT c.classroom_name as class_name, s.code as subject_code, s.name as subject_name, COALESCE(sas.level_code, s.level_type, 'O-LEVEL') as level_code,
                   (SELECT COUNT(DISTINCT sca.student_id) 
                    FROM student_classroom_allocations sca 
                    WHERE sca.classroom_id = c.id AND sca.status = 'Active') AS total_students
            FROM teacher_classroom_assignments tca
            JOIN classrooms c ON tca.classroom_id = c.id
            JOIN subjects s ON tca.subject_id = s.id
            LEFT JOIN school_approved_subjects sas ON (sas.school_id = c.school_id AND sas.subject_code = s.code)
            WHERE tca.teacher_id = :teacher_id AND tca.academic_year_id = :year_id
        ) AS combined_allocations
        ORDER BY class_name ASC, subject_name ASC
    ");
    $stmtSubjects->execute([
        ':teacher_id' => $teacherId,
        ':year_id' => $academicYearId
    ]);
    $taughtSubjects = $stmtSubjects->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Form Master Managed Class Stream (Dual Role Check)
    $stmtFormMaster = $conn->prepare("
        SELECT COALESCE(c.classroom_name, ct.class_stream_id) as managed_class_name
        FROM class_teachers ct
        LEFT JOIN classrooms c ON (ct.class_stream_id = c.classroom_name OR ct.class_stream_id = CAST(c.id AS CHAR))
        WHERE ct.teacher_id = :teacher_id AND ct.academic_year_id = :year_id
        LIMIT 1
    ");
    $stmtFormMaster->execute([
        ':teacher_id' => $teacherId,
        ':year_id' => $academicYearId
    ]);
    $managedClass = $stmtFormMaster->fetch(PDO::FETCH_ASSOC);

    // 3. Class Detail Roster & Academic Progress Tracker (If class_name & subject_code requested)
    $classDetails = null;
    $targetClass   = $_GET['class_name'] ?? null;
    $targetSubject = $_GET['subject_code'] ?? null;

    if ($targetClass && $targetSubject) {
        $stmtRoster = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code, u.gender,
                   COALESCE(me.continuous_assessment_mark, 0) AS ca_mark,
                   COALESCE(me.terminal_mark, 0) AS terminal_mark,
                   (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) AS total_mark,
                   CASE 
                     WHEN (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) >= 75 THEN 'A (Excellent)'
                     WHEN (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) >= 65 THEN 'B (Very Good)'
                     WHEN (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) >= 45 THEN 'C (Good Pass)'
                     WHEN (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) >= 30 THEN 'D (Satisfactory)'
                     ELSE 'F (Fail / Needs Support)'
                   END AS progress_grade
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN marks_entry me ON (me.student_id = u.id AND me.subject_code = :subj AND me.academic_year = :year)
            WHERE (c.classroom_name = :cname OR CAST(c.id AS CHAR) = :cname) AND sca.academic_year = :year AND sca.status = 'Active'
            ORDER BY total_mark DESC, u.full_name ASC
        ");
        $stmtRoster->execute([':subj' => $targetSubject, ':year' => $academicYearId, ':cname' => $targetClass]);
        $classDetails = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        "success" => true,
        "teacher_id" => $teacherId,
        "academic_year" => $academicYearId,
        "is_form_master" => !empty($managedClass),
        "managed_class" => $managedClass ? $managedClass['managed_class_name'] : null,
        "taught_subjects" => $taughtSubjects,
        "class_details" => $classDetails
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
