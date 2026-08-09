<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query('SELECT id FROM schools LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';
$year = $_GET['year'] ?? $input['year'] ?? '2026';
$term = $_GET['term'] ?? $input['term'] ?? 'Term 1';

try {
    // 1. INDIVIDUAL 360° STUDENT PROGRESS REPORT CARD (TASK 4.1)
    if ($action === 'student_report_card') {
        $studentId = $_GET['student_id'] ?? $input['student_id'] ?? '';
        if (empty($studentId)) {
            echo json_encode(['success' => false, 'message' => 'student_id is required.']);
            exit();
        }

        // Student details & classroom
        $stmtStudent = $conn->prepare("
            SELECT u.id, u.full_name, u.user_code, u.gender, c.classroom_name, c.id AS classroom_id, g.name AS grade_name, el.name AS level_type
            FROM users u
            LEFT JOIN student_classroom_allocations sca ON (sca.student_id = u.id AND sca.academic_year = ?)
            LEFT JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN grades g ON c.grade_id = g.id
            LEFT JOIN education_levels el ON g.level_id = el.id
            WHERE u.id = ? AND u.school_id = ?
            LIMIT 1
        ");
        $stmtStudent->execute([$year, $studentId, $schoolId]);
        $student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student record not found.']);
            exit();
        }

        $levelType = $student['level_type'] ?: 'O-Level';

        // Fetch grading scale rules
        $stmtScales = $conn->prepare("SELECT min_mark, max_mark, grade, remark, points FROM grading_scales WHERE level_type = ? ORDER BY min_mark DESC");
        $stmtScales->execute([$levelType]);
        $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all subject marks for student
        $stmtMarks = $conn->prepare("
            SELECT me.subject_code, s.subject_name,
                   COALESCE(me.continuous_assessment_mark, 0) AS ca_mark,
                   COALESCE(me.terminal_mark, 0) AS terminal_mark
            FROM marks_entry me
            LEFT JOIN subjects s ON me.subject_code = s.subject_code
            WHERE me.school_id = ? AND me.student_id = ? AND me.academic_year = ? AND me.term = ?
            ORDER BY me.subject_code ASC
        ");
        $stmtMarks->execute([$schoolId, $studentId, $year, $term]);
        $subjectMarks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

        $totalPoints = 0;
        $totalScores = 0;
        $subjectCount = count($subjectMarks);

        foreach ($subjectMarks as &$m) {
            $total = floatval($m['ca_mark']) + floatval($m['terminal_mark']);
            $m['total_score'] = round($total, 2);
            $totalScores += $total;

            $mGrade = 'F';
            $mRemark = 'Fail';
            $mPoints = 7;

            foreach ($scales as $sc) {
                if ($total >= floatval($sc['min_mark']) && $total <= floatval($sc['max_mark'])) {
                    $mGrade = $sc['grade'];
                    $mRemark = $sc['remark'];
                    $mPoints = intval($sc['points']);
                    break;
                }
            }

            $m['grade'] = $mGrade;
            $m['remark'] = $mRemark;
            $m['points'] = $mPoints;
            $totalPoints += $mPoints;
        }

        // Calculate division result from division_scales
        $stmtDiv = $conn->prepare("SELECT division_name, remark FROM division_scales WHERE level_type = ? AND ? BETWEEN min_points AND max_points LIMIT 1");
        $stmtDiv->execute([$levelType, $totalPoints]);
        $divRow = $stmtDiv->fetch(PDO::FETCH_ASSOC);
        $divisionResult = $divRow ? $divRow['division_name'] : 'Division IV';

        $gpa = $subjectCount > 0 ? round($totalScores / $subjectCount, 2) : 0;

        // Fetch Form Master Comment
        $stmtComment = $conn->prepare("SELECT conduct_comment FROM student_report_comments WHERE school_id = ? AND academic_year = ? AND term = ? AND student_id = ? LIMIT 1");
        $stmtComment->execute([$schoolId, $year, $term, $studentId]);
        $conductComment = $stmtComment->fetchColumn() ?: 'Demonstrates good behavior and consistent academic effort.';

        echo json_encode([
            'success' => true,
            'student' => $student,
            'year' => $year,
            'term' => $term,
            'subject_marks' => $subjectMarks,
            'summary' => [
                'total_points' => $totalPoints,
                'division' => $divisionResult,
                'gpa_average' => $gpa,
                'subject_count' => $subjectCount
            ],
            'conduct_comment' => $conductComment
        ]);
        exit();
    }

    // SAVE FORM MASTER CONDUCT COMMENT
    if ($action === 'save_conduct_comment') {
        $studentId = $input['student_id'] ?? '';
        $comment = trim($input['conduct_comment'] ?? '');

        if (empty($studentId)) {
            echo json_encode(['success' => false, 'message' => 'student_id is required.']);
            exit();
        }

        $stmt = $conn->prepare("
            INSERT INTO student_report_comments (school_id, academic_year, term, student_id, form_master_id, conduct_comment)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE conduct_comment = VALUES(conduct_comment), form_master_id = VALUES(form_master_id), updated_at = NOW()
        ");
        $stmt->execute([$schoolId, $year, $term, $studentId, $_SESSION['user_id'], $comment]);

        echo json_encode(['success' => true, 'message' => 'Form Master conduct comment saved successfully.']);
        exit();
    }

    // 2. CLASSROOM STREAM PERFORMANCE LEDGER (TASK 4.2)
    if ($action === 'classroom_ledger') {
        $classroomId = intval($_GET['classroom_id'] ?? $input['classroom_id'] ?? 0);
        if (!$classroomId) {
            echo json_encode(['success' => false, 'message' => 'classroom_id required.']);
            exit();
        }

        // Fetch classroom name
        $stmtC = $conn->prepare("SELECT classroom_name FROM classrooms WHERE id = ? AND school_id = ?");
        $stmtC->execute([$classroomId, $schoolId]);
        $cname = $stmtC->fetchColumn();

        // Fetch students in room
        $stmtS = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code, u.gender
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            WHERE sca.classroom_id = ? AND sca.school_id = ? AND sca.academic_year = ? AND sca.status = 'Active'
            ORDER BY u.full_name ASC
        ");
        $stmtS->execute([$classroomId, $schoolId, $year]);
        $students = $stmtS->fetchAll(PDO::FETCH_ASSOC);

        // Fetch distinct subjects evaluated in room
        $stmtSubj = $conn->prepare("
            SELECT DISTINCT me.subject_code, COALESCE(s.subject_name, me.subject_code) AS subject_name
            FROM marks_entry me
            JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
            LEFT JOIN subjects s ON me.subject_code = s.subject_code
            WHERE sca.classroom_id = ? AND me.school_id = ? AND me.academic_year = ? AND me.term = ?
            ORDER BY me.subject_code ASC
        ");
        $stmtSubj->execute([$classroomId, $schoolId, $year, $term]);
        $subjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC);

        // Matrix map: student_id => [ subject_code => total_score ]
        $matrixMap = [];
        $stmtAllMarks = $conn->prepare("
            SELECT me.student_id, me.subject_code,
                   (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) AS total_score
            FROM marks_entry me
            JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
            WHERE sca.classroom_id = ? AND me.school_id = ? AND me.academic_year = ? AND me.term = ?
        ");
        $stmtAllMarks->execute([$classroomId, $schoolId, $year, $term]);
        $allMarks = $stmtAllMarks->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allMarks as $m) {
            $matrixMap[$m['student_id']][$m['subject_code']] = round(floatval($m['total_score']), 2);
        }

        // Attach scores & failing counts to students
        foreach ($students as &$s) {
            $s['scores'] = $matrixMap[$s['student_id']] ?? [];
            $failingCount = 0;
            $totalSum = 0;
            foreach ($s['scores'] as $scoreVal) {
                $totalSum += $scoreVal;
                if ($scoreVal < 45.0) $failingCount++;
            }
            $s['failing_count'] = $failingCount;
            $s['total_aggregate'] = round($totalSum, 2);
        }

        echo json_encode([
            'success' => true,
            'classroom_name' => $cname,
            'year' => $year,
            'term' => $term,
            'subjects' => $subjects,
            'students' => $students
        ]);
        exit();
    }

    // 3. COMPARATIVE GRADE-WIDE ANALYTICS BOARD (TASK 4.3)
    if ($action === 'comparative_analytics') {
        $gradeId = intval($_GET['grade_id'] ?? $input['grade_id'] ?? 0);

        // Fetch streams in grade or all streams if grade_id = 0
        $stmtStreams = $conn->prepare("
            SELECT c.id AS classroom_id, c.classroom_name, g.name AS grade_name
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            WHERE c.school_id = ? AND c.academic_year = ? AND (? = 0 OR c.grade_id = ?)
            ORDER BY g.order_seq ASC, c.classroom_name ASC
        ");
        $stmtStreams->execute([$schoolId, $year, $gradeId, $gradeId]);
        $streams = $stmtStreams->fetchAll(PDO::FETCH_ASSOC);

        // Stream Comparison: Stream => Pass Rate % and Average Score
        $streamAnalytics = [];
        foreach ($streams as $st) {
            $cid = $st['classroom_id'];
            $stmtStats = $conn->prepare("
                SELECT COUNT(*) AS total_entries,
                       SUM(CASE WHEN (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) >= 45 THEN 1 ELSE 0 END) AS pass_entries,
                       AVG(COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) AS avg_score
                FROM marks_entry me
                JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
                WHERE sca.classroom_id = ? AND me.school_id = ? AND me.academic_year = ? AND me.term = ?
            ");
            $stmtStats->execute([$cid, $schoolId, $year, $term]);
            $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

            $tot = intval($stats['total_entries']);
            $pass = intval($stats['pass_entries']);
            $passRate = $tot > 0 ? round(($pass / $tot) * 100, 1) : 0;
            $avgScore = round(floatval($stats['avg_score']), 1);

            $streamAnalytics[] = [
                'classroom_id' => $cid,
                'classroom_name' => $st['classroom_name'],
                'grade_name' => $st['grade_name'],
                'total_entries' => $tot,
                'pass_entries' => $pass,
                'pass_rate_percent' => $passRate,
                'average_score' => $avgScore
            ];
        }

        // Gender Comparison: Male vs Female pass rates and averages across the school / grade
        $stmtGender = $conn->prepare("
            SELECT u.gender,
                   COUNT(me.id) AS total_entries,
                   SUM(CASE WHEN (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) >= 45 THEN 1 ELSE 0 END) AS pass_entries,
                   AVG(COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) AS avg_score
            FROM marks_entry me
            JOIN users u ON me.student_id = u.id
            JOIN student_classroom_allocations sca ON me.student_id = sca.student_id
            JOIN classrooms c ON sca.classroom_id = c.id
            WHERE me.school_id = ? AND me.academic_year = ? AND me.term = ? AND (? = 0 OR c.grade_id = ?)
            GROUP BY u.gender
        ");
        $stmtGender->execute([$schoolId, $year, $term, $gradeId, $gradeId]);
        $genderRows = $stmtGender->fetchAll(PDO::FETCH_ASSOC);

        $genderAnalytics = [];
        foreach ($genderRows as $gr) {
            $gLabel = (strtolower($gr['gender']) === 'm' || strtolower($gr['gender']) === 'male') ? 'Male (Boys)' : 'Female (Girls)';
            $tot = intval($gr['total_entries']);
            $pass = intval($gr['pass_entries']);
            $passRate = $tot > 0 ? round(($pass / $tot) * 100, 1) : 0;
            $avgScore = round(floatval($gr['avg_score']), 1);

            $genderAnalytics[] = [
                'gender' => $gLabel,
                'total_entries' => $tot,
                'pass_entries' => $pass,
                'pass_rate_percent' => $passRate,
                'average_score' => $avgScore
            ];
        }

        echo json_encode([
            'success' => true,
            'year' => $year,
            'term' => $term,
            'stream_analytics' => $streamAnalytics,
            'gender_analytics' => $genderAnalytics
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
