<?php
// config.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production to hide errors

require_once __DIR__ . '/config_secret.php';

$servername = "localhost";
$dbusername = "root";
$password = "";
$dbname = "project_prototype_1";

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