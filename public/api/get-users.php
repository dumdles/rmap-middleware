<?php
// get-users.php

require_once __DIR__ . '/../../src/index.php';
require_once __DIR__ . '/../../src/config.php';

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

    // Fetch all users (excluding admins if necessary)
    // Modify the WHERE clause if you have user roles
    $sql = "SELECT id, User_Name, Name, Permissions, email, phone_number FROM login_users";

    $result = $db->query($sql);

    $users = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $users
    ]);
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching users: ' . $e->getMessage()
    ]);
}
