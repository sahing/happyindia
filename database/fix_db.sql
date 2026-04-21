-- Fix for missing upi_ids table
-- Run this in phpMyAdmin or MySQL command line

USE happyindia_db;

-- Create upi_ids table if it doesn't exist
CREATE TABLE IF NOT EXISTS upi_ids (
    id INT PRIMARY KEY AUTO_INCREMENT,
    upi_id VARCHAR(100) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default UPI ID if not exists
INSERT IGNORE INTO upi_ids (upi_id) VALUES ('happyindia@upi');

-- Update admins table to add permission_level column if missing
ALTER TABLE admins ADD COLUMN IF NOT EXISTS permission_level ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin';
ALTER TABLE admins ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Insert default admin if not exists
INSERT IGNORE INTO admins (username, password_hash, permission_level)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

SELECT 'Database fix applied successfully!' as status;