<?php
// api/change-password.php

require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../../src/config.php';

header('Content-Type: application/json');

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

$userId = $_SESSION['userID'];

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

$currentPassword = $input['currentPassword'] ?? null;
$newPassword = $input['newPassword'] ?? null;
$confirmPassword = $input['confirmPassword'] ?? null;

// Validate inputs
if (!$currentPassword || !$newPassword || !$confirmPassword) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
    exit();
}

if ($newPassword !== $confirmPassword) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    exit();
}

try {
    $db = getDBconnection();

    // Fetch the user's current password
    $stmt = $db->prepare("SELECT Password FROM login_users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($hashedPassword);
    $stmt->fetch();
    $stmt->close();

    // Verify the current password
    if (!password_verify($currentPassword, $hashedPassword)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit();
    }

    // Hash the new password
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update the user's password
    $stmt = $db->prepare("UPDATE login_users SET Password = ? WHERE id = ?");
    $stmt->bind_param('si', $newHashedPassword, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to change password.']);
    }

    $stmt->close();
    $db->close();
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
