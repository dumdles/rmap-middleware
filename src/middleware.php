<?php
// middleware.php - Unified middleware for all API endpoints

// Start the session if not already started 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the logger helper
require_once __DIR__ . '/helpers/logger.php';

// Include Composer autoload
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Handle CORS
$allowed_origins = [
    // Local development variations
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'https://localhost:3000',
    'https://127.0.0.1:3000',
    'http://172.23.58.17:3000',
    'http://172.23.58.16:3000',
    'http://172.23.58.12:3000',
    // Your existing origins
    'http://192.168.50.169:3000',
    'http://172.22.47.163',
    'http://172.22.46.88',
    'http://172.23.58.17',
    // Development environments
    'http://' . $_SERVER['HTTP_HOST'],
    'https://' . $_SERVER['HTTP_HOST'],
    // Allow any subdomain of your local network
    'http://*.local',
    // Add specific IP addresses from your network
    'http://192.168.*.*',     // Allow all IPs in 192.168.x.x range
    'http://172.16.*.*',      // Allow all IPs in 172.16.x.x range
    'http://10.*.*.*',        // Allow all IPs in 10.x.x.x range
    'http://172.23.*.*',
    'https://deployment.d1zcgaudis8kbz.amplifyapp.com',

];

if (empty($_SERVER['HTTP_ORIGIN'])) {
    $_SERVER['HTTP_ORIGIN'] = 'http://localhost:3000'; // Default fallback
}

error_log("Incoming Origin: " . $origin);

// Function to check if origin matches pattern (supports wildcards)
function matchesPattern($origin, $pattern)
{
    if (empty($origin)) {
        return false;
    }
    $pattern = preg_quote($pattern, '/');
    $pattern = str_replace('\*', '.*', $pattern);
    return preg_match('/^' . $pattern . '$/', $origin);
}

// Check if origin is allowed
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$origin_allowed = false;

foreach ($allowed_origins as $allowed_origin) {
    if (strpos($allowed_origin, '*') !== false) {
        if (matchesPattern($origin, $allowed_origin)) {
            $origin_allowed = true;
            break;
        }
    } else if ($origin === $allowed_origin) {
        $origin_allowed = true;
        break;
    }
}
if ($origin_allowed) {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    // You might want to log unauthorized attempts
    error_log("Blocked CORS request from: " . $origin);
    header("Access-Control-Allow-Origin: http://localhost:3000"); // Fallback
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 hours cache for preflight requests

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$public_endpoints = [
    '/api/login.php',
    '/api/signup.php',
    '/RMAP/api/login.php',
    '/RMAP/api/signup.php'
];

// Function to check if current endpoint is public
function is_public_endpoint()
{
    global $public_endpoints;
    $current_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return in_array($current_uri, $public_endpoints);
}

$admin_endpoints = [
    '/api/get-users.php',
];

// Function to check if the current endpoint requires admin access
function is_admin_endpoint()
{
    global $admin_endpoints;
    $current_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return in_array($current_uri, $admin_endpoints);
}

function getAuthorizationHeader()
{
    $headers = null;

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(
            array_map('ucwords', array_keys($requestHeaders)),
            array_values($requestHeaders)
        );
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }

    error_log("Found Authorization header: " . ($headers ? $headers : 'not found'));
    return $headers;
}

// Function to authenticate user, using JWT
function authenticate_user()
{
    error_log("Starting authentication process");

    if (is_public_endpoint()) {
        return;
    }

    $jwt = null;

    // Try to get the token from the Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader) {
        list($jwt) = sscanf($authHeader, 'Bearer %s');
    }

    // If no token in header, try to get it from the cookie
    if (!$jwt && isset($_COOKIE['token'])) {
        $jwt = $_COOKIE['token'];
    }

    if (!$jwt) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No token provided']);
        exit();
    }

    try {
        // Debug - Check secret key
        $secret_key = JWT_SECRET_KEY;

        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));

        // Debug - Log successful decode
        error_log("JWT successfully decoded. User ID: " . $decoded->data->userId);

        $_SESSION['user_id'] = $decoded->data->userId;
        $_SESSION['username'] = $decoded->data->username;
        $_SESSION['email'] = $decoded->data->email;
        $_SESSION['permissions'] = $decoded->data->permissions;

        // After setting permissions, check if endpoint requires admin access
        if (is_admin_endpoint()) {
            if (!isset($_SESSION['permissions']) || $_SESSION['permissions'] != 1) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden: Admin access required']);
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("JWT decode error: " . $e->getMessage());
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid token: ' . $e->getMessage()]);
        exit();
    }
}

// Call the authentication function
authenticate_user();

// Function to log actions based on the request
function log_request()
{
    $user_id = $_SESSION['user_id'] ?? null;
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    $input = file_get_contents('php://input'); // Raw input data

    // Parse input data if JSON
    $details = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $details = $input; // Fallback to raw input
    }

    // log_action("API Request", $user_id, json_encode([
    //     'method' => $method,
    //     'uri' => $uri,
    //     'details' => $details
    // ]));
}

// In middleware.php or a global helpers file
function isAdmin()
{
    if (!isset($_SESSION['permissions'])) {
        return false; // No permissions set, definitely not admin
    }

    // Typically, 1 or '1' indicates admin
    return $_SESSION['permissions'] == 1;
}


// Log the incoming request
log_request();
