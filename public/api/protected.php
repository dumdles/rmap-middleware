<?php
error_log(print_r(getallheaders(), true));

require_once __DIR__ . '/../middleware.php';

header('Content-Type: application/json');

// If authentication passes, you can access user info from $_SESSION
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

echo json_encode([
    'success' => true,
    'message' => 'Access granted',
    'data' => [
        'userId' => $userId,
        'username' => $username
    ]
]);
