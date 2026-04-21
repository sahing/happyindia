<?php
include 'includes/db_config.php';

$conn = getDBConnection();

// Create password_reset_tokens table
$sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table password_reset_tokens created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

closeDBConnection($conn);
echo "Database setup complete!";
?>