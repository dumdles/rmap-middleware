<?php
// get-submitted-this-month-count.php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $db = getDBconnection();

    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM report_list 
        WHERE final_report_status = 2 
          AND MONTH(inspection_date) = MONTH(CURRENT_DATE()) 
          AND YEAR(inspection_date) = YEAR(CURRENT_DATE())
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
