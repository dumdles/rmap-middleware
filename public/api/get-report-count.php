<?php
// get-report-count.php

require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../../src/config.php';

// Set the response header to application/json
header('Content-Type: application/json');

// Allow cross-origin requests
header('Access-Control-Allow-Origin: *');

if (isset($_GET['year']) && isset($_GET['month'])) {
    $year = intval($_GET['year']);
    $month = intval($_GET['month']);

    try {
        // Connect to the database
        $db = getDBconnection();

        // Prepare the query to count reports for the specified month and year
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM report_list WHERE YEAR(inspection_date) = ? AND MONTH(inspection_date) = ?");
        $stmt->bind_param("ii", $year, $month);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();

        echo json_encode(['success' => true, 'count' => $count]);

        $stmt->close();
        $db->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching report count: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Year and month parameters are required.']);
}
