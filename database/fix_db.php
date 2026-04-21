<?php
// Database Fix Script
// Run this to fix missing tables and columns

echo "<h1>Database Fix Script</h1>";
echo "<pre>";

// Local database credentials
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'happyindia_db';
$port = 3311;

echo "Connecting to database...\n";

// Connect to database
$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "✅ Connected successfully!\n\n";

// SQL commands to fix the database
$sql_commands = [
    "CREATE TABLE IF NOT EXISTS upi_ids (
        id INT PRIMARY KEY AUTO_INCREMENT,
        upi_id VARCHAR(100) NOT NULL UNIQUE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "INSERT IGNORE INTO upi_ids (upi_id) VALUES ('happyindia@upi')",

    "ALTER TABLE admins ADD COLUMN IF NOT EXISTS permission_level ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin'",
    "ALTER TABLE admins ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",

    "INSERT IGNORE INTO admins (username, password_hash, permission_level)
     VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin')"
];

$success_count = 0;
$error_count = 0;

foreach ($sql_commands as $sql) {
    echo "Executing: " . substr($sql, 0, 50) . "...\n";

    if ($conn->query($sql) === TRUE) {
        echo "✓ Success\n";
        $success_count++;
    } else {
        echo "✗ Error: " . $conn->error . "\n";
        $error_count++;
    }
}

$conn->close();

echo "\n\nSummary:\n";
echo "Successful: $success_count\n";
echo "Failed: $error_count\n";

if ($error_count == 0) {
    echo "\n🎉 Database fix completed! You can now access the admin settings.\n";
} else {
    echo "\n⚠️  Some fixes failed. Check the errors above.\n";
}

echo "</pre>";
?>