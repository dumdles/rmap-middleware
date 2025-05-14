<?php
// api/update-user-role.php

require_once __DIR__ . '/../../src/index.php';
require_once __DIR__ . '/../../src/config.php';

header('Content-Type: application/json');

// Check if the user is an admin
if (!isset($_SESSION['permissions']) || $_SESSION['permissions'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Admin access required']);
    exit();
}

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

$userId = $input['userId'] ?? null;
$permissions = $input['Permissions'] ?? null;

if (!$userId || !isset($permissions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID and Permissions are required']);
    exit();
}

try {
    $db = getDBconnection();

    $stmt = $db->prepare("UPDATE login_users SET Permissions = ? WHERE id = ?");
    $stmt->bind_param('ii', $permissions, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User role updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update user role']);
    }

    $stmt->close();
    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
