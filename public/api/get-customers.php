<?php
// get-customers.php

require_once __DIR__ . '/../../src/index.php';
require_once __DIR__ . '/../../src/config.php';

// Set the response type to JSON
header('Content-Type: application/json');

try {
    // Connect to the database
    $db = getDBconnection(); // This should be your function to connect to the DB

    // Prepare the SQL query to get customer details
    $sql = "
    SELECT 
        c.customerID, 
        c.customer_name, 
        c.customer_address, 
        c.contact_person, 
        c.contact_email, 
        c.contact_number, 
        ct.contractor_name AS contractor
    FROM 
        customers c
    LEFT JOIN 
        contractors ct ON c.contractor = ct.contractorID
    ";

    // Execute the query
    $result = $db->query($sql);

    // Initialize an empty array to store customer data
    $customers = [];

    // Check if there are any results
    if ($result->num_rows > 0) {
        // Loop through the result set and fetch all rows
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
    }

    // Send the customer data as JSON
    echo json_encode([
        'success' => true,
        'data' => $customers
    ]);
} catch (Exception $e) {
    // If there's an error, return a JSON error message
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching customers: ' . $e->getMessage()
    ]);
}
