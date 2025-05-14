<?php
// get-latest-report.php

require_once __DIR__ . '/../../src/index.php';
require_once __DIR__ . '/../../src/config.php';

try {
    $db = getDBconnection();

    $stmt = $db->prepare("
        SELECT rl.report_number, rl.report_id, rl.inspection_date, rl.final_report_status, c.customer_name, con.contractor_name
        FROM report_list rl 
        JOIN customers c ON rl.customer_id = c.customerID
        JOIN contractors con ON c.contractor = con.contractorID 
        WHERE rl.soft_delete = 'false'
        ORDER BY report_id DESC 
        LIMIT 1
    ");

    $stmt->execute();

    $result = $stmt->get_result();
    $latestReport = $result->fetch_assoc();

    if ($latestReport) {
        echo json_encode(['success' => true, 'report' => $latestReport]);
    } else {
        echo json_encode(['success' => true, 'report' => null]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching latest report: ' . $e->getMessage()]);
}
