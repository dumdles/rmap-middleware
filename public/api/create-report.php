<?php
// create-report.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware.php';

// Set the response header to application/json
header('Content-Type: application/json');

// Only handle POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    log_action("Failed Create Report - Invalid Method", $_SESSION['username'], "Method: " . $_SERVER["REQUEST_METHOD"]);
    exit;
}

// Get the raw POST data and decode JSON
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Validate required fields
$requiredFields = ['report_number', 'customer_id', 'inspector_id', 'startDate'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $missingFields[] = ucfirst(str_replace('_', ' ', $field));
    }
}

if (!empty($missingFields)) {
    $missingFieldsMessage = 'Missing required fields: ' . implode(', ', $missingFields);
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => $missingFieldsMessage]);
    log_action("Failed Create Report - Missing Fields", $_SESSION['username'], $missingFieldsMessage);
    exit;
}

// Prepare the data
$report_number = $data['report_number'];
$customer_id = $data['customer_id'];
$inspector_id = $data['inspector_id'];
$inspection_date = $data['startDate'];
$inspection_end_date = isset($data['endDate']) && !empty($data['endDate']) ? $data['endDate'] : NULL; // Set end date to NULL if empty
$remarks = isset($data['remarks']) ? $data['remarks'] : NULL;

// Server-side validation for remarks length (updated to match message)
if (!empty($remarks) && strlen($remarks) > 150) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Remarks cannot exceed 250 characters.']);
    log_action("Failed Create Report - Remarks Too Long", $_SESSION['username'], "Remarks Length: " . strlen($remarks));
    exit;
}

try {
    // Get DB connection
    $db = getDBconnection();

    if ($db->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }

    // Check if report number exists in the DB
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM report_list WHERE report_number = ?");
    if (!$checkStmt) {
        throw new Exception("Prepare statement failed: " . $db->error);
    }
    $checkStmt->bind_param("s", $report_number);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        // Report number already exists
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Report number already exists.']);
        log_action("Failed Create Report - Duplicate Report Number", $_SESSION['username'], "Report Number: $report_number");
        exit;
    }

    // Prepare the insert statement
    $stmt = $db->prepare("INSERT INTO report_list (report_number, customer_id, inspection_date, inspection_end_date, inspection_by, remarks) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $report_number, $customer_id, $inspection_date, $inspection_end_date, $inspector_id, $remarks);

    if ($stmt->execute()) {
        http_response_code(201); // Created

        // Fetch customer name
        $customer_name = 'Unknown';
        $customerStmt = $db->prepare("SELECT customer_name FROM customers WHERE customerID = ?");
        if ($customerStmt) {
            $customerStmt->bind_param("i", $customer_id);
            $customerStmt->execute();
            $customerStmt->bind_result($customer_name);
            $customerStmt->fetch();
            $customerStmt->close();
        }

        // Fetch inspector name
        $inspector_name = 'Unknown';
        $inspectorStmt = $db->prepare("SELECT inspector_name FROM inspectors WHERE id = ?");
        if ($inspectorStmt) {
            $inspectorStmt->bind_param("i", $inspector_id);
            $inspectorStmt->execute();
            $inspectorStmt->bind_result($inspector_name);
            $inspectorStmt->fetch();
            $inspectorStmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Report created successfully.']);
        log_action("Create Report", $_SESSION['username'], "Report Number: $report_number, Customer: $customer_name, Inspector: $inspector_name");
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }


    $stmt->close();
    $db->close();
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    log_action("Error Create Report", $_SESSION['username'], "Error: " . $e->getMessage());
}
