<?php
// refresh-token.php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/middleware.php';
require_once __DIR__ . '/../../src/config.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

header('Content-Type: application/json');
// re-run your JWT decode to verify the old token:
$oldJwt = $_COOKIE['token'] ?? ''; // or from Authorization header
try {
    $decoded = JWT::decode($oldJwt, new Key(JWT_SECRET_KEY, 'HS256'));
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    exit;
}

// issue a brand-new token with a fresh exp
$payload = [
    'iss' => 'localhost',
    'aud' => 'localhost',
    'iat' => time(),
    'nbf' => time(),
    'exp' => time() + 3600,
    'data' => $decoded->data
];
$newJwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');

// send it back (or set as HttpOnly cookie)
setcookie('token', $newJwt, [
    'httponly' => true,
    'secure' => true,
    'samesite' => 'strict',
    'expires' => time() + 3600,
    'path' => '/',
]);

echo json_encode(['success' => true, 'token' => $newJwt]);
