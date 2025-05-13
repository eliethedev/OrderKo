-- Add is_deleted column to messages table
ALTER TABLE messages ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0;

-- Add business_context column to messages table
ALTER TABLE messages ADD COLUMN business_context INT NULL;
ALTER TABLE messages ADD INDEX (business_context);

-- Create archived_conversations table
CREATE TABLE IF NOT EXISTS archived_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('user', 'business') NOT NULL,
    partner_id INT NOT NULL,
    partner_type ENUM('user', 'business') NOT NULL,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, user_type),
    INDEX (partner_id, partner_type)
);

-- Create blocked_users table
CREATE TABLE IF NOT EXISTS blocked_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('user', 'business') NOT NULL,
    blocked_id INT NOT NULL,
    blocked_type ENUM('user', 'business') NOT NULL,
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, user_type),
    INDEX (blocked_id, blocked_type),
    UNIQUE KEY unique_block (user_id, user_type, blocked_id, blocked_type)
);
