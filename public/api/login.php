<?php
// /api/login.php - API endpoint for login
require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

header('Content-Type: application/json');  // Return a JSON response

try {
    $db = getDBconnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Database connection failed']);
    exit();
}

// Check if username and password are passed via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $username = $input['username'] ?? null;
    $password = $input['password'] ?? null;

    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode(['message' => 'Username and password are required']);
        exit();
    }

    error_log("Login attempt for user: $username");

    // Query the database for the user
    $stmt = $db->prepare("SELECT * FROM login_users WHERE User_Name = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify the password
        if (password_verify($password, $user['Password'])) {
            // Define token payload
            $payload = [
                'iss' => 'localhost',
                'aud' => 'localhost',
                'iat' => time(),
                'nbf' => time(),
                'exp' => time() + (60 * 60),
                'data' => [
                    'userId' => $user['ID'],
                    'username' => $user['User_Name'],
                    'email' => $user['email'],
                    'permissions' => $user['Permissions']
                ]
            ];

            $_SESSION['email'] = $user['email'];

            $jwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256');

            error_log("User $username logged in successfully.");
            // Log User-Agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Not set';
            error_log("User-Agent: $userAgent");

            // Determine if request is from Flutter app
            $isFlutterApp = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Dart') !== false;
            error_log("isFlutterApp: " . ($isFlutterApp ? 'true' : 'false'));

            if ($isFlutterApp) {
                // Return the token in the response
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'token' => $jwt,
                    'user' => [
                        'userId' => $user['ID'],
                        'username' => $user['User_Name'],
                        'permissions' => $user['Permissions']
                    ]
                ]);
            } else { // Set HTTP-only cookie for web clients
                setcookie('token', $jwt, [
                    'expires' => time() + 3600,
                    'path' => '/',
                    // 'domain' => 'localhost', // Set to your domain if needed
                    'secure' => false, // Set to true if using HTTPS
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);

                // Return success without token
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => [
                        'userId' => $user['ID'],
                        'username' => $user['User_Name'],
                        'permissions' => $user['Permissions']
                    ]
                ]);
            }
        } else {
            // Invalid credentials
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } else {
        // Username not found
        http_response_code(404);
        echo json_encode(['message' => 'User not found']);
    }

    $stmt->close();
    $db->close();
} else {
    http_response_code(405);  // Method Not Allowed
    echo json_encode(['message' => 'Only POST method is allowed']);
}
