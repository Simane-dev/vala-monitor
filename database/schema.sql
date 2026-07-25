-- VALA Monitor Pro - Database Schema
-- Version Pro avec toutes les tables nécessaires
DROP DATABASE IF EXISTS vala_monitor;
CREATE DATABASE vala_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vala_monitor;

-- Table principale des diagnostics
CREATE TABLE diagnostics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    http_code INT NOT NULL DEFAULT 0,
    response_time INT NOT NULL DEFAULT 0,
    ssl_days INT NOT NULL DEFAULT 0,
    ssl_expire_date DATE NULL,
    mx_ok TINYINT(1) NOT NULL DEFAULT 0,
    dns_a VARCHAR(100) DEFAULT 'N/A',
    dns_mx VARCHAR(255) DEFAULT 'N/A',
    vala_score INT NOT NULL DEFAULT 0,
    grade CHAR(1) NOT NULL DEFAULT 'F',
    full_report JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_domain (domain),
    INDEX idx_score (vala_score),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;

-- Table des utilisateurs pour le mode pro
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','technicien') DEFAULT 'technicien',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table pour le suivi d'uptime
CREATE TABLE pings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    status TINYINT(1) NOT NULL,
    response_time INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_domain_date (domain, created_at)
);

-- Insertion de l'admin par défaut (admin / admin123)
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@vala.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('technicien', 'tech@vala.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technicien');

-- Données de test
INSERT INTO diagnostics (domain, http_code, response_time, ssl_days, mx_ok, vala_score, grade, full_report) VALUES
('vala.ma', 200, 245, 89, 1, 95, 'A', '{"test":true}'),
('client1.ma', 200, 560, 12, 1, 70, 'C', '{"test":true}'),
('ancien-client.ma', 500, 3000, 0, 0, 20, 'F', '{"test":true}');