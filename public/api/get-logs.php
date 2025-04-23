<?php
// get-logs.php - Return user actions logs in JSON

require_once __DIR__ . '/src/middleware.php';
require_once __DIR__ . '/src/config.php';

header('Content-Type: application/json');

// (Optional) check user perms if only admins can see logs
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

try {
    $db = getDBconnection();

    // Maybe you want to limit logs or have them sorted
    $stmt = $db->prepare("
        SELECT id, username, action_type, description, timestamp
        FROM user_actions_log
        ORDER BY timestamp DESC
        LIMIT 100
    ");

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement");
    }

    $result = $stmt->get_result();
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            'id' => (int)$row['id'],
            'userName' => $row['username'],
            'actionType' => $row['action_type'],
            'description' => $row['description'],
            'timestamp' => $row['timestamp'],
        ];
    }

    $stmt->close();
    $db->close();

    echo json_encode(['success' => true, 'logs' => $logs]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
