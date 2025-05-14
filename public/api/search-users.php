<?php

require_once __DIR__ . '/../../src/index.php';
require_once __DIR__ . '/../../src/config.php';

// Get the search query
$query = isset($_GET['query']) ? $_GET['query'] : '';

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'No search query provided']);
    exit();
}

try {
    $conn = getDBconnection();

    // Prepare the statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, Name, User_Name, Email FROM login_users WHERE Name LIKE ? OR User_Name LIKE ? OR Email LIKE ?");
    $searchTerm = '%' . $query . '%';
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);

    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $users]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred while searching users']);
}
