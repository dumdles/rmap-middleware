<?php
// get-contractors.php

// Include the database configuration file
require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../../src/config.php';

try {
    // Connect to the database
    $db = getDBconnection(); // This should be your function to connect to the DB

    // Prepare the SQL query to get contractor details
    $sql = "SELECT contractorID, contractor_name FROM contractors";

    // Execute the query
    $result = $db->query($sql);

    // Initialize an empty array to store contractor data
    $contractors = [];

    // Check if there are any results
    if ($result && $result->num_rows > 0) {
        // Loop through the result set and fetch all rows
        while ($row = $result->fetch_assoc()) {
            $contractors[] = $row;
        }
    }

    // Send the contractor data as JSON
    echo json_encode([
        'success' => true,
        'data' => $contractors
    ]);
} catch (Exception $e) {
    // If there's an error, return a JSON error message
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching contractors: ' . $e->getMessage()
    ]);
}
