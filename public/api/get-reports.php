<?php
// get-reports.php

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Initialize an empty array for the reports
$reports = [];

try {
    // Connect to the database
    $db = getDBconnection();

    // Check if customer_id is provided
    $customer_id = null;
    if (isset($_GET['customer_id'])) {
        $customer_id = intval($_GET['customer_id']);
    }

    // Base SQL query to fetch reports
    $sql = "SELECT rl.report_number, rl.report_id, rl.inspection_date, rl.inspection_end_date, rl.final_report_status, 
                   c.customer_name, con.contractor_name,
                   (SELECT COUNT(*) FROM report_details WHERE report_number = rl.report_number AND soft_delete = 'false') AS inspection_count
            FROM report_list rl
            JOIN customers c ON rl.customer_id = c.customerID
            JOIN contractors con ON c.contractor = con.contractorID 
            WHERE rl.soft_delete = 'false'";

    // If customer_id is provided, add it to the WHERE clause
    if ($customer_id !== null && $customer_id > 0) {
        $sql .= " AND rl.customer_id = ?";
    }

    // Prepare the SQL statement
    if ($stmt = $db->prepare($sql)) {
        // Bind parameters if customer_id is provided
        if ($customer_id !== null && $customer_id > 0) {
            $stmt->bind_param("i", $customer_id);
        }

        // Execute the statement
        $stmt->execute();

        // Get the result
        $result = $stmt->get_result();

        // Fetch all reports
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }

        // Close the statement
        $stmt->close();
    } else {
        throw new Exception('Failed to prepare SQL statement.');
    }

    // Return the reports array as JSON with a success flag
    echo json_encode([
        'success' => true,
        'reports' => $reports
    ]);
} catch (Exception $e) {
    // If there's an error, return a JSON error message
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reports: ' . $e->getMessage()
    ]);
}
