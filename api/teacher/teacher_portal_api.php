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
$schoolId  = $_SESSION['school_id'] ?? null;
if (!$schoolId && ($_SESSION['role'] ?? '') === 'super_admin') {
    $row = $conn->query('SELECT id FROM schools LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $schoolId = $row['id'] ?? null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'dashboard';
$academicYearId = $_GET['academic_year'] ?? '2026';

try {
    // 1. DASHBOARD & OVERVIEW DATA
    if ($action === 'dashboard') {
        // Teacher Info
        $tStmt = $conn->prepare("SELECT id, user_code, full_name, email, phone, department FROM users WHERE id = ?");
        $tStmt->execute([$teacherId]);
        $teacher = $tStmt->fetch(PDO::FETCH_ASSOC);

        // Fetch Allocations (combining legacy & new allocation engines)
        $stmtAlloc = $conn->prepare("
            SELECT classroom_name, subject_code, subject_name, student_count
            FROM (
                SELECT DISTINCT COALESCE(c.classroom_name, tsa.class_stream_id) AS classroom_name, tsa.subject_code, COALESCE(sas.subject_name, tsa.subject_code) AS subject_name,
                       (SELECT COUNT(DISTINCT sca.student_id) FROM student_classroom_allocations sca JOIN classrooms c2 ON sca.classroom_id=c2.id WHERE (c2.classroom_name=tsa.class_stream_id OR CAST(c2.id AS CHAR)=tsa.class_stream_id) AND sca.status='Active') AS student_count
                FROM teacher_subject_assignments tsa
                LEFT JOIN classrooms c ON (tsa.class_stream_id = c.classroom_name OR tsa.class_stream_id = CAST(c.id AS CHAR))
                LEFT JOIN school_approved_subjects sas ON (tsa.school_id = sas.school_id AND tsa.subject_code = sas.subject_code)
                WHERE tsa.teacher_id = :teacher_id AND tsa.academic_year_id = :year_id

                UNION DISTINCT

                SELECT DISTINCT c.classroom_name AS classroom_name, s.code AS subject_code, s.name AS subject_name,
                       (SELECT COUNT(DISTINCT sca.student_id) FROM student_classroom_allocations sca WHERE sca.classroom_id=c.id AND sca.status='Active') AS student_count
                FROM teacher_classroom_assignments tca
                JOIN classrooms c ON tca.classroom_id = c.id
                JOIN subjects s ON tca.subject_id = s.id
                WHERE tca.teacher_id = :teacher_id AND tca.academic_year_id = :year_id
            ) AS combined_alloc
            ORDER BY classroom_name ASC, subject_name ASC
        ");
        $stmtAlloc->execute([':teacher_id' => $teacherId, ':year_id' => $academicYearId]);
        $allocations = $stmtAlloc->fetchAll(PDO::FETCH_ASSOC);

        // Calculate Totals
        $uniqueStreams = array_unique(array_column($allocations, 'classroom_name'));
        $totalStudents = 0;
        foreach ($uniqueStreams as $stName) {
            $c = $conn->prepare("SELECT COUNT(DISTINCT sca.student_id) FROM student_classroom_allocations sca JOIN classrooms c ON sca.classroom_id=c.id WHERE (c.classroom_name=? OR CAST(c.id AS CHAR)=?) AND sca.status='Active'");
            $c->execute([$stName, $stName]);
            $totalStudents += (int)$c->fetchColumn();
        }

        // Check Form Master
        $stmtFM = $conn->prepare("
            SELECT COALESCE(c.classroom_name, ct.class_stream_id) AS form_master_class
            FROM class_teachers ct
            LEFT JOIN classrooms c ON (ct.class_stream_id = c.classroom_name OR ct.class_stream_id = CAST(c.id AS CHAR))
            WHERE ct.teacher_id = ? AND ct.academic_year_id = ? LIMIT 1
        ");
        $stmtFM->execute([$teacherId, $academicYearId]);
        $formMasterClass = $stmtFM->fetchColumn();

        // Daily Timetable Schedule
        $dayOfWeek = date('l'); // e.g. Monday
        if (!in_array($dayOfWeek, ['Monday','Tuesday','Wednesday','Thursday','Friday'])) {
            $dayOfWeek = 'Monday'; // Default weekday view
        }
        $stmtTT = $conn->prepare("
            SELECT ct.day_of_week, COALESCE(c.classroom_name, ct.class_stream_id) AS classroom_name, ct.subject_code,
                   COALESCE(sas.subject_name, ct.subject_code) AS subject_name,
                   tp.period_name, tp.start_time, tp.end_time
            FROM class_timetables ct
            LEFT JOIN classrooms c ON (ct.class_stream_id = c.classroom_name OR ct.class_stream_id = CAST(c.id AS CHAR))
            JOIN timetable_periods tp ON ct.period_id = tp.id
            LEFT JOIN school_approved_subjects sas ON (ct.school_id = sas.school_id AND ct.subject_code = sas.subject_code)
            WHERE ct.teacher_id = :teacher_id AND ct.academic_year_id = :year_id AND ct.day_of_week = :day_name
            ORDER BY tp.start_time ASC
        ");
        $stmtTT->execute([':teacher_id' => $teacherId, ':year_id' => $academicYearId, ':day_name' => $dayOfWeek]);
        $schedule = $stmtTT->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "teacher" => $teacher,
            "academic_year" => $academicYearId,
            "metrics" => [
                "my_subjects_count" => count($allocations),
                "total_students" => $totalStudents,
                "form_master_class" => $formMasterClass ?: null,
                "today_periods_count" => count($schedule)
            ],
            "allocations" => $allocations,
            "schedule" => $schedule
        ]);
        exit();
    }

    // 2. SCORE SHEET ROSTER WITH PAGINATION & SEARCH
    if ($action === 'get_scoresheet') {
        $streamName  = $_GET['stream'] ?? '';
        $subjectCode = $_GET['subject'] ?? '';
        $search      = trim($_GET['search'] ?? '');
        $page        = max(1, intval($_GET['page'] ?? 1));
        $limit       = max(10, min(500, intval($_GET['limit'] ?? 50)));
        $offset      = ($page - 1) * $limit;

        if (empty($streamName)) {
            echo json_encode(["success" => false, "message" => "Stream parameter is required."]);
            exit();
        }

        $whereSql = "WHERE (c.classroom_name = :cname OR CAST(c.id AS CHAR) = :cname) AND sca.academic_year = :year AND sca.status = 'Active'";
        $params = [':subj' => $subjectCode, ':year' => $academicYearId, ':cname' => $streamName];

        if ($search !== '') {
            $whereSql .= " AND (u.full_name LIKE :search OR u.user_code LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $stmtCnt = $conn->prepare("
            SELECT COUNT(*)
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            $whereSql
        ");
        // Count statement doesn't need :subj
        $paramsCnt = $params;
        unset($paramsCnt[':subj']);
        $stmtCnt->execute($paramsCnt);
        $total = (int)$stmtCnt->fetchColumn();
        $totalPages = max(1, ceil($total / $limit));

        $term = $_GET['term'] ?? 'Term 1';
        $params[':term'] = $term;

        // Fetch education level type for the target classroom stream
        $stmtLevel = $conn->prepare("
            SELECT el.name AS level_type
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE (c.classroom_name = :cname OR CAST(c.id AS CHAR) = :cname)
            LIMIT 1
        ");
        $stmtLevel->execute([':cname' => $streamName]);
        $levelType = $stmtLevel->fetchColumn() ?: 'O-Level';

        // Fetch official grading scale rules configured by Super Admin in Academic Curriculum
        $stmtScales = $conn->prepare("
            SELECT min_mark, max_mark, grade, remark, points
            FROM grading_scales
            WHERE level_type = :ltype
            ORDER BY min_mark DESC
        ");
        $stmtScales->execute([':ltype' => $levelType]);
        $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);

        if (empty($scales)) {
            $stmtScales->execute([':ltype' => 'O-Level']);
            $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmtRoster = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code,
                   COALESCE(me.continuous_assessment_mark, 0) AS ca_mark,
                   COALESCE(me.terminal_mark, 0) AS terminal_mark
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN marks_entry me ON (me.student_id = u.id AND me.subject_code = :subj AND me.academic_year = :year AND me.term = :term)
            $whereSql
            ORDER BY u.full_name ASC
        ");
        $stmtRoster->execute($params);
        $roster = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);

        // Dynamically assign letter grades and remarks using database grading scale
        $allScored = [];
        foreach ($roster as &$student) {
            $ca = floatval($student['ca_mark']);
            $termMark = floatval($student['terminal_mark']);
            $total = round($ca + $termMark, 2);
            $student['total_score'] = $total;

            $matchedGrade = '-';
            $matchedRemark = '-';

            foreach ($scales as $sc) {
                if ($total >= floatval($sc['min_mark']) && $total <= floatval($sc['max_mark'])) {
                    $matchedGrade = $sc['grade'];
                    $matchedRemark = $sc['remark'];
                    break;
                }
            }

            $student['grade_letter'] = $matchedGrade;
            $student['grade_remark'] = $matchedRemark;
            $allScored[] = $student;
        }

        // Rank students by total_score DESC
        usort($allScored, fn($a, $b) => $b['total_score'] <=> $a['total_score']);

        $rankMap = [];
        $currentRank = 1;
        foreach ($allScored as $idx => $s) {
            if ($idx > 0 && $s['total_score'] < $allScored[$idx - 1]['total_score']) {
                $currentRank = $idx + 1;
            }
            $rankMap[$s['student_id']] = $currentRank;
        }

        // Attach rank position to roster
        foreach ($roster as &$student) {
            $student['subject_rank'] = $rankMap[$student['student_id']] ?? '-';
        }

        echo json_encode([
            "success" => true,
            "stream" => $streamName,
            "subject" => $subjectCode,
            "term" => $term,
            "roster" => $roster
        ]);
        exit();
    }

    // 3. SAVE MARKS BATCH
    if ($action === 'save_marks') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $subjectCode = $input['subject_code'] ?? '';
        $trackType   = $input['track_type'] ?? 'terminal'; // ca or terminal
        $term        = $input['term'] ?? 'Term 1';
        $marksData   = $input['marks'] ?? [];

        if (empty($subjectCode) || empty($marksData)) {
            echo json_encode(["success" => false, "message" => "Subject code and marks data required."]);
            exit();
        }

        $conn->beginTransaction();
        foreach ($marksData as $m) {
            $studentId = $m['student_id'];
            $score     = floatval($m['score']);

            if ($trackType === 'ca') {
                $stmt = $conn->prepare("
                    INSERT INTO marks_entry (school_id, academic_year, term, student_id, subject_code, continuous_assessment_mark)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE continuous_assessment_mark = VALUES(continuous_assessment_mark)
                ");
                $stmt->execute([$schoolId, $academicYearId, $term, $studentId, $subjectCode, $score]);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO marks_entry (school_id, academic_year, term, student_id, subject_code, terminal_mark)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE terminal_mark = VALUES(terminal_mark)
                ");
                $stmt->execute([$schoolId, $academicYearId, $term, $studentId, $subjectCode, $score]);
            }
        }
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Assessment scores batch saved successfully for $term."]);
        exit();
    }

    // 4. FORM MASTER DAILY CLASSROOM GENERAL ATTENDANCE (MAHUDHURIO YA KILA SIKU)
    if ($action === 'get_daily_attendance') {
        $targetDate  = $_GET['date'] ?? date('Y-m-d');
        $classroomId = intval($_GET['classroom_id'] ?? 0);

        // 1. Fetch all classrooms where teacher is/was assigned as Class Guider (Mwalimu wa Darasa)
        $stmtFM = $conn->prepare("
            SELECT DISTINCT c.id AS classroom_id, c.classroom_name, c.academic_year
            FROM class_teachers ct
            JOIN classrooms c ON (ct.class_stream_id = c.classroom_name OR ct.class_stream_id = CAST(c.id AS CHAR))
            WHERE ct.teacher_id = :teacher_id
            ORDER BY c.academic_year DESC, c.classroom_name ASC
        ");
        $stmtFM->execute([':teacher_id' => $teacherId]);
        $managedClasses = $stmtFM->fetchAll(PDO::FETCH_ASSOC);

        if (empty($managedClasses)) {
            echo json_encode([
                "success" => true,
                "is_form_master" => false,
                "message" => "Access Restricted: You are not assigned as a Class Guider for any classroom."
            ]);
            exit();
        }

        // Select requested classroom or default to first assigned classroom
        $selectedRoom = null;
        if ($classroomId > 0) {
            foreach ($managedClasses as $mc) {
                if (intval($mc['classroom_id']) === $classroomId) {
                    $selectedRoom = $mc;
                    break;
                }
            }
        }
        if (!$selectedRoom) {
            $selectedRoom = $managedClasses[0];
        }

        $classroomId   = $selectedRoom['classroom_id'];
        $classroomName = $selectedRoom['classroom_name'];
        $yearForRoom   = $selectedRoom['academic_year'];

        // 2. Fetch existing daily master ledger record if taken on target date
        $stmtMaster = $conn->prepare("SELECT id, general_remarks FROM daily_attendance WHERE classroom_id = ? AND attendance_date = ?");
        $stmtMaster->execute([$classroomId, $targetDate]);
        $master = $stmtMaster->fetch(PDO::FETCH_ASSOC);
        $attendanceId = $master ? $master['id'] : null;

        // 3. Fetch Roster & Join details status
        $stmtRoster = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code,
                   COALESCE(dad.status, 'Present') AS status
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN daily_attendance_details dad ON (dad.daily_attendance_id = :att_id AND dad.student_id = u.id)
            WHERE c.id = :cid AND sca.academic_year = :year AND sca.status != 'TransferredOut'
            ORDER BY u.full_name ASC
        ");
        $stmtRoster->execute([':att_id' => $attendanceId, ':cid' => $classroomId, ':year' => $yearForRoom]);
        $roster = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);

        // Headcount summary
        $total    = count($roster);
        $present  = count(array_filter($roster, fn($r) => $r['status'] === 'Present'));
        $absent   = count(array_filter($roster, fn($r) => $r['status'] === 'Absent'));
        $excused  = count(array_filter($roster, fn($r) => $r['status'] === 'Excused'));

        $dtTime = strtotime($targetDate);
        $dateFormatted = date('l, M j, Y', $dtTime);

        echo json_encode([
            "success" => true,
            "is_form_master" => true,
            "classroom_id" => $classroomId,
            "classroom_name" => $classroomName,
            "academic_year" => $yearForRoom,
            "attendance_date" => $targetDate,
            "date_formatted" => $dateFormatted,
            "is_update_mode" => !empty($master),
            "general_remarks" => $master ? $master['general_remarks'] : '',
            "managed_classes" => $managedClasses,
            "headcount" => [
                "total" => $total,
                "present" => $present,
                "absent" => $absent,
                "excused" => $excused
            ],
            "roster" => $roster
        ]);
        exit();
    }

    if ($action === 'save_daily_attendance') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $classroomId     = intval($input['classroom_id'] ?? 0);
        $date            = trim($input['attendance_date'] ?? date('Y-m-d'));
        $studentStatuses = $input['statuses'] ?? []; // [ student_id => 'Present'|'Absent'|'Excused' ]
        $remarks         = trim($input['remarks'] ?? '');

        if (!$classroomId || empty($studentStatuses)) {
            echo json_encode(["success" => false, "message" => "Classroom ID and student status payload required."]);
            exit();
        }

        // Fetch classroom academic year
        $yrStmt = $conn->prepare("SELECT academic_year FROM classrooms WHERE id = ?");
        $yrStmt->execute([$classroomId]);
        $roomYear = $yrStmt->fetchColumn() ?: $academicYearId;

        $conn->beginTransaction();

        // 1. Check if a master record already exists for this room on this specific date
        $checkStmt = $conn->prepare("SELECT id FROM daily_attendance WHERE classroom_id = :class_id AND attendance_date = :date");
        $checkStmt->execute([':class_id' => $classroomId, ':date' => $date]);
        $attendanceId = $checkStmt->fetchColumn();

        if ($attendanceId) {
            // Record exists: Update master remarks
            $updateMaster = $conn->prepare("UPDATE daily_attendance SET general_remarks = :remarks WHERE id = :id");
            $updateMaster->execute([':remarks' => $remarks, ':id' => $attendanceId]);
        } else {
            // New Record: Insert master entry
            $insertMaster = $conn->prepare("
                INSERT INTO daily_attendance (academic_year_id, classroom_id, attendance_date, recorded_by_teacher_id, general_remarks) 
                VALUES (:year_id, :class_id, :date, :teacher_id, :remarks)
            ");
            $insertMaster->execute([
                ':year_id'    => $roomYear,
                ':class_id'   => $classroomId,
                ':date'       => $date,
                ':teacher_id' => $teacherId,
                ':remarks'    => $remarks
            ]);
            $attendanceId = $conn->lastInsertId();
        }

        // 2. Clear old detail states for this master entry
        $clearDetails = $conn->prepare("DELETE FROM daily_attendance_details WHERE daily_attendance_id = :id");
        $clearDetails->execute([':id' => $attendanceId]);

        // 3. Batch insert student statuses
        $insertDetails = $conn->prepare("
            INSERT INTO daily_attendance_details (daily_attendance_id, student_id, status) 
            VALUES (:master_id, :student_id, :status)
        ");

        foreach ($studentStatuses as $sid => $status) {
            $insertDetails->execute([
                ':master_id'  => $attendanceId,
                ':student_id' => $sid,
                ':status'     => in_array($status, ['Present','Absent','Excused']) ? $status : 'Present'
            ]);
        }

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Roll-call cataloged successfully for $date."]);
        exit();
    }

    if ($action === 'get_attendance_history') {
        $yearParam   = $_GET['year'] ?? 'All';
        $monthParam  = $_GET['month'] ?? 'All';
        $classroomId = intval($_GET['classroom_id'] ?? 0);

        $sql = "
            SELECT da.id, da.classroom_id, c.classroom_name, c.academic_year, da.attendance_date, da.general_remarks, da.created_at,
                   (SELECT COUNT(*) FROM daily_attendance_details dad WHERE dad.daily_attendance_id = da.id) AS total_students,
                   (SELECT COUNT(*) FROM daily_attendance_details dad WHERE dad.daily_attendance_id = da.id AND dad.status = 'Present') AS present_count,
                   (SELECT COUNT(*) FROM daily_attendance_details dad WHERE dad.daily_attendance_id = da.id AND dad.status = 'Absent') AS absent_count,
                   (SELECT COUNT(*) FROM daily_attendance_details dad WHERE dad.daily_attendance_id = da.id AND dad.status = 'Excused') AS excused_count
            FROM daily_attendance da
            JOIN classrooms c ON da.classroom_id = c.id
            WHERE da.recorded_by_teacher_id = :teacher_id
        ";
        $params = [':teacher_id' => $teacherId];

        if ($yearParam !== 'All') {
            $sql .= " AND (da.academic_year_id = :year OR c.academic_year = :year)";
            $params[':year'] = $yearParam;
        }
        if ($monthParam !== 'All') {
            $sql .= " AND MONTH(da.attendance_date) = :month";
            $params[':month'] = intval($monthParam);
        }
        if ($classroomId > 0) {
            $sql .= " AND da.classroom_id = :cid";
            $params[':cid'] = $classroomId;
        }

        $sql .= " ORDER BY da.attendance_date DESC, da.id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($history as &$h) {
            $h['date_formatted'] = date('l, M j, Y', strtotime($h['attendance_date']));
        }

        echo json_encode([
            "success" => true,
            "history" => $history,
            "count" => count($history)
        ]);
        exit();
    }

    // 5. PARENT DESK CATALOG WITH PAGINATION & SEARCH
    if ($action === 'get_parents_catalog') {
        $streamName = $_GET['stream'] ?? '';
        $search     = trim($_GET['search'] ?? '');
        $page       = max(1, intval($_GET['page'] ?? 1));
        $limit      = max(10, min(500, intval($_GET['limit'] ?? 25)));
        $offset     = ($page - 1) * $limit;

        $whereSql = "WHERE c.classroom_name = :cname AND sca.academic_year = :year AND sca.status = 'Active'";
        $params = [':cname' => $streamName, ':year' => $academicYearId];

        if ($search !== '') {
            $whereSql .= " AND (u.full_name LIKE :search OR u.user_code LIKE :search OR p.guardian_name LIKE :search OR p.guardian_phone LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $stmtCnt = $conn->prepare("
            SELECT COUNT(*)
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN parent_profiles p ON (u.id = p.student_id)
            $whereSql
        ");
        $stmtCnt->execute($params);
        $total = (int)$stmtCnt->fetchColumn();
        $totalPages = max(1, ceil($total / $limit));

        $stmtParent = $conn->prepare("
            SELECT u.full_name AS student_name, u.user_code,
                   COALESCE(p.guardian_name, 'Juma Mlimani Kassim') AS parent_name,
                   COALESCE(p.relation, 'Father') AS relation,
                   COALESCE(p.guardian_phone, '+255712000000') AS phone
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN parent_profiles p ON (u.id = p.student_id)
            $whereSql
            ORDER BY u.full_name ASC
            LIMIT $offset, $limit
        ");
        $stmtParent->execute($params);
        $catalog = $stmtParent->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "success" => true,
            "stream" => $streamName,
            "catalog" => $catalog,
            "pagination" => [
                "total" => $total,
                "page" => $page,
                "limit" => $limit,
                "total_pages" => $totalPages
            ]
        ]);
        exit();
    }

    echo json_encode(["success" => false, "message" => "Unknown action."]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
