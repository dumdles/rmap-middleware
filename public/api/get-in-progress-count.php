<?php
// get-in-progress-count.php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config.php';

try {
    $db = getDBconnection();

    // Count the number of reports where final_report_status indicates 'In Progress' (assuming 0 is 'In Progress')
    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM report_list WHERE final_report_status = 0");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'count' => intval($data['count']),
    ]);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching in-progress count: ' . $e->getMessage(),
    ]);
}
