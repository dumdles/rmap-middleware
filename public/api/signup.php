<?php
// signup.php - API endpoint for sign-up

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $rawData = file_get_contents('php://input');
    // Decode JSON
    $input = json_decode($rawData, true);

    // Extract fields
    $fullName      = trim($input['fullName'] ?? '');
    $username      = trim($input['username'] ?? '');
    $email         = trim($input['email'] ?? '');
    $phoneNumber   = trim($input['phoneNumber'] ?? '');
    $userPassword  = trim($input['password'] ?? '');

    // Basic validation
    if (empty($fullName) || empty($username) || empty($email) || empty($phoneNumber) || empty($userPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }

    // Check if the username or email already exists
    try {
        $db = getDBconnection();
        $stmt = $db->prepare("SELECT * FROM login_users WHERE User_Name = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
            exit();
        }

        // Hash the password
        $passwordHash = password_hash($userPassword, PASSWORD_DEFAULT);

        // Insert user into the sign_up_users table
        $stmt = $db->prepare("INSERT INTO sign_up_users (User_Name, Name, email, phone_number, Password, signup_date) VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $db->error);
        }
        $stmt->bind_param("sssss", $username, $fullName, $email, $phoneNumber, $passwordHash);
        if ($stmt->execute()) {
            // Success
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'User created successfully']);
            log_action(
                "signup",
                $username, // Username is known post-signup
                "User $fullName ($email) has requested to sign up"
            );
        } else {
            // Failed to execute
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
        $db->close();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        log_action(
            "error",
            null,
            "Signup Error: " . $e->getMessage()
        );
    }
} else {
    // Invalid request method
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed']);
    log_action(
        "error",
        null,
        "Failed Signup - Invalid Method: " . $_SERVER['REQUEST_METHOD']
    );
}
