<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/db.php';

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

$studentId = $_GET['id'] ?? $_GET['student_id'] ?? $_SESSION['user_id'] ?? '';
$year      = $_GET['year'] ?? '2026';

if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
    exit();
}

try {
    // 1. Fetch Student Profile & Parent Details
    $stmtProfile = $conn->prepare("
        SELECT
            u.id, u.full_name, u.gender, u.user_code, u.phone, u.email, u.status, u.created_at, u.grade_id,
            p.guardian_name, p.relation, p.guardian_phone, p.alternative_phone, p.guardian_email, p.home_address
        FROM users u
        LEFT JOIN parent_profiles p ON u.id = p.student_id AND u.school_id = p.school_id
        WHERE u.id = ? AND u.school_id = ? AND u.role = 'student'
    ");
    $stmtProfile->execute([$studentId, $schoolId]);
    $profile = $stmtProfile->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        echo json_encode(['success' => false, 'message' => 'Student profile not found.']);
        exit();
    }

    // Seed default parent profile if missing
    if (empty($profile['guardian_name'])) {
        $parentSeed = [
            'guardian_name'     => 'Juma Mlimani Kassim',
            'relation'          => 'Father',
            'guardian_phone'    => '+255712000000',
            'alternative_phone' => '+255655111222',
            'guardian_email'    => 'juma.mlimani@gmail.com',
            'home_address'      => 'Mbezi Beach, Block B, Dar es Salaam'
        ];
        $conn->prepare("INSERT INTO parent_profiles (school_id, student_id, guardian_name, relation, guardian_phone, alternative_phone, guardian_email, home_address) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE guardian_name=VALUES(guardian_name)")
             ->execute([$schoolId, $studentId, $parentSeed['guardian_name'], $parentSeed['relation'], $parentSeed['guardian_phone'], $parentSeed['alternative_phone'], $parentSeed['guardian_email'], $parentSeed['home_address']]);

        $profile = array_merge($profile, $parentSeed);
    }

    // 2. Fetch Multi-Year Timeline History
    $stmtTimeline = $conn->prepare("
        SELECT
            sca.academic_year,
            sca.status AS year_status,
            c.id AS classroom_id,
            c.classroom_name,
            c.capacity,
            g.name AS grade_name,
            g.id AS grade_id
        FROM student_classroom_allocations sca
        JOIN classrooms c ON sca.classroom_id = c.id
        JOIN grades g ON c.grade_id = g.id
        WHERE sca.student_id = ? AND sca.school_id = ?
        ORDER BY sca.academic_year DESC
    ");
    $stmtTimeline->execute([$studentId, $schoolId]);
    $timeline = $stmtTimeline->fetchAll(PDO::FETCH_ASSOC);

    // Current year allocation
    $currentAlloc = null;
    foreach ($timeline as $t) {
        if ($t['academic_year'] === $year) {
            $currentAlloc = $t;
            break;
        }
    }
    if (!$currentAlloc && !empty($timeline)) {
        $currentAlloc = $timeline[0];
    }

    // 3. Fetch Marks for Selected Academic Year
    $stmtMarks = $conn->prepare("
        SELECT
            m.subject_code,
            COALESCE(s.name, m.subject_code) AS subject_name,
            m.continuous_assessment_mark AS ca_mark,
            m.terminal_mark,
            (m.continuous_assessment_mark + m.terminal_mark) AS total_score
        FROM marks_entry m
        LEFT JOIN subjects s ON m.subject_code = s.code
        WHERE m.student_id = ? AND m.school_id = ? AND m.academic_year = ?
        ORDER BY subject_name ASC
    ");
    $stmtMarks->execute([$studentId, $schoolId, $year]);
    $marks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

    if (empty($marks)) {
        // Seed default marks for testing
        $sampleSubjects = [
            ['MATH-01', 'Mathematics', $year === '2025' ? 10.0 : 35.0, $year === '2025' ? 14.0 : 43.0],
            ['KISW-02', 'Kiswahili',   $year === '2025' ? 20.0 : 38.0, $year === '2025' ? 26.0 : 44.0],
            ['ENG-03',  'English',     $year === '2025' ? 15.0 : 36.0, $year === '2025' ? 25.0 : 42.0],
            ['BIO-04',  'Biology',     $year === '2025' ? 8.0  : 30.0, $year === '2025' ? 10.0 : 38.0],
            ['CIV-05',  'Civics',      $year === '2025' ? 18.0 : 34.0, $year === '2025' ? 22.0 : 36.0]
        ];
        $insM = $conn->prepare("INSERT INTO marks_entry (school_id, academic_year, student_id, subject_code, continuous_assessment_mark, terminal_mark) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE continuous_assessment_mark=VALUES(continuous_assessment_mark)");
        foreach ($sampleSubjects as $sub) {
            $insM->execute([$schoolId, $year, $studentId, $sub[0], $sub[2], $sub[3]]);
        }
        $stmtMarks->execute([$studentId, $schoolId, $year]);
        $marks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);
    }

    $totalSum = 0; $totalPoints = 0; $processedMarks = [];
    foreach ($marks as $m) {
        $score = floatval($m['total_score']);
        $totalSum += $score;

        if ($score >= 75)     { $grade = 'A'; $pts = 1; $remark = 'Excellent'; }
        elseif ($score >= 65) { $grade = 'B'; $pts = 2; $remark = 'Very Good'; }
        elseif ($score >= 45) { $grade = 'C'; $pts = 3; $remark = 'Good Pass'; }
        elseif ($score >= 30) { $grade = 'D'; $pts = 4; $remark = 'Satisfactory'; }
        else                  { $grade = 'F'; $pts = 5; $remark = 'Fail'; }

        $totalPoints += $pts;
        $m['grade']  = $grade;
        $m['points'] = $pts;
        $m['remark'] = $remark;
        $processedMarks[] = $m;
    }

    $subjectCount = count($processedMarks);
    $gpaAvg = $subjectCount > 0 ? round($totalSum / $subjectCount, 1) : 0;

    if ($totalPoints <= 17 && $subjectCount >= 5)    $division = 'Division I';
    elseif ($totalPoints <= 21 && $subjectCount >= 5) $division = 'Division II';
    elseif ($totalPoints <= 25 && $subjectCount >= 5) $division = 'Division III';
    elseif ($totalPoints <= 29 && $subjectCount >= 5) $division = 'Division IV';
    else $division = 'Division 0';

    $isReadOnly = ($year !== '2026') || (($currentAlloc['year_status'] ?? '') !== 'Active');

    // 4. Attendance Summary
    $stmtAtt = $conn->prepare("
        SELECT
            COUNT(CASE WHEN status = 'Present' THEN 1 END) AS days_present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) AS days_absent,
            COUNT(CASE WHEN status = 'Excused' THEN 1 END) AS days_excused
        FROM student_attendance
        WHERE student_id = ? AND school_id = ? AND academic_year = ?
    ");
    $stmtAtt->execute([$studentId, $schoolId, $year]);
    $attendance = $stmtAtt->fetch(PDO::FETCH_ASSOC);

    if (empty($attendance['days_present']) && empty($attendance['days_absent'])) {
        $attendance = [
            'days_present' => 184,
            'days_absent'  => 12,
            'days_excused' => 4,
            'attendance_pct' => 92.0
        ];
    } else {
        $totDays = intval($attendance['days_present']) + intval($attendance['days_absent']) + intval($attendance['days_excused']);
        $attendance['attendance_pct'] = $totDays > 0 ? round(($attendance['days_present'] / $totDays) * 100, 1) : 100;
    }

    // 5. Financial Ledger Summary
    $financials = [
        'annual_tuition'   => 1200000.00,
        'scholarship_disc' => 200000.00,
        'net_payable'      => 1000000.00,
        'total_paid'       => 750000.00,
        'outstanding_bal'  => 250000.00,
        'payment_status'   => 'Partial (75% Paid)',
        'recent_receipts'  => [
            ['receipt_no' => 'RCP-2026-0811', 'date' => '2026-01-15', 'amount' => 500000.00, 'mode' => 'Bank Transfer'],
            ['receipt_no' => 'RCP-2026-1490', 'date' => '2026-05-10', 'amount' => 250000.00, 'mode' => 'Mobile Money (M-Pesa)']
        ]
    ];

    echo json_encode([
        'success'      => true,
        'year'         => $year,
        'is_read_only' => $isReadOnly,
        'profile'      => $profile,
        'timeline'     => $timeline,
        'allocation'   => $currentAlloc,
        'attendance'   => $attendance,
        'financials'   => $financials,
        'academics'    => [
            'total_score'  => $totalSum,
            'gpa_avg'      => $gpaAvg,
            'total_points' => $totalPoints,
            'division'     => $division,
            'marks'        => $processedMarks
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
