<?php
// Database configuration
// Local development
define('DB_HOST_LOCAL', 'localhost');
define('DB_USER_LOCAL', 'root');
define('DB_PASS_LOCAL', '');
define('DB_NAME_LOCAL', 'happyindia_db');
define('DB_PORT_LOCAL', 3311);

// Live production
define('DB_HOST_LIVE', 'localhost'); // Replace with actual live host
define('DB_USER_LIVE', 'primexio_happyindia'); // Replace with actual live username
define('DB_PASS_LIVE', 'Picf=1NVK7;!8bOx'); // Replace with actual live password
define('DB_NAME_LIVE', 'primexio_happyindia'); // Replace with actual live database name
define('DB_PORT_LIVE', 3306);

// Determine environment
$is_local = ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1' || strpos($_SERVER['HTTP_HOST'], '.local') !== false);

define('DB_HOST', $is_local ? DB_HOST_LOCAL : DB_HOST_LIVE);
define('DB_USER', $is_local ? DB_USER_LOCAL : DB_USER_LIVE);
define('DB_PASS', $is_local ? DB_PASS_LOCAL : DB_PASS_LIVE);
define('DB_NAME', $is_local ? DB_NAME_LOCAL : DB_NAME_LIVE);
define('DB_PORT', $is_local ? DB_PORT_LOCAL : DB_PORT_LIVE);

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}

// Close connection
function closeDBConnection($conn) {
    $conn->close();
}
?>