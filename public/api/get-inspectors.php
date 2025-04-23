<?php
// get-inspectors.php

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config.php';

// Set the response type to JSON
header('Content-Type: application/json');

try {
    // Connect to the database
    $db = getDBconnection();

    // Query to fetch inspectors
    $sql = "SELECT id, inspector_name FROM inspectors";

    $result = $db->query($sql);

    $inspectors = [];

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $inspectors[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $inspectors]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching inspectors: ' . $e->getMessage()]);
}
