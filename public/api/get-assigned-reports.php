<?php
// get-assigned-reports.php

require_once __DIR__ . '/../../src/middleware.php'; // Handles authentication and sets $_SESSION
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');

// Ensure the request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use GET.'
    ]);
    exit;
}

try {
    $db = getDBconnection();

    // Retrieve user_id from session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User ID not set in session.");
    }
    $user_id = $_SESSION['user_id'];
    error_log("User ID from session: " . $user_id); // Debug user_id

    // Adjusted SQL query
    $sql = "SELECT
        rl.report_number,
        rl.report_id,
        rl.customer_id,
        c.customer_name,
        rl.inspection_by AS inspection_by_id,
        i.inspector_name AS inspection_by_name,
        rl.inspection_date,
        rl.inspection_end_date,
        rl.final_report_status,
        con.contractor_name,
        COUNT(rd.inspection_id) AS inspection_count,
        GROUP_CONCAT(DISTINCT CONCAT(u_assigned.id, '::', u_assigned.User_Name, '::', u_assigned.Name) SEPARATOR '||') AS assigned_users_raw
    FROM report_list rl
    JOIN customers c ON rl.customer_id = c.customerID
    JOIN contractors con ON c.contractor = con.contractorID
    JOIN report_assignments ra ON rl.report_id = ra.report_id
    JOIN inspectors i ON rl.inspection_by = i.id
    LEFT JOIN report_details rd ON rl.report_number = rd.report_number AND rd.soft_delete = 'false'
    LEFT JOIN report_assignments ra_assigned ON rl.report_id = ra_assigned.report_id
    LEFT JOIN login_users u_assigned ON ra_assigned.user_id = u_assigned.id
    WHERE rl.soft_delete = 'false' 
    AND ra.user_id = ? 
    AND rl.final_report_status = 0.0
    GROUP BY rl.report_id
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: (" . $db->errno . ") " . $db->error);
        throw new Exception("Database prepare failed.");
    }

    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        throw new Exception("Database execute failed.");
    }

    $result = $stmt->get_result();
    if (!$result) {
        error_log("Getting result set failed: (" . $stmt->errno . ") " . $stmt->error);
        throw new Exception("Getting result set failed.");
    }

    $reports = [];
    while ($row = $result->fetch_assoc()) {
        // Log the raw assigned users data
        error_log("Report ID {$row['report_id']} - assigned_users_raw: " . $row['assigned_users_raw']);

        // Parse assigned_users_raw
        $assigned_users = [];
        if (!empty($row['assigned_users_raw'])) {
            $users_raw = explode('||', $row['assigned_users_raw']);
            foreach ($users_raw as $user_raw) {
                $user_parts = explode('::', $user_raw);
                if (count($user_parts) === 3) {
                    list($id, $userName, $name) = $user_parts;
                    $assigned_users[] = [
                        'id' => intval($id),
                        'userName' => $userName,
                        'name' => $name,
                    ];
                }
            }
        }
        $row['assigned_users'] = $assigned_users;
        unset($row['assigned_users_raw']); // Remove the raw field
        $reports[] = $row;
    }


    error_log("Number of reports fetched: " . count($reports)); // Debug count

    echo json_encode(['success' => true, 'reports' => $reports]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get-assigned-reports.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching assigned reports: ' . $e->getMessage()]);
}
