<?php
// config.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production to hide errors

require_once __DIR__ . '/config_secret.php';

$servername = getenv('DB_HOST');
$dbusername = getenv('DB_USER');
$password = getenv('DB_PASS');
$dbname = getenv('DB_NAME');
$dbport = getenv('DB_PORT') ?: 3306;

function getDBconnection()
{

    global $servername, $dbusername, $password, $dbname;

    // Create connection (object-oriented approach)
    $mysqli = new mysqli($servername, $dbusername, $password, $dbname);

    // Check connection
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }

    return $mysqli;
}

define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY'));