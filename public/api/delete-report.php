<?php
// delete-report.php - API endpoint for soft-deleting a report and its inspections
require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../../src/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Extract report_id and report_number
    $report_id = $input['report_id'] ?? null;
    $report_number = $input['report_number'] ?? null;

    // Validate report_id
    if (!$report_id || !is_numeric($report_id)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Valid report_id is required']);
        exit();
    }

    try {
        $db = getDBconnection();

        // Begin transaction
        $db->begin_transaction();

        // Soft-delete the report
        $stmt1 = $db->prepare("UPDATE report_list SET soft_delete = 'true' WHERE report_id = ? AND soft_delete = 'false'");
        if (!$stmt1) {
            throw new Exception("Prepare statement failed: " . $db->error);
        }
        $stmt1->bind_param("i", $report_id);
        $stmt1->execute();
        log_action("Soft-Deleted Report", $_SESSION['username'] ?? null, "Report Number: $report_number");
        if ($stmt1->affected_rows !== 1) {
            throw new Exception("Report not found or already deleted");
        }

        $stmt1->close();

        // Soft-delete all associated inspections
        $stmt2 = $db->prepare("UPDATE report_details SET soft_delete = 'true' WHERE report_number = ? AND soft_delete = 'false'");
        if (!$stmt2) {
            throw new Exception("Prepare statement failed: " . $db->error);
        }
        $stmt2->bind_param("s", $report_number);
        $stmt2->execute();
        log_action("Soft-Deleted Inspections", $_SESSION['username'] ?? null, "Report Number: $report_number");
        // Optionally, you can check $stmt2->affected_rows if needed
        $stmt2->close();

        // Commit transaction
        $db->commit();
        $db->close();

        // Return success response
        echo json_encode(['success' => true, 'message' => 'Report and associated inspections soft-deleted successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        if (method_exists($db, 'in_transaction') && $db->in_transaction) {
            $db->rollback();
        }
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Error soft-deleting report: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed']);
}
