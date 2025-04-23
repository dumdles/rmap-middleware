<?php
// session_status.php - API to check if a user is logged in

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config.php';


// If authenticated, respond with user details
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Unknown';
$email = $_SESSION['email'] ?? 'Unknown';

echo json_encode([
    'loggedIn' => true,
    'user_id' => $user_id,
    'username' => $username,
    'email' => $email
]);
