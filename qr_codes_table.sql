-- Create qr_codes table for storing uploaded QR code images
CREATE TABLE IF NOT EXISTS qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add some sample data (optional)
-- INSERT INTO qr_codes (filename, original_name) VALUES ('sample_qr.png', 'Sample QR Code.png');