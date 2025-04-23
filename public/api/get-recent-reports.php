<?php
// get-recent-reports.php

require_once __DIR__ . '/src/middleware.php';
require_once __DIR__ . '/src/config.php';

try {
    $db = getDBconnection();

    $stmt = $db->prepare("
        SELECT rl.report_number, rl.report_id, rl.inspection_date, rl.final_report_status, c.customer_name, con.contractor_name
        FROM report_list rl 
        JOIN customers c ON rl.customer_id = c.customerID
        JOIN contractors con ON c.contractor = con.contractorID 
        WHERE rl.soft_delete = 'false'
        ORDER BY report_id DESC 
        LIMIT 5
    ");

    $stmt->execute();

    $result = $stmt->get_result(); // Fetch the result set from the prepared statement
    $reports = [];

    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }

    if ($reports) {
        echo json_encode(['success' => true, 'reports' => $reports]);
    } else {
        echo json_encode(['success' => true, 'reports' => []]); // Return empty array if no reports
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching reports: ' . $e->getMessage()]);
}
