<?php
// get-submitted-last-month-count.php
require_once __DIR__ . '/../../src/index.php';
require_once __DIR__ . '/../../src/config.php';

header('Content-Type: application/json');

try {
    $db = getDBconnection();

    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM report_list 
        WHERE final_report_status = 2 
          AND MONTH(inspection_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) 
          AND YEAR(inspection_date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        echo json_encode([
            'success' => true,
            'count' => (int)$row['count']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'count' => 0
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
