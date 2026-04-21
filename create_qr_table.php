<?php
// Force local environment for command line execution
$_SERVER['HTTP_HOST'] = 'localhost';

require_once 'includes/db_config.php';

$conn = getDBConnection();

// Create qr_codes table
$sql = "CREATE TABLE IF NOT EXISTS qr_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "qr_codes table created successfully\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

closeDBConnection($conn);
?>