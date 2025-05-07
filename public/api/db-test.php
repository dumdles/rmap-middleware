<?php
// File: public/db-test.php
require_once __DIR__ . '/../../src/config.php';
header('Content-Type: application/json');

try {
    // Test connection
    $db = getDBconnection();
    
    // Simple query to verify connection and permissions
    $result = $db->query("SHOW TABLES");
    
    if (!$result) {
        throw new Exception("Failed to execute query: " . $db->error);
    }
    
    $tables = [];
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'database' => [
            'host' => $db->host_info,
            'server_version' => $db->server_info,
            'tables_count' => count($tables),
            'tables' => $tables
        ]
    ]);
    
    $db->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'env_vars' => [
            'DB_HOST' => !empty(getenv('DB_HOST')) ? 'Set' : 'Not set',
            'DB_USER' => !empty(getenv('DB_USER')) ? 'Set' : 'Not set',
            'DB_PASS' => !empty(getenv('DB_PASS')) ? 'Set (length: ' . strlen(getenv('DB_PASS')) . ')' : 'Not set',
            'DB_NAME' => !empty(getenv('DB_NAME')) ? 'Set' : 'Not set'
        ]
    ]);
}