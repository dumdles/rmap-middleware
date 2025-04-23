<?php
// logout.php - API endpoint for logout
session_start(); // Start the session to access session variables

require_once __DIR__ . '/src/middleware.php';

// Handle preflight (OPTIONS) request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


// Check if a session exists
if (isset($_SESSION['username'])) {
    // Unset all session variables
    $_SESSION = [];

    // Destroy the session
    session_destroy();

    // Clear the token cookie
    setcookie('token', '', time() - 3600, '/', '', false, true);

    // Send a success response
    http_response_code(200);
    echo json_encode(['message' => 'Logout successful']);
} else {
    // If no session exists, send an appropriate response
    http_response_code(400);
    echo json_encode(['message' => 'No active session']);
}
