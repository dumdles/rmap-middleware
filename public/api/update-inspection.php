<?php
// update-inspection.php - API endpoint for updating an inspection

require_once __DIR__ . '/src/middleware.php';
require_once __DIR__ . '/src/config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);  // OK for OPTIONS requests
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Extract inspection data
    $inspection_id = $input['inspection_id'] ?? null;
    $location = $input['location'] ?? null;
    $equipment_name = $input['equipment_name'] ?? null;
    $description = $input['description'] ?? null;
    $temp_category = $input['temp_category'] ?? null;
    $ref_image = $input['ref_image'] ?? null;
    $comp_type = $input['comp_type'] ?? null;
    $L1 = $input['L1'] ?? null;
    $L2 = $input['L2'] ?? null;
    $L3 = $input['L3'] ?? null;
    $neutral = $input['neutral'] ?? null;
    $inspection_type = $input['inspection_type'] ?? 'operational';

    // Validate required fields
    if (!$inspection_id) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Valid inspection_id is required']);
        exit();
    }


    // Optionally validate inspection_type
    $allowed_types = ['operational', 'nop', 'nl', 'na'];
    if (!in_array($inspection_type, $allowed_types)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Invalid inspection_type provided']);
        exit();
    }

    try {
        $db = getDBconnection();

        // Prepare the UPDATE statement
        $stmt = $db->prepare("
            UPDATE report_details 
            SET location = ?, equipment_name = ?, description = ?, temp_category = ?, ref_image = ?, 
                comp_type = ?, L1 = ?, L2 = ?, L3 = ?, neutral = ?, inspection_type = ?
            WHERE inspection_id = ? AND soft_delete = 'false'
        ");

        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $db->error);
        }

        // Bind parameters: 
        // s = string, d = double, we will cast numeric fields to double where necessary
        // ref_image, L1, L2, L3, neutral can be null or numeric. If null is possible, bind as string and handle cast?
        // For simplicity, use 's' for all fields except known numeric that must be numeric. 
        // If null is allowed, MySQLi will insert NULL if parameter is null.

        // Convert numeric fields properly:
        $ref_image = ($ref_image === null || $ref_image === '') ? null : (int)$ref_image;
        $L1 = ($L1 === null || $L1 === '') ? null : (float)$L1;
        $L2 = ($L2 === null || $L2 === '') ? null : (float)$L2;
        $L3 = ($L3 === null || $L3 === '') ? null : (float)$L3;
        $neutral = ($neutral === null || $neutral === '') ? null : (float)$neutral;

        $stmt->bind_param(
            "ssssssddddss",
            $location,
            $equipment_name,
            $description,
            $temp_category,
            $ref_image,
            $comp_type,
            $L1,
            $L2,
            $L3,
            $neutral,
            $inspection_type,
            $inspection_id
        );
        $stmt->execute();

        if ($stmt->affected_rows === 1) {
            // Successfully updated
            echo json_encode(['success' => true, 'message' => 'Inspection updated successfully']);
        } else {
            // Inspection not found or no changes made
            echo json_encode(['success' => false, 'message' => 'Inspection not found or no changes made']);
        }

        $stmt->close();
        $db->close();
    } catch (Exception $e) {
        // Handle exceptions
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Error updating inspection: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST method is allowed']);
}
