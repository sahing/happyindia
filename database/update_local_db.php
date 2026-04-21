<?php
// Database Update Script for LOCAL Testing
// This script will update your LOCAL database for testing

echo "<h1>Database Update Script - Local Test</h1>";
echo "<pre>";

// Local database credentials
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'happyindia_db';
$port = 3311; // Your local MySQL port

echo "Attempting to connect to LOCAL database...\n";
echo "Host: $host\n";
echo "Database: $db\n";
echo "User: $user\n\n";

// Connect to database
$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    echo "❌ CONNECTION FAILED!\n";
    echo "Error: " . $conn->connect_error . "\n\n";
    echo "🔧 TROUBLESHOOTING:\n";
    echo "1. Make sure XAMPP/MySQL is running\n";
    echo "2. Check if port 3311 is correct (check your XAMPP config)\n";
    echo "3. Try port 3306 if 3311 doesn't work\n";
    die();
}

echo "✅ Connected to local database successfully!\n\n";

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
    echo "\n🎉 Local database update completed successfully!\n";
    echo "You can now test the website locally.\n";
} else {
    echo "\n⚠️  Some statements failed. Please check the errors above.\n";
}

echo "</pre>";
?>