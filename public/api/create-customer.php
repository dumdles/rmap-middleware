<?php
// api/create_customer.php

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/middleware.php';

// Set the response type to JSON
header('Content-Type: application/json');

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use POST.'
    ]);
    log_action("error", $_SESSION['username'] ?? null, "Failed Create Customer - Invalid Method: " . $_SERVER['REQUEST_METHOD']);
    exit;
}

// Get the raw POST data
$rawData = file_get_contents("php://input");

// Decode JSON
$data = json_decode($rawData, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.'
    ]);
    log_action("error", $_SESSION['username'] ?? null, "Failed Create Customer - Invalid JSON. Payload: $rawData");
    exit;
}

// Validate required fields
$requiredFields = ['customer_name', 'customer_address'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        $missingFields[] = ucfirst(str_replace('_', ' ', $field));
    }
}

if (!empty($missingFields)) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: ' . implode(', ', $missingFields)
    ]);
    log_action("error", $_SESSION['username'] ?? null, "Failed Create Customer - Missing Fields: " . implode(', ', $missingFields));
    exit;
}

// Extract and sanitize fields
$customer_name = trim($data['customer_name']);
$customer_address = trim($data['customer_address']);
$contractorID = trim($data['contractorID']);
$contact_person = isset($data['contact_person']) ? trim($data['contact_person']) : null;
$contact_email = isset($data['contact_email']) ? trim($data['contact_email']) : null;
$contact_number = isset($data['contact_number']) ? trim($data['contact_number']) : null;

// Additional validation (e.g., email format)
if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format.'
    ]);
    exit;
}

try {
    // Connect to the database
    $db = getDBconnection();

    // Check if contractorID exists
    $stmt = $db->prepare("SELECT contractorID FROM contractors WHERE contractorID = ?");
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $db->error);
    }
    $stmt->bind_param("s", $contractorID);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        http_response_code(400); // Bad Request
        echo json_encode([
            'success' => false,
            'message' => 'Invalid contractorID.'
        ]);
        exit;
    }

    $stmt->close();

    // Prepare the INSERT statement
    $stmt = $db->prepare("INSERT INTO customers (customer_name, customer_address, contractor, contact_person, contact_email, contact_number) VALUES (?, ?, ?, ?, ?, ?)")
        or throw new Exception("Prepare statement failed: " . $db->error);

    // Bind parameters (s = string, etc.)
    $stmt->bind_param(
        "ssssss",
        $customer_name,
        $customer_address,
        $contractorID,
        $contact_person,
        $contact_email,
        $contact_number
    );

    // Execute the statement
    if ($stmt->execute()) {
        // Success
        echo json_encode([
            'success' => true,
            'message' => 'Customer created successfully.',
            'customerID' => $stmt->insert_id // Return the new customerID
        ]);
        log_action("create", $_SESSION['username'] ?? null, "Created customer: {$customer_name}");
    } else {
        // Failed to execute
        throw new Exception("Execute failed: " . $stmt->error);
    }

    // Close the statement
    $stmt->close();
} catch (Exception $e) {
    // Handle exceptions
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Error creating customer: ' . $e->getMessage()
    ]);
    log_action("error", $_SESSION['username'] ?? null, "Error Create Customer: " . $e->getMessage());
}
