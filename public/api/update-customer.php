<?php
// update-customer.php
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use POST.'
    ]);
    log_action("Failed Update Customer - Invalid Method", $_SESSION['username'], "Method: " . $_SERVER['REQUEST_METHOD']);
    exit;
}

$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

// Check if JSON decoding was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.'
    ]);
    log_action("Failed Update Customer - Invalid JSON", $_SESSION['username'], "Payload: $rawData");
    exit;
}

// Validate required fields
$requireFields = ['customerID', 'customer_name', 'customer_address'];
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
    log_action("Failed Update Customer - Missing Fields", $_SESSION['username'], implode(', ', $missingFields));
    exit;
}

$customerID = intval($data['customerID']);
$customer_name = trim($data['customer_name']);
$customer_address = trim($data['customer_address']);
$contact_person = isset($data['contact_person']) ? trim($data['contact_person']) : null;
$contact_email = isset($data['contact_email']) ? trim($data['contact_email']) : null;
$contact_number = isset($data['contact_number']) ? trim($data['contact_number']) : null;

// Additional validation
if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format.'
    ]);
    log_action("Failed Update Customer - Invalid Email", $_SESSION['username'], "Email: $contact_email");
    exit;
}

try {
    $db = getDBconnection();

    // Prepare the UPDATE statement
    $stmt = $db->prepare("UPDATE customers SET customer_name = ?, customer_address = ?, contact_person = ?, contact_email = ?, contact_number = ? WHERE customerID = ?")
        or throw new Exception("Prepare statement failed: " . $db->error);

    $stmt->bind_param(
        "sssssi",
        $customer_name,
        $customer_address,
        $contact_person,
        $contact_email,
        $contact_number,
        $customerID
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows === 1) {
            http_response_code(200); // OK
            echo json_encode([
                'success' => true,
                'message' => 'Customer updated successfully.'
            ]);
            log_action("Update Customer", $_SESSION['username'], "Customer ID: $customerID");
        } else {
            // No rows affected (possibly same data or customer not found)
            http_response_code(404); // Not Found
            echo json_encode([
                'success' => false,
                'message' => 'Customer not found or no changes made.'
            ]);
            log_action("Failed Update Customer - Not Found/No Changes", $_SESSION['username'], "Customer ID: $customerID");
        }
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
    $db->close();
} catch (Exception $e) {
    // Handle exceptions
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Error updating customer: ' . $e->getMessage()
    ]);
    log_action("Error Update Customer", $_SESSION['username'], "Customer ID: $customerID | Error: " . $e->getMessage());
}
