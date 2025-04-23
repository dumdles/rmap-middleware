<?php
// insert-inspections.php

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use POST.'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['report_id']) || empty($data['report_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Report ID is required.'
    ]);
    exit;
}

if (!isset($data['inspections']) || !is_array($data['inspections'])) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Inspections data is required.'
    ]);
    exit;
}

$report_id = intval($data['report_id']);
$inspections = $data['inspections'];

try {
    $db = getDBconnection();

    // **Retrieve the report_number associated with the report_id**
    $stmt = $db->prepare("SELECT report_number FROM report_list WHERE report_id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $stmt->bind_result($report_number);
    if (!$stmt->fetch()) {
        http_response_code(400); // Bad Request
        echo json_encode([
            'success' => false,
            'message' => 'Invalid report ID.'
        ]);
        exit;
    }
    $stmt->close();

    // **Proceed to insert inspections using the correct report_number**
    $stmt = $db->prepare("INSERT INTO report_details (report_number, location, equipment_name, description, temp_category, ref_image, comp_type, L1, L2, L3, neutral) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($inspections as $inspection) {
        $stmt->bind_param(
            "sssssssssss",
            $report_number,
            $inspection['location'],
            $inspection['equipment_name'],
            $inspection['description'],
            $inspection['temp_category'],
            $inspection['ref_image'],
            $inspection['comp_type'],
            $inspection['L1'],
            $inspection['L2'],
            $inspection['L3'],
            $inspection['neutral']
        );
        $stmt->execute();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Inspections inserted successfully.'
    ]);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Error inserting inspections: ' . $e->getMessage()
    ]);
}
?>
