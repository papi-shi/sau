-- Create database
CREATE DATABASE IF NOT EXISTS login_system;
USE login_system;

-- Table for role permissions
CREATE TABLE IF NOT EXISTS role_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL UNIQUE,
    is_allowed TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role) REFERENCES role_access(role)
);

-- Table for login history
CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    ip_address VARCHAR(50),
    status VARCHAR(20) DEFAULT 'success',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default roles
INSERT INTO role_access (role, is_allowed) VALUES 
('admin', 1),
('station', 1),
('substation', 1)
ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);

-- Insert default users with hashed passwords (use password_hash() in PHP)
INSERT INTO users (username, password, role, full_name) VALUES 
('admin', '$2y$10$abcdefghijklmnopqrstuuqZvCNTpFiDT6L.R9wEh9Aeq9q2C5ie', 'admin', 'System Administrator'),
('zamboanga', '$2y$10$abcdefghijklmnopqrstuuXlkTfvWo9.DYfBgI0orDKnWnNmTTm', 'station', 'Zamboanga Station'),
('subs', '$2y$10$abcdefghijklmnopqrstuuwOJsD6HdANa1HXOqNYlgR6cE2LGtO', 'substation', 'Cawit Substation')
ON DUPLICATE KEY UPDATE role = VALUES(role);
