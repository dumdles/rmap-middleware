<?php
// api/update-report.php - API endpoint to update report details

require_once __DIR__ . '/../middleware.php'; // Include the middleware
require_once __DIR__ . '/../config.php';

// Set the response type to JSON
header('Content-Type: application/json');

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    log_action("Failed Update Report - Invalid Method", $_SESSION['username'] ?? null, "Method: " . $_SERVER['REQUEST_METHOD']);
    exit();
}

// Get the raw POST data
$rawData = file_get_contents("php://input");

// Decode JSON
$data = json_decode($rawData, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    log_action("Failed Update Report - Invalid JSON", $_SESSION['username'] ?? null, "Payload: $rawData");
    exit();
}

// Validate required fields
$requiredFields = ['report_id', 'customer_name', 'inspection_by', 'inspection_date'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missingFields)]);
    log_action("Failed Update Report - Missing Fields", $_SESSION['username'] ?? null, implode(', ', $missingFields));
    exit();
}

// Extract and sanitize fields
$report_id = intval($data['report_id']);
$customer_id = intval($data['customer_name']);
$inspector_id = intval($data['inspection_by']);
$inspection_date = $data['inspection_date'];
$inspection_end_date = $data['inspection_end_date'];
$remarks = trim($data['remarks']);

try {
    // Get DB connection
    $db = getDBconnection();

    // Prepare the UPDATE statement
    $stmt = $db->prepare("UPDATE report_list SET customer_id = ?, inspection_by = ?, inspection_date = ?, inspection_end_date = ?, remarks = ? WHERE report_id = ?")
        or throw new Exception("Prepare statement failed: " . $db->error);

    // Bind parameters
    $stmt->bind_param("iisssi", $customer_id, $inspector_id, $inspection_date, $inspection_end_date, $remarks, $report_id);

    // Execute the statement
    if ($stmt->execute()) {
        http_response_code(200); // OK
        echo json_encode(['success' => true, 'message' => 'Report updated successfully.']);
        log_action("Update Report", $_SESSION['username'] ?? null, "Report ID: $report_id");
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    // Close the statement
    $stmt->close();
    $db->close();
} catch (Exception $e) {
    // Handle exceptions
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error updating report: ' . $e->getMessage()]);
    log_action("Error Update Report", $_SESSION['username'] ?? null, "Error: " . $e->getMessage());
}
?>
