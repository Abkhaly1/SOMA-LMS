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

    // 2. SCORE SHEET ROSTER WITH ROW-LEVEL SECURITY & MULTI-LAYERED RANKING ENGINE
    if ($action === 'get_scoresheet') {
        $streamName  = $_GET['stream'] ?? '';
        $subjectCode = $_GET['subject'] ?? '';
        $term        = $_GET['term'] ?? 'Term 1';
        $search      = trim($_GET['search'] ?? '');

        if (empty($streamName) || empty($subjectCode)) {
            echo json_encode(["success" => false, "message" => "Stream and subject parameters are required."]);
            exit();
        }

        // TASK 2.1: ROW-LEVEL ACCESS CONTROL (Who Enters Marks?)
        // Teachers can ONLY select and open mark sheets for classrooms & subjects explicitly assigned to them
        $userRole = $_SESSION['role'] ?? '';
        if ($userRole === 'teacher') {
            $stmtAccess = $conn->prepare("
                SELECT COUNT(*) FROM teacher_subject_assignments tsa
                JOIN classrooms c ON (tsa.class_stream_id = c.classroom_name OR tsa.class_stream_id = CAST(c.id AS CHAR))
                WHERE tsa.teacher_id = :tid AND tsa.subject_code = :scode AND (c.classroom_name = :cname OR CAST(c.id AS CHAR) = :cname)
            ");
            $stmtAccess->execute([':tid' => $teacherId, ':scode' => $subjectCode, ':cname' => $streamName]);
            if ((int)$stmtAccess->fetchColumn() === 0) {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Access Restricted: You are not assigned to teach $subjectCode for classroom '$streamName'."]);
                exit();
            }
        }

        // Fetch classroom details
        $stmtC = $conn->prepare("SELECT id, classroom_name, grade_id FROM classrooms WHERE (classroom_name = :cname OR CAST(id AS CHAR) = :cname) AND school_id = :sch LIMIT 1");
        $stmtC->execute([':cname' => $streamName, ':sch' => $schoolId]);
        $classroom = $stmtC->fetch(PDO::FETCH_ASSOC);
        $classroomId = $classroom ? intval($classroom['id']) : 0;
        $gradeId = $classroom ? intval($classroom['grade_id']) : 0;

        // TASK 2.3: CHECK READ-ONLY FINALIZATION LOCK
        $stmtLock = $conn->prepare("
            SELECT is_locked, locked_at, locked_by
            FROM marks_entry_locks
            WHERE school_id = ? AND academic_year = ? AND term = ? AND classroom_id = ? AND subject_code = ? AND is_locked = 1
        ");
        $stmtLock->execute([$schoolId, $academicYearId, $term, $classroomId, $subjectCode]);
        $lockRow = $stmtLock->fetch(PDO::FETCH_ASSOC);
        $isLocked = !empty($lockRow);

        // TASK 2.2: FETCH STUDENT-LEVEL ENROLLMENTS (Only students actively registered for this subject)
        $whereSql = "WHERE (c.classroom_name = :cname OR CAST(c.id AS CHAR) = :cname) AND sca.academic_year = :year AND sca.status = 'Active'";
        $params = [':subj' => $subjectCode, ':year' => $academicYearId, ':cname' => $streamName, ':term' => $term, ':sch' => $schoolId];

        if ($search !== '') {
            $whereSql .= " AND (u.full_name LIKE :search OR u.user_code LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        // Check if explicit subject enrollments exist for this classroom & subject
        $stmtEnrCheck = $conn->prepare("SELECT COUNT(*) FROM student_subject_enrollments WHERE school_id = ? AND subject_code = ? AND academic_year_id = ?");
        $stmtEnrCheck->execute([$schoolId, $subjectCode, $academicYearId]);
        $hasEnrFilter = ((int)$stmtEnrCheck->fetchColumn() > 0);

        $enrJoin = "";
        if ($hasEnrFilter) {
            $enrJoin = "JOIN student_subject_enrollments sse ON (sse.student_id = u.id AND sse.subject_code = :subj AND sse.school_id = :sch)";
        }

        // Fetch grading scale rules configured in Academic Curriculum
        $stmtLevel = $conn->prepare("
            SELECT el.name AS level_type
            FROM classrooms c
            JOIN grades g ON c.grade_id = g.id
            JOIN education_levels el ON g.level_id = el.id
            WHERE c.id = :cid LIMIT 1
        ");
        $stmtLevel->execute([':cid' => $classroomId]);
        $levelType = $stmtLevel->fetchColumn() ?: 'O-Level';

        $stmtScales = $conn->prepare("SELECT min_mark, max_mark, grade, remark FROM grading_scales WHERE level_type = :ltype ORDER BY min_mark DESC");
        $stmtScales->execute([':ltype' => $levelType]);
        $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);
        if (empty($scales)) {
            $stmtScales->execute([':ltype' => 'O-Level']);
            $scales = $stmtScales->fetchAll(PDO::FETCH_ASSOC);
        }

        // Fetch score sheet roster
        $stmtRoster = $conn->prepare("
            SELECT u.id AS student_id, u.full_name, u.user_code,
                   COALESCE(me.continuous_assessment_mark, 0) AS ca_mark,
                   COALESCE(me.terminal_mark, 0) AS terminal_mark
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            $enrJoin
            LEFT JOIN marks_entry me ON (me.student_id = u.id AND me.subject_code = :subj AND me.academic_year = :year AND me.term = :term)
            $whereSql
            ORDER BY u.full_name ASC
        ");
        $stmtRoster->execute($params);
        $roster = $stmtRoster->fetchAll(PDO::FETCH_ASSOC);

        // TASK 3.1 & 3.2: CALCULATE SCORES, GRADES & MULTI-LAYERED RANKINGS (STREAM, GRADE-WIDE, SUBJECT-WIDE)

        // 1. Stream Roster Scoring
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
        }

        // Helper function for competition rank calculation with tie handling (Task 3.3)
        $computeRankMap = function($items) {
            usort($items, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
            $map = [];
            $currentRank = 1;
            foreach ($items as $idx => $s) {
                if ($idx > 0 && $s['total_score'] < $items[$idx - 1]['total_score']) {
                    $currentRank = $idx + 1;
                }
                $map[$s['student_id']] = $currentRank;
            }
            return $map;
        };

        // 1. Stream Rank
        $streamRankMap = $computeRankMap($roster);

        // 2. Grade-Wide Rank (Across all streams in this grade e.g. Form 1 Alpha, Beta, Charlie)
        $stmtGradeAll = $conn->prepare("
            SELECT u.id AS student_id,
                   (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) AS total_score
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN marks_entry me ON (me.student_id = u.id AND me.subject_code = :subj AND me.academic_year = :year AND me.term = :term)
            WHERE c.grade_id = :gid AND c.school_id = :sch AND sca.academic_year = :year AND sca.status = 'Active'
        ");
        $stmtGradeAll->execute([':subj' => $subjectCode, ':year' => $academicYearId, ':term' => $term, ':gid' => $gradeId, ':sch' => $schoolId]);
        $gradeWideRoster = $stmtGradeAll->fetchAll(PDO::FETCH_ASSOC);
        $gradeRankMap = $computeRankMap($gradeWideRoster);

        // 3. Subject-Specific Rank (Across entire school taking this course)
        $stmtSubjAll = $conn->prepare("
            SELECT u.id AS student_id,
                   (COALESCE(me.continuous_assessment_mark, 0) + COALESCE(me.terminal_mark, 0)) AS total_score
            FROM student_classroom_allocations sca
            JOIN users u ON sca.student_id = u.id
            JOIN classrooms c ON sca.classroom_id = c.id
            LEFT JOIN marks_entry me ON (me.student_id = u.id AND me.subject_code = :subj AND me.academic_year = :year AND me.term = :term)
            WHERE c.school_id = :sch AND sca.academic_year = :year AND sca.status = 'Active'
        ");
        $stmtSubjAll->execute([':subj' => $subjectCode, ':year' => $academicYearId, ':term' => $term, ':sch' => $schoolId]);
        $subjWideRoster = $stmtSubjAll->fetchAll(PDO::FETCH_ASSOC);
        $subjRankMap = $computeRankMap($subjWideRoster);

        // Attach multi-layered rank positions to roster
        foreach ($roster as &$student) {
            $sid = $student['student_id'];
            $student['subject_rank']    = $streamRankMap[$sid] ?? '-';
            $student['stream_rank']     = $streamRankMap[$sid] ?? '-';
            $student['grade_rank']      = $gradeRankMap[$sid] ?? '-';
            $student['subject_wide_rank']= $subjRankMap[$sid] ?? '-';
        }

        echo json_encode([
            "success" => true,
            "stream" => $streamName,
            "subject" => $subjectCode,
            "term" => $term,
            "classroom_id" => $classroomId,
            "is_locked" => $isLocked,
            "locked_details" => $lockRow,
            "roster" => $roster
        ]);
        exit();
    }

    // 3. SAVE MARKS BATCH (WITH LOCK & ROW-LEVEL SECURITY ENFORCEMENT)
    if ($action === 'save_marks') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $streamName  = $input['stream'] ?? '';
        $subjectCode = $input['subject_code'] ?? '';
        $trackType   = $input['track_type'] ?? 'terminal'; // ca or terminal
        $term        = $input['term'] ?? 'Term 1';
        $marksData   = $input['marks'] ?? [];

        if (empty($subjectCode) || empty($marksData)) {
            echo json_encode(["success" => false, "message" => "Subject code and marks data required."]);
            exit();
        }

        // ROW-LEVEL ACCESS CHECK FOR TEACHER
        $userRole = $_SESSION['role'] ?? '';
        if ($userRole === 'teacher' && !empty($streamName)) {
            $stmtAccess = $conn->prepare("
                SELECT COUNT(*) FROM teacher_subject_assignments tsa
                JOIN classrooms c ON (tsa.class_stream_id = c.classroom_name OR tsa.class_stream_id = CAST(c.id AS CHAR))
                WHERE tsa.teacher_id = :tid AND tsa.subject_code = :scode AND (c.classroom_name = :cname OR CAST(c.id AS CHAR) = :cname)
            ");
            $stmtAccess->execute([':tid' => $teacherId, ':scode' => $subjectCode, ':cname' => $streamName]);
            if ((int)$stmtAccess->fetchColumn() === 0) {
                http_response_code(403);
                echo json_encode(["success" => false, "message" => "Access Restricted: You are not assigned to edit marks for $subjectCode in '$streamName'."]);
                exit();
            }
        }

        // CHECK READ-ONLY FINALIZATION LOCK
        if (!empty($streamName)) {
            $stmtC = $conn->prepare("SELECT id FROM classrooms WHERE (classroom_name = :cname OR CAST(id AS CHAR) = :cname) AND school_id = :sch LIMIT 1");
            $stmtC->execute([':cname' => $streamName, ':sch' => $schoolId]);
            $cid = $stmtC->fetchColumn();

            if ($cid) {
                $stmtLock = $conn->prepare("SELECT is_locked FROM marks_entry_locks WHERE school_id = ? AND academic_year = ? AND term = ? AND classroom_id = ? AND subject_code = ? AND is_locked = 1");
                $stmtLock->execute([$schoolId, $academicYearId, $term, $cid, $subjectCode]);
                if ($stmtLock->fetchColumn()) {
                    http_response_code(423);
                    echo json_encode(["success" => false, "message" => "Locked: This score sheet has been finalized. Any future adjustments require supervisor override from Headmaster Portal."]);
                    exit();
                }
            }
        }

        $conn->beginTransaction();
        foreach ($marksData as $m) {
            $studentId = $m['student_id'];
            $score     = floatval($m['score']);

            if ($trackType === 'ca') {
                $stmt = $conn->prepare("
                    INSERT INTO marks_entry (school_id, academic_year, term, student_id, subject_code, continuous_assessment_mark)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE continuous_assessment_mark = VALUES(continuous_assessment_mark), updated_at = NOW()
                ");
                $stmt->execute([$schoolId, $academicYearId, $term, $studentId, $subjectCode, $score]);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO marks_entry (school_id, academic_year, term, student_id, subject_code, terminal_mark)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE terminal_mark = VALUES(terminal_mark), updated_at = NOW()
                ");
                $stmt->execute([$schoolId, $academicYearId, $term, $studentId, $subjectCode, $score]);
            }
        }
        $conn->commit();
        echo json_encode(["success" => true, "message" => "Assessment scores batch saved successfully for $term."]);
        exit();
    }

    // 4. LOCK MARKS SHEET (SAVE AND LOCK MARKS FINALIZATION)
    if ($action === 'lock_marks') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $streamName  = $input['stream'] ?? '';
        $subjectCode = $input['subject_code'] ?? '';
        $term        = $input['term'] ?? 'Term 1';

        if (empty($streamName) || empty($subjectCode)) {
            echo json_encode(["success" => false, "message" => "Stream and subject_code are required."]);
            exit();
        }

        $stmtC = $conn->prepare("SELECT id FROM classrooms WHERE (classroom_name = :cname OR CAST(id AS CHAR) = :cname) AND school_id = :sch LIMIT 1");
        $stmtC->execute([':cname' => $streamName, ':sch' => $schoolId]);
        $cid = intval($stmtC->fetchColumn());

        if (!$cid) {
            echo json_encode(["success" => false, "message" => "Classroom stream not found."]);
            exit();
        }

        $stmtLock = $conn->prepare("
            INSERT INTO marks_entry_locks (school_id, academic_year, term, classroom_id, subject_code, is_locked, locked_by, locked_at)
            VALUES (?, ?, ?, ?, ?, 1, ?, NOW())
            ON DUPLICATE KEY UPDATE is_locked = 1, locked_by = VALUES(locked_by), locked_at = NOW()
        ");
        $stmtLock->execute([$schoolId, $academicYearId, $term, $cid, $subjectCode, $_SESSION['user_id']]);

        echo json_encode(["success" => true, "message" => "Score sheet finalized and locked into read-only mode."]);
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
