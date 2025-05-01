<?php
// upload-report.php

$raw_data = file_get_contents("php://input");
error_log("Raw incoming data: " . $raw_data);

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Include database and JWT authentication files
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../vendor/autoload.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Get DB connection
$db = getDBconnection();

// Get JWT token from Authorization header
$headers = getallheaders();
$headers = array_change_key_case($headers, CASE_LOWER); // Convert all header keys to lowercase

if (!isset($headers['authorization'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized. No token provided."]);
    exit();
}

$authHeader = $headers['authorization'];

if (isset($_SERVER['Authorization'])) {
    $authHeader = $_SERVER['Authorization'];
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $requestHeaders = apache_request_headers();
    if (isset($requestHeaders['Authorization'])) {
        $authHeader = $requestHeaders['Authorization'];
    }
}

if ($authHeader === null) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized. No token provided."]);
    exit();
}

$arr = explode(" ", $authHeader);
if (count($arr) != 2) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized. Invalid token format."]);
    exit();
}
$jwt = $arr[1];

// Validate JWT token
try {
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
    $username = $decoded->username;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized. " . $e->getMessage()]);
    exit();
}

// Get the posted data
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "No data provided."]);
    exit();
}

// Validate required fields in report
$requiredReportFields = ['report_id', 'report_number', 'customer_id', 'inspection_by_id', 'inspection_date', 'final_report_status'];
foreach ($requiredReportFields as $field) {
    if (!isset($data['report'][$field])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Missing required field in report: $field"]);
        exit();
    }
}

// Start database transaction
$db->autocommit(FALSE);

try {
    // Insert or update the report
    $report = $data['report'];

    // Extract fields from the report data
    $reportId = $report['report_id'];
    $reportNumber = $report['report_number'];
    $customerId = $report['customer_id'];
    $inspectionById = $report['inspection_by_id'];
    $inspectionDate = $report['inspection_date'];
    $inspectionEndDate = $report['inspection_end_date'];
    $finalReportStatus = $report['final_report_status'];
    $remarks = $report['remarks'];
    $softDelete = $report['soft_delete'];

    // Prepare the SQL statement with placeholders, excluding contractor_name
    $stmt = $db->prepare("
            INSERT INTO report_list (
            report_id, report_number, customer_id,
            inspection_date, inspection_end_date, inspection_by,
            final_report_status, remarks, soft_delete
        ) VALUES (
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?
        )
        ON DUPLICATE KEY UPDATE
            customer_id = VALUES(customer_id),
            inspection_date = VALUES(inspection_date),
            inspection_end_date = VALUES(inspection_end_date),
            inspection_by = VALUES(inspection_by),
            final_report_status = VALUES(final_report_status),
            remarks = VALUES(remarks),
            soft_delete = VALUES(soft_delete)
        ");


    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }

    $stmt->bind_param(
        "isssssiss",
        $reportId,
        $reportNumber,
        $customerId,
        $inspectionDate,
        $inspectionEndDate,
        $inspectionById,
        $finalReportStatus,
        $remarks,
        $softDelete
    );


    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();

    // Insert inspections
    $inspectionCount = 0;

    if (isset($data['inspections']) && is_array($data['inspections'])) {
        $inspectionCount = count($data['inspections']);

        foreach ($data['inspections'] as $inspection) {
            // Add detailed logging right after we get the inspection data
            error_log("Raw inspection data: " . json_encode($inspection));

            // Validate required fields in inspection
            $requiredInspectionFields = ['inspection_id', 'report_number', 'timestamp'];
            foreach ($requiredInspectionFields as $field) {
                if (!isset($inspection[$field])) {
                    throw new Exception("Missing required field in inspection: $field");
                }
            }

            // Prepare the insert statement
            $inspectionStmt = $db->prepare("
            INSERT INTO report_details (
                inspection_id, report_number, location, equipment_name,
                description, temp_category, ref_image, comp_type, L1, L2, L3,
                neutral, soft_delete, remarks, timestamp, inspection_type
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?
            )
            ON DUPLICATE KEY UPDATE
                location = VALUES(location),
                equipment_name = VALUES(equipment_name),
                description = VALUES(description),
                temp_category = VALUES(temp_category),
                ref_image = VALUES(ref_image),
                comp_type = VALUES(comp_type),
                L1 = VALUES(L1),
                L2 = VALUES(L2),
                L3 = VALUES(L3),
                neutral = VALUES(neutral),
                soft_delete = VALUES(soft_delete),
                remarks = VALUES(remarks),
                timestamp = VALUES(timestamp),
                inspection_type = VALUES(inspection_type)
        ");

            if (!$inspectionStmt) {
                throw new Exception("Prepare failed for inspection: " . $db->error);
            }

            // Extract and cast variables from the $inspection array
            $inspection_id = (string)$inspection['inspection_id'];
            $report_number = (string)$inspection['report_number'];
            $location = isset($inspection['location']) ? (string)$inspection['location'] : '';
            $equipment_name = isset($inspection['equipment_name']) ? (string)$inspection['equipment_name'] : '';
            $description = isset($inspection['description']) ? (string)$inspection['description'] : '';
            $temp_category = isset($inspection['temp_category']) ? (string)$inspection['temp_category'] : '';
            $ref_image = isset($inspection['ref_image']) ? (int)$inspection['ref_image'] : null;
            $comp_type = isset($inspection['comp_type']) ? (string)$inspection['comp_type'] : '';
            $L1 = isset($inspection['L1']) ? (float)$inspection['L1'] : null;
            $L2 = isset($inspection['L2']) ? (float)$inspection['L2'] : null;
            $L3 = isset($inspection['L3']) ? (float)$inspection['L3'] : null;
            $neutral = isset($inspection['neutral']) ? (float)$inspection['neutral'] : null;
            $soft_delete = isset($inspection['soft_delete']) ? (string)$inspection['soft_delete'] : 'false';
            $remarks = isset($inspection['remarks']) ? (string)$inspection['remarks'] : '';
            $timestamp = isset($inspection['timestamp']) ? (int)$inspection['timestamp'] : time();
            // Default to 'operational' if not provided
            $inspection_type = isset($inspection['inspection_type']) ? (string)$inspection['inspection_type'] : 'operational';

            error_log("Processed inspection_id (length: " . strlen($inspection_id) . "): " . $inspection_id);

            // Validate UUID format (optional, depending on your needs)
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $inspection_id)) {
                error_log("Invalid UUID format detected: " . $inspection_id);
            }

            // Prepare parameter types and values
            $types = "ssssssisssssssss";

            // Convert null doubles to strings or leave as NULL
            foreach (['L1', 'L2', 'L3', 'neutral'] as $key) {
                if ($$key !== null) {
                    $$key = strval($$key);
                }
            }

            $values = [
                &$inspection_id,
                &$report_number,
                &$location,
                &$equipment_name,
                &$description,
                &$temp_category,
                &$ref_image,
                &$comp_type,
                &$L1,
                &$L2,
                &$L3,
                &$neutral,
                &$soft_delete,
                &$remarks,
                &$timestamp,
                &$inspection_type
            ];

            $inspectionStmt->bind_param($types, ...$values);

            if (!$inspectionStmt->execute()) {
                error_log("MySQL Error: " . $inspectionStmt->error);
                error_log("MySQL Error Number: " . $inspectionStmt->errno);
                throw new Exception("Execute failed for inspection ID " . $inspection_id . ": " . $inspectionStmt->error);
            } else {
                // Log successful insertion
                error_log("Successfully inserted/updated inspection with ID: " . $inspection_id);
            }

            $inspectionStmt->close();
        }
    }

    // Commit the transaction
    $db->commit();

    // Enable autocommit again
    $db->autocommit(TRUE);

    // Return success response
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Report and inspections uploaded successfully."]);

    // Log the action
    // "Uploaded X inspections to report IRTS-2412-01"
    $countText = $inspectionCount . " inspections";
    $logDescription = "Uploaded $countText to report $reportNumber";

    log_action("upload", $_SESSION['username'] ?? null, $logDescription);

    // After binding parameters
    error_log("Inserting report: report_id={$report['report_id']}, report_number={$report['report_number']}, customer_id={$report['customer_id']}, inspection_date={$report['inspection_date']}, inspection_end_date={$report['inspection_end_date']}, inspection_by_id={$report['inspection_by_id']}, final_report_status={$report['final_report_status']}, remarks={$report['remarks']}, soft_delete={$report['soft_delete']}");
} catch (Exception $e) {
    // Rollback the transaction
    $db->rollback();
    $db->autocommit(TRUE);

    // Return error response
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error uploading report: " . $e->getMessage()]);
    log_action("error", $_SESSION['username'] ?? null, "Upload Report failed: " . $e->getMessage());
}
