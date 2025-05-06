<?php
// inference-api.php

require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../../src/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Use POST.'
    ]);
    exit;
}

$input = file_get_contents("php://input");
$data = json_decode($input, true);
$action = $data['action'] ?? '';

if ($action === "create_inference_batch") {
    // Expected: excel_filename, upload_timestamp
    $excelFilename = $data['excel_filename'] ?? '';
    $uploadTimestamp = $data['upload_timestamp'] ?? 0;
    if (!$excelFilename || !$uploadTimestamp) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing parameters for creating batch.'
        ]);
        exit;
    }
    $db = getDBconnection();
    $stmt = $db->prepare("INSERT INTO inference_batches (excel_filename, upload_timestamp) VALUES (?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Prepare failed: ' . $db->error
        ]);
        exit;
    }
    $stmt->bind_param("si", $excelFilename, $uploadTimestamp);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Execute failed: ' . $stmt->error
        ]);
        exit;
    }
    $batch_id = $db->insert_id;
    echo json_encode([
        'success' => true,
        'batch_id' => (int)$batch_id
    ]);
    exit;
} elseif ($action === "insert_inference") {
    // Expected: batch_id, original_ir_filename, comp_type, model_used, inference_speed_ms, confidence_score, bounding_box, timestamp_ms
    $batch_id = $data['batch_id'] ?? null;
    $originalFilename = $data['original_ir_filename'] ?? '';
    $compType = $data['comp_type'] ?? '';
    $modelUsed = $data['model_used'] ?? '';
    $speedMs = $data['inference_speed_ms'] ?? 0;
    $confidence = $data['confidence_score'] ?? null;
    $bbox = $data['bounding_box'] ?? '';
    $ts = $data['timestamp_ms'] ?? 0;
    if (!$batch_id || !$originalFilename) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required inference parameters.'
        ]);
        exit;
    }
    $db = getDBconnection();
    $stmt = $db->prepare("INSERT INTO inferences (batch_id, original_ir_filename, comp_type, model_used, inference_speed_ms, confidence_score, bounding_box, timestamp_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Prepare failed: ' . $db->error
        ]);
        exit;
    }
    $stmt->bind_param("isssiisi", $batch_id, $originalFilename, $compType, $modelUsed, $speedMs, $confidence, $bbox, $ts);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Execute failed: ' . $stmt->error
        ]);
        exit;
    }
    echo json_encode([
        'success' => true
    ]);
    exit;
} elseif ($action === "get_all_inferences") {
    $db = getDBconnection();
    $result = $db->query("SELECT * FROM inferences ORDER BY timestamp_ms DESC");
    if (!$result) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Query failed: ' . $db->error
        ]);
        exit;
    }
    $inferences = [];
    while ($row = $result->fetch_assoc()) {
        $inferences[] = $row;
    }
    echo json_encode([
        'success' => true,
        'inferences' => $inferences
    ]);
    exit;
} elseif ($action === "get_inference_batches") {
    $db = getDBconnection();
    // This query returns each batch with a count of associated inference records
    $sql = "
        SELECT 
          batch_id AS id,
          excel_filename,
          upload_timestamp AS timestamp,
          (SELECT COUNT(*) FROM inferences i WHERE i.batch_id = b.batch_id) AS numImages
        FROM inference_batches b
        ORDER BY upload_timestamp DESC
    ";
    $result = $db->query($sql);
    if (!$result) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Query failed: ' . $db->error
        ]);
        exit;
    }
    $batches = [];
    while ($row = $result->fetch_assoc()) {
        $batches[] = $row;
    }
    echo json_encode([
        'success' => true,
        'batches' => $batches
    ]);
    exit;
} elseif ($action === "get_inferences_by_batch") {
    $db = getDBconnection();
    $batch_id = $data['batch_id'] ?? null;
    if (!$batch_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing batch_id'
        ]);
        exit;
    }
    $stmt = $db->prepare("SELECT * FROM inferences WHERE batch_id = ? ORDER BY timestamp_ms DESC");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Prepare failed: ' . $db->error
        ]);
        exit;
    }
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $inferences = [];
    while ($row = $result->fetch_assoc()) {
        $inferences[] = $row;
    }
    echo json_encode([
        'success' => true,
        'inferences' => $inferences
    ]);
    exit;
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
    exit;
}
