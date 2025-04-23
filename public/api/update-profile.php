<?php
// api/update-profile.php

require_once __DIR__ . '/src/middleware.php';
require_once __DIR__ . '/src/config.php';

header('Content-Type: application/json');

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

$userId = $_SESSION['user_id'];

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

$name = $input['name'] ?? null;
$username = $input['username'] ?? null;
$email = $input['email'] ?? null;
$phone_number = $input['phone_number'] ?? null;

// Validate inputs
if (!$name || !$username) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Name and username are required.']);
    exit();
}

try {
    $db = getDBconnection();

    // Check if the username is already taken by another user
    $stmt = $db->prepare("SELECT id FROM login_users WHERE User_Name = ? AND id != ?");
    $stmt->bind_param('si', $username, $userId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Username is already taken.']);
        exit();
    }
    $stmt->close();

    // Update the user's profile information
    $stmt = $db->prepare("UPDATE login_users SET Name = ?, User_Name = ?, email = ?, phone_number = ? WHERE id = ?");
    $stmt->bind_param('sssii', $name, $username, $email, $phone_number, $userId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
    }

    $stmt->close();
    $db->close();
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
