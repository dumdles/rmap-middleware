<?php
// approve-sign-up-request.php

require_once __DIR__ . '/src/middleware.php';
require_once __DIR__ . '/src/config.php';

header('Content-Type: application/json');

// Check if the user is an admin
if (!isset($_SESSION['permissions']) || $_SESSION['permissions'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Admin access required']);
    exit();
}

$rawInput = file_get_contents('php://input');
error_log('Raw input: ' . $rawInput);

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('JSON decode error: ' . json_last_error_msg());
}

error_log('Received input: ' . print_r($input, true));

$userId = $input['userId'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

try {
    $db = getDBconnection();

    // Start transaction
    $db->begin_transaction();

    // Fetch user data from sign_up_users
    $stmt = $db->prepare("SELECT * FROM sign_up_users WHERE ID = ?");
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $db->error);
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $signUpUser = $result->fetch_assoc();

    if (!$signUpUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found in sign-up requests']);
        $stmt->close();
        $db->rollback();
        exit();
    }
    $stmt->close();

    // Check for existing username or email in login_users
    $checkStmt = $db->prepare("SELECT ID FROM login_users WHERE User_Name = ? OR email = ?");
    $checkStmt->bind_param('ss', $signUpUser['User_Name'], $signUpUser['email']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'A user with this username or email already exists']);
        $db->rollback();
        exit();
    }

    // Prepare the insert statement
    $insertStmt = $db->prepare("
        INSERT INTO login_users (
            Name, 
            User_Name, 
            Password, 
            Permissions, 
            email, 
            phone_number,
            signup_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // Set default Permissions to 0 (regular user)
    $permissions = 0;

    // Bind parameters
    $insertStmt->bind_param(
        'sssisss',
        $signUpUser['Name'],
        $signUpUser['User_Name'],
        $signUpUser['Password'],
        $permissions,
        $signUpUser['email'],
        $signUpUser['phone_number'],
        $signUpUser['signup_date']
    );

    if ($insertStmt->execute()) {
        // Delete the user from sign_up_users
        $deleteStmt = $db->prepare("DELETE FROM sign_up_users WHERE ID = ?");
        $deleteStmt->bind_param('i', $userId);
        $deleteStmt->execute();

        // Commit transaction
        $db->commit();

        echo json_encode(['success' => true, 'message' => 'User approved and added to login_users']);

        // Log the successful approval
        // TODO: Edit the log statement to include the new login user name
        log_action(
            "approve_signup",
            $_SESSION['username'] ?? null,
            "New user added: @" . $signUpUser['User_Name']
        );
    } else {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to insert user into login_users']);
    }

    // Close statements and connection
    $stmt->close();
    $checkStmt->close();
    $insertStmt->close();
    $deleteStmt->close();
    $db->close();
} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    log_action(
        "error",
        $_SESSION['username'] ?? null,
        "Approve Signup Error - User ID $userId: " . $e->getMessage()
    );
}
