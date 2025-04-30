<?php
// reject-sign-up-request.php

require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../../src/config.php';

header('Content-Type: application/json');

// Check if the user is an admin
if (!isset($_SESSION['permissions']) || $_SESSION['permissions'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Admin access required']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $input['userId'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

try {
    $db = getDBconnection();
    
    // First, fetch the user info before deleting
    $stmt = $db->prepare("SELECT User_Name FROM sign_up_users WHERE ID = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $username = $user['User_Name'] ?? 'Unknown';
    $stmt->close();
    
    // Now delete the user
    $deleteStmt = $db->prepare("DELETE FROM sign_up_users WHERE ID = ?");
    $deleteStmt->bind_param('i', $userId);
    
    if ($deleteStmt->execute()) {
        if ($deleteStmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'User rejected and removed from sign-up requests']);
            log_action(
                "reject_signup",
                $_SESSION['username'] ?? null,
                "Rejected signup request from: @$username"
            );
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found in sign-up requests']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete user from sign-up requests']);
    }
    
    // Close statement and connection
    $deleteStmt->close();
    $db->close();
} catch (Exception $e) {
    // No need for rollback as there's no transaction
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    log_action(
        "error",
        $_SESSION['username'] ?? null,
        "Reject Signup Error - User ID $userId: " . $e->getMessage()
    );
}
