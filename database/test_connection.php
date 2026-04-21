<?php
// Database Connection Test Script
// Test both local and live database connections

echo "<h1>Database Connection Test</h1>";
echo "<pre>";

// Test Local Connection
echo "=== Testing Local Database Connection ===\n";
$local_host = 'localhost';
$local_user = 'root';
$local_pass = '';
$local_db = 'happyindia_db';
$local_port = 3311;

$local_conn = new mysqli($local_host, $local_user, $local_pass, $local_db, $local_port);
if ($local_conn->connect_error) {
    echo "❌ Local Connection Failed: " . $local_conn->connect_error . "\n";
} else {
    echo "✅ Local Connection Successful\n";
    $local_conn->close();
}

// Test Live Connection
echo "\n=== Testing Live Database Connection ===\n";
$live_host = 'localhost';
$live_user = 'primexio_happyindia';
$live_pass = 'Picf=1NVK7;!8bOx';
$live_db = 'primexio_happyindia';
$live_port = 3306;

$live_conn = new mysqli($live_host, $live_user, $live_pass, $live_db, $live_port);
if ($live_conn->connect_error) {
    echo "❌ Live Connection Failed: " . $live_conn->connect_error . "\n";
} else {
    echo "✅ Live Connection Successful\n";
    $live_conn->close();
}

echo "\n=== Test Complete ===\n";
echo "</pre>";
?>