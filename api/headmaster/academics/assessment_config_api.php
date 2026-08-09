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
    if ($method === 'GET') {
        $stmt = $conn->prepare("
            SELECT id, name, weight_percent, is_terminal, created_at
            FROM assessment_types
            WHERE school_id = ? AND academic_year = ? AND term = ?
            ORDER BY is_terminal ASC, name ASC
        ");
        $stmt->execute([$schoolId, $year, $term]);
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total weight
        $totalWeight = 0;
        foreach ($types as $t) {
            $totalWeight += floatval($t['weight_percent']);
        }

        $hasSaved = !empty($types);

        // Default profile if empty
        if (empty($types)) {
            $types = [
                ['id' => null, 'name' => 'Continuous Assessment (CA)', 'weight_percent' => 30.00, 'is_terminal' => 0],
                ['id' => null, 'name' => 'Terminal Exam', 'weight_percent' => 70.00, 'is_terminal' => 1]
            ];
            $totalWeight = 100.00;
        }

        echo json_encode([
            'success' => true,
            'year' => $year,
            'term' => $term,
            'has_saved_policy' => $hasSaved,
            'assessment_types' => $types,
            'total_weight' => round($totalWeight, 2),
            'is_valid_policy' => (round($totalWeight, 2) === 100.00)
        ]);
        exit();
    }

    if ($method === 'POST' && $action === 'save_categories') {
        $categories = $input['categories'] ?? [];
        if (empty($categories)) {
            echo json_encode(['success' => false, 'message' => 'Categories array is required.']);
            exit();
        }

        $sumWeight = 0;
        foreach ($categories as $c) {
            $sumWeight += floatval($c['weight_percent'] ?? 0);
        }

        if (round($sumWeight, 2) !== 100.00) {
            echo json_encode([
                'success' => false,
                'message' => "Invalid policy: Total weight load must equal 100%. Current sum: " . round($sumWeight, 2) . "%."
            ]);
            exit();
        }

        $conn->beginTransaction();
        // Clear existing for this year and term
        $stmtDel = $conn->prepare("DELETE FROM assessment_types WHERE school_id = ? AND academic_year = ? AND term = ?");
        $stmtDel->execute([$schoolId, $year, $term]);

        $stmtIns = $conn->prepare("
            INSERT INTO assessment_types (school_id, academic_year, term, name, weight_percent, is_terminal)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $saved = 0;
        foreach ($categories as $c) {
            $name = trim($c['name'] ?? '');
            if (empty($name)) continue;
            $weight = floatval($c['weight_percent'] ?? 0);
            $isTerminal = !empty($c['is_terminal']) ? 1 : 0;

            $stmtIns->execute([$schoolId, $year, $term, $name, $weight, $isTerminal]);
            $saved++;
        }

        $conn->commit();
        echo json_encode([
            'success' => true,
            'saved_count' => $saved,
            'message' => "Assessment weight policy saved successfully for $term ($year) totaling 100% load."
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
