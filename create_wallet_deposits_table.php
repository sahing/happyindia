<?php
// Create wallet_deposits table for deposit requests
// Force local database connection for CLI
$_SERVER['HTTP_HOST'] = 'localhost';
include 'includes/db_config.php';

$conn = getDBConnection();

$sql = "CREATE TABLE IF NOT EXISTS wallet_deposits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    screenshot_path VARCHAR(255),
    qr_code_id INT,
    utr_number VARCHAR(100),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_notes TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id),
    FOREIGN KEY (processed_by) REFERENCES admins(id)
)";

if ($conn->query($sql) === TRUE) {
    echo "✅ wallet_deposits table created successfully!";
} else {
    echo "❌ Error creating table: " . $conn->error;
}

closeDBConnection($conn);
?>