<?php
// get-customer-details.php

require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../../src/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method not allowed
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Customer ID is required.'
    ]);
    exit;
}

$customerId = intval($_GET['id']);

try {
    $db = getDBconnection();

    // Fetch customer details
    $stmt = $db->prepare('SELECT c.*, con.contractor_name AS contractor_name
        FROM customers c
        LEFT JOIN contractors con ON c.contractor = con.contractorID
        WHERE c.customerID = ?');
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404); // Not Found
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found.'
        ]);
        exit;
    }

    $customer = $result->fetch_assoc();

    // Fetch reports associated with this customer
    $reportQuery = "
    SELECT report_list.report_id, report_list.report_number, report_list.inspection_date, report_list.inspection_end_date, report_list.final_report_status, inspectors.inspector_name
    FROM report_list
    LEFT JOIN inspectors ON report_list.inspection_by = inspectors.id
    WHERE report_list.customer_id = ?
    ";

    $reportStmt = $db->prepare($reportQuery);
    $reportStmt->bind_param("i", $customerId);
    $reportStmt->execute();
    $reportResult = $reportStmt->get_result();

    $reports = [];
    while ($row = $reportResult->fetch_assoc()) {
        $reports[] = $row;
    }

    // Return the data
    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'reports' => $reports
    ]);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching customer details: ' . $e->getMessage()
    ]);
}
