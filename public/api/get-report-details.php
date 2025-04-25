<?php
// get-report-details.php
require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../../src/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use GET.'
    ]);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing report ID.'
    ]);
    exit;
}

$reportId = intval($_GET['id']);

try {
    $db = getDBconnection();

    // First fetch the report details
    $stmt = $db->prepare("
        SELECT 
            rl.*,
            c.customer_name,
            c.customer_address,
            c.customerID,
            i.inspector_name,
            con.contractor_name
        FROM report_list rl
        JOIN customers c ON rl.customer_id = c.customerID
        JOIN inspectors i ON rl.inspection_by = i.id
        JOIN contractors con ON c.contractor = con.contractorID
        WHERE rl.report_id = ?
    ");

    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Report not found.'
        ]);
        exit;
    }

    $report = $result->fetch_assoc();

    // Fetch assigned users separately
    $userStmt = $db->prepare("
        SELECT 
            lu.id,
            lu.User_Name,
            lu.Name
        FROM report_assignments ra
        JOIN login_users lu ON ra.user_id = lu.id
        WHERE ra.report_id = ?
    ");

    $userStmt->bind_param("i", $reportId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();

    $assignedUsers = [];
    while ($user = $userResult->fetch_assoc()) {
        $assignedUsers[] = $user;
    }

    // Add assigned users to report object
    $report['assigned_users'] = $assignedUsers;

    // Fetch inspections
    $inspectionStmt = $db->prepare("
        SELECT *
        FROM report_details
        WHERE report_number = ? AND soft_delete = 'false' ORDER BY timestamp ASC;
    ");
    $inspectionStmt->bind_param("s", $report['report_number']);
    $inspectionStmt->execute();
    $inspectionResult = $inspectionStmt->get_result();

    $inspections = [];
    while ($row = $inspectionResult->fetch_assoc()) {
        $inspections[] = $row;
    }

    // Return the data
    echo json_encode([
        'success' => true,
        'report' => $report,
        'inspections' => $inspections
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching report details: ' . $e->getMessage()
    ]);
}
