<?php
// helpers/logger.php
include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../index.php';

$logDir = __DIR__ . '/../logs';
if (! is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

function log_action($action, $user_id = null, $details = "")
{
    $username = $_SESSION['username'] ?? 'Guest';

    // Now your DB insertion can use $username
    $db = getDBconnection();
    $stmt = $db->prepare("
        INSERT INTO user_actions_log (username, action_type, description, timestamp)
        VALUES (?, ?, ?, NOW())
    ");
    // Now we pass $username, $action, $details
    $stmt->bind_param("sss", $username, $action, $details);

    $stmt->execute();
    $stmt->close();
    $db->close();

    // Then log to file 
    $timestamp = date('Y-m-d H:i:s');
    $user_info = $user_id ? "User ID: $user_id" : "Guest";
    $log_entry = "[$timestamp] Action: $action | $user_info | Details: $details" . PHP_EOL;
    file_put_contents(__DIR__ . '/../logs/debug_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
}
