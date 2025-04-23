<?php
require_once __DIR__ . '/src/middleware.php';
require_once __DIR__ . '/src/config.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $report_id = $data['report_id'];
    $assigned_user_ids = $data['assigned_user_ids']; // An array of user IDs

    // Begin transaction
    $db = getDBconnection();

    // Delete existing assignments for the report
    $deleteSql = "DELETE FROM report_assignments WHERE report_id = ?";
    $stmt = $db->prepare($deleteSql);
    $stmt->bind_param("i", $report_id);
    $stmt->execute();

    // Insert new assignments
    $insertSql = "INSERT INTO report_assignments (report_id, user_id) VALUES (?, ?)";
    $stmt = $db->prepare($insertSql);

    foreach ($assigned_user_ids as $user_id) {
        $stmt->bind_param("ii", $report_id, $user_id);
        $stmt->execute();
    }

    // Commit transaction
    $db->commit();

    echo json_encode(['success' => true, 'message' => 'Report assignments updated successfully']);
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'message' => 'Error updating report assignments: ' . $e->getMessage()]);
}
