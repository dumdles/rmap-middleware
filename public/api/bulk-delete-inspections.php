<?php
// bulk-delete-inspections.php - API endpoint for bulk soft-deleting inspections

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);  // OK for OPTIONS requests
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $rawData = file_get_contents('php://input');

    // Decode JSON
    $input = json_decode($rawData, true);

    // Extract inspection_ids
    $inspection_ids = $input['inspection_ids'] ?? null;

    // Validate inspection_ids
    if (!$inspection_ids || !is_array($inspection_ids)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Valid inspection_ids array is required']);
        exit();
    }

    try {
        $db = getDBconnection();

        $db->begin_transaction();

        // Fetch the report_number for the given inspection_id
        $placeholders = implode(',', array_fill(0, count($inspection_ids), '?'));
        $types = str_repeat('s', count($inspection_ids)); // 's' for string (UUID)

        $stmt = $db->prepare("SELECT inspection_id, report_number FROM report_details WHERE inspection_id IN ($placeholders)");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $db->error);
        }

        // Bind parameters dynamically
        $stmt->bind_param($types, ...$inspection_ids);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if all inspection_ids exist
        if ($result->num_rows !== count($inspection_ids)) {
            throw new Exception("Some inspection_ids do not exist or have already been deleted.");
        }

        // Collect report_numbers
        $report_counts = []; // Associative array: report_number => count
        while ($row = $result->fetch_assoc()) {
            $report_number = $row['report_number'];
            if (isset($report_counts[$report_number])) {
                $report_counts[$report_number]++;
            } else {
                $report_counts[$report_number] = 1;
            }
        }

        $stmt->close();

        $stmt = $db->prepare("UPDATE report_details SET soft_delete = 'true' WHERE inspection_id IN ($placeholders) AND soft_delete = 'false'");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $db->error);
        }

        // Bind parameters dynamically
        $stmt->bind_param($types, ...$inspection_ids);
        $stmt->execute();

        $affected_rows = $stmt->affected_rows;

        $stmt->close();

        // Commit transaction
        $db->commit();
        $db->close();

        $messages = [];
        foreach ($report_counts as $report_number => $count) {
            $messages[] = "Deleted $count inspections from report $report_number";
        }
        $finalMessage = implode('; ', $messages);

        echo json_encode(['success' => true, 'message' => "$affected_rows inspections soft-deleted successfully"]);

        // Include report_numbers in log description if reasonable
        if ($affected_rows > 0 && count($report_counts) <= 5) { // Adjust threshold as needed
            foreach ($report_counts as $report_number => $count) {
                $logDescription .= ": $count inspections from report $report_number";
            }
        }
        log_action(
            "delete",
            $_SESSION['username'] ?? null,
            $logDescription
        );
    } catch (Exception $e) {
        // Rollback transaction in case of error
        if (isset($db)) {
            $db->rollback();
        }

        // Handle exceptions
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'success' => false,
            'message' => 'Error soft-deleting inspections: ' . $e->getMessage()
        ]);
        log_action(
            "error",
            $_SESSION['username'] ?? null,
            "Error Bulk Delete Inspections: " . $e->getMessage()
        );
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed']);

    log_action(
        "error",
        $_SESSION['username'] ?? null,
        "Failed Bulk Delete Inspections - Invalid Method: " . $_SERVER['REQUEST_METHOD']
    );
}
