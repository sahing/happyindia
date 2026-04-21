<?php
// Database Update Script
// This script will update the database with the latest schema
// Run this on your LIVE server, not locally!

echo "<h1>Database Update Script</h1>";
echo "<pre>";

// IMPORTANT: Update these credentials for your live server
$host = 'localhost'; // Your live database host (usually 'localhost' or IP)
$user = 'primexio_happyindia'; // Your live database username
$pass = 'Picf=1NVK7;!8bOx'; // Your live database password
$db = 'primexio_happyindia'; // Your live database name
$port = 3306; // Usually 3306 for MySQL

echo "Attempting to connect to database...\n";
echo "Host: $host\n";
echo "Database: $db\n";
echo "User: $user\n\n";

// Connect to database
$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    echo "❌ CONNECTION FAILED!\n";
    echo "Error: " . $conn->connect_error . "\n\n";
    echo "🔧 TROUBLESHOOTING:\n";
    echo "1. Make sure you're running this on your LIVE server\n";
    echo "2. Check your database credentials in this file\n";
    echo "3. Contact your hosting provider for correct credentials\n";
    echo "4. Make sure the database exists\n";
    die();
}

echo "✅ Connected to database successfully!\n\n";

// Read the schema file
$schema_file = '../database/schema.sql';
if (!file_exists($schema_file)) {
    die("Schema file not found: $schema_file");
}

$schema_sql = file_get_contents($schema_file);

// Split the SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $schema_sql)));

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue; // Skip empty lines and comments
    }

    echo "Executing: " . substr($statement, 0, 50) . "...\n";

    if ($conn->query($statement) === TRUE) {
        echo "✓ Success\n";
        $success_count++;
    } else {
        echo "✗ Error: " . $conn->error . "\n";
        $error_count++;
    }
}

$conn->close();

echo "\n\nSummary:\n";
echo "Successful statements: $success_count\n";
echo "Failed statements: $error_count\n";

if ($error_count == 0) {
    echo "\n🎉 Database update completed successfully!\n";
} else {
    echo "\n⚠️  Some statements failed. Please check the errors above.\n";
}

echo "</pre>";
?>