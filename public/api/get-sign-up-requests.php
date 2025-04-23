<?php
// api/get-sign-up-requests.php

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $db = getDBconnection();

    $stmt = $db->prepare("SELECT ID, User_Name, Name, email, signup_date FROM sign_up_users");
    $stmt->execute();
    $result = $stmt->get_result();

    $signUpRequests = [];
    while ($row = $result->fetch_assoc()) {
        $signupDate = $row['signup_date'];

        // Create a DateTime object from the signup date
        $dateTime = new DateTime($signupDate, new DateTimeZone('Asia/Singapore')); // Replace with your server's time zone if different
        $dateTime->setTimezone(new DateTimeZone('UTC')); // Convert to UTC
        $row['signup_date'] = $dateTime->format('c'); // ISO 8601 format

        $signUpRequests[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => $signUpRequests
    ]);

    $stmt->close();
    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
