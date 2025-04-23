<?php
// api/update-report-status.php - API endpoint to update report status

require_once __DIR__ . '/../middleware.php'; // Include the middleware
require_once __DIR__ . '/../config.php';

// Set the response type to JSON
header('Content-Type: application/json');

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    log_action("Failed Update Report Status - Invalid Method", $_SESSION['username'] ?? null, "Method: " . $_SERVER['REQUEST_METHOD']);
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
    log_action("Failed Update Report Status - Invalid JSON", $_SESSION['username'] ?? null, "Payload: $rawData");
    exit();
}

// Validate required fields
$requiredFields = ['report_id', 'new_status'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missingFields)]);
    log_action("Failed Update Report Status - Missing Fields", $_SESSION['username'] ?? null, implode(', ', $missingFields));
    exit();
}

// Extract and sanitize fields
$report_id = intval($data['report_id']);
$new_status = intval($data['new_status']);

try {
    // Get DB connection
    $db = getDBconnection();

    // Fetch the report_number for the given report_id
    $selectStmt = $db->prepare("SELECT report_number FROM report_list WHERE report_id = ?");
    $selectStmt->bind_param("i", $report_id);
    if (!$selectStmt->execute()) {
        throw new Exception("Failed to fetch report_number: " . $selectStmt->error);
    }
    $result = $selectStmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $report_number = $row['report_number']; // e.g. "IRTS-2412-01"
    } else {
        // If no matching row found
        throw new Exception("Report not found for report_id = $report_id");
    }
    $selectStmt->close();

    $statusMap = [
        0 => "In Progress",
        1 => "Completed",
        2 => "Submitted",
    ];
    $statusText = $statusMap[$new_status] ?? "Unknown"; // fallback if not 0-2

    // Prepare the UPDATE statement
    $stmt = $db->prepare("UPDATE report_list SET final_report_status = ? WHERE report_id = ?")
        or throw new Exception("Prepare statement failed: " . $db->error);

    // Bind parameters
    $stmt->bind_param("ii", $new_status, $report_id);

    // Execute the statement
    if ($stmt->execute()) {
        http_response_code(200); // OK
        echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
        $reportIdText = "Updated report $report_number to $statusText";
        log_action("update", $_SESSION['username'] ?? null, $reportIdText);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    // Close the statement
    $stmt->close();
    $db->close();
} catch (Exception $e) {
    // Handle exceptions
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()]);
    log_action("error", $_SESSION['username'] ?? null, "Update Report Status failed: " . $e->getMessage());
}
