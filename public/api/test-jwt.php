<?php
// filepath: c:\xampp\htdocs\rmap-middleware\public\api\test-jwt.php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');

try {
    // Step 1: Create a test token
    $payload = [
        'iss' => 'test-issuer',
        'aud' => 'test-audience',
        'iat' => time(),
        'nbf' => time(),
        'exp' => time() + 60,  // Token expires in 60 seconds
        'data' => [
            'userId' => 'test-user',
            'username' => 'testuser',
            'email' => 'test@example.com'
        ]
    ];

    // Create token
    $jwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');
    
    // Step 2: Verify the token
    $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
    
    // Step 3: Return results
    echo json_encode([
        'success' => true,
        'message' => 'JWT library is working correctly',
        'token' => $jwt,
        'decoded' => [
            'userId' => $decoded->data->userId,
            'username' => $decoded->data->username,
            'email' => $decoded->data->email,
            'expires' => date('Y-m-d H:i:s', $decoded->exp)
        ],
        'library_version' => Firebase\JWT\JWT::$timestamp
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'JWT test failed: ' . $e->getMessage()
    ]);
}