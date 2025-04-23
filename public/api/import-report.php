<?php
// import-report.php

require_once __DIR__ . '/src/middleware.php';
require_once __DIR__ . '/src/config.php';

header('Content-Type: application/json');

// Function to generate UUID v4
function generate_uuid_v4()
{
    $data = random_bytes(16);

    // Set version to 0100 (version 4)
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    // Set bits 6-7 to 10 (variant 1)
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    // Convert to hex
    $hex = bin2hex($data);

    // Assemble UUID string
    $uuid = sprintf(
        '%08s-%04s-%04s-%04s-%12s',
        substr($hex, 0, 8),   // 8 characters
        substr($hex, 8, 4),   // 4 characters
        substr($hex, 12, 4),  // 4 characters
        substr($hex, 16, 4),  // 4 characters
        substr($hex, 20, 12)  // 12 characters
    );

    return $uuid;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'No data provided.']);
    exit;
}

// Extract data
$reportNumber = $data['reportNumber'];
$customerName = $data['customerName'];
$customerAddress = $data['customerAddress'];
$inspectionDate = $data['inspectionDate'];
$inspectorName = $data['inspectorName'];
$inspections = $data['inspections'];

// Validate required fields
if (!$reportNumber || !$customerName || !$inspectionDate || !$inspectorName || !$inspections) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

try {
    $db = getDBconnection();

    // Start transaction
    $db->autocommit(FALSE);

    // **Step 1: Check if Customer Exists or Create New Customer**
    $stmt = $db->prepare("SELECT customerID FROM customers WHERE customer_name = ?");
    $stmt->bind_param("s", $customerName);
    $stmt->execute();
    $stmt->bind_result($customer_id);
    if (!$stmt->fetch()) {
        // Customer doesn't exist, create new customer
        $stmt->close();

        $stmt = $db->prepare("INSERT INTO customers (customer_name, customer_address) VALUES (?, ?)");
        $stmt->bind_param("ss", $customerName, $customerAddress);
        $stmt->execute();
        $customer_id = $db->insert_id;
    }
    $stmt->close();

    // **Step 1.5: Lookup Inspector ID**
    $stmt = $db->prepare("SELECT id FROM inspectors WHERE inspector_name = ?");
    $stmt->bind_param("s", $inspectorName);
    $stmt->execute();
    $stmt->bind_result($inspector_id);
    if (!$stmt->fetch()) {
        // Inspector doesn't exist; handle accordingly
        $stmt->close();
        $stmt = $db->prepare("INSERT INTO inspectors (inspector_name) VALUES (?)");
        $stmt->bind_param("s", $inspectorName);
        $stmt->execute();
        $inspector_id = $db->insert_id;
    }
    $stmt->close();

    // **Step 2: Create New Report**
    $stmt = $db->prepare("INSERT INTO report_list (report_number, customer_id, inspection_date, inspection_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $reportNumber, $customer_id, $inspectionDate, $inspector_id);
    $stmt->execute();
    $report_id = $db->insert_id;
    $stmt->close();

    // **Step 3: Insert Inspections**
    $inspectionStmt = $db->prepare("
        INSERT INTO report_details (
            inspection_id,
            report_number,
            location,
            equipment_name,
            description,
            temp_category,
            ref_image,
            comp_type,
            L1,
            L2,
            L3,
            neutral
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    foreach ($inspections as $inspection) {
        // Generate UUID for inspection_id
        $inspection_id = generate_uuid_v4();

        // Extract and handle null values
        $location = isset($inspection['location']) ? $inspection['location'] : null;
        $equipment_name = isset($inspection['equipment_name']) ? $inspection['equipment_name'] : null;
        $description = isset($inspection['description']) ? $inspection['description'] : null;
        $temp_category = isset($inspection['temp_category']) ? $inspection['temp_category'] : null;
        $ref_image = isset($inspection['ref_image']) ? $inspection['ref_image'] : null;
        $comp_type = isset($inspection['comp_type']) ? $inspection['comp_type'] : null;
        $L1 = isset($inspection['L1']) ? $inspection['L1'] : null;
        $L2 = isset($inspection['L2']) ? $inspection['L2'] : null;
        $L3 = isset($inspection['L3']) ? $inspection['L3'] : null;
        $neutral = isset($inspection['neutral']) ? $inspection['neutral'] : null;

        // Convert numeric values to appropriate types or null
        $ref_image = !empty($ref_image) ? intval($ref_image) : null;
        $L1 = !empty($L1) ? floatval($L1) : null;
        $L2 = !empty($L2) ? floatval($L2) : null;
        $L3 = !empty($L3) ? floatval($L3) : null;
        $neutral = !empty($neutral) ? floatval($neutral) : null;

        // Bind parameters
        $inspectionStmt->bind_param(
            "ssssssisdddd",
            $inspection_id,    // s
            $reportNumber,     // s
            $location,         // s
            $equipment_name,   // s
            $description,      // s
            $temp_category,    // s
            $ref_image,        // i (integer or null)
            $comp_type,        // s
            $L1,               // d (double or null)
            $L2,               // d
            $L3,               // d
            $neutral           // d
        );

        $inspectionStmt->execute();
    }
    $inspectionStmt->close();

    // **Step 4: Commit Transaction**
    $db->commit();
    $db->autocommit(TRUE);

    echo json_encode(['success' => true, 'message' => 'Report and inspections imported successfully.', 'report_id' => $report_id]);
} catch (Exception $e) {
    // Rollback transaction
    $db->rollback();
    $db->autocommit(TRUE);

    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error importing report: ' . $e->getMessage()]);
}

