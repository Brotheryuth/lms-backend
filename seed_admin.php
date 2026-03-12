<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->connect();

    // Create users table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'admin',c
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert admin user
    $hash = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (email, password_hash, role) VALUES (:email, :hash, 'admin') ON CONFLICT (email) DO UPDATE SET password_hash = :hash");
    $stmt->execute([':email' => 'admin@lms.com', ':hash' => $hash]);

    echo json_encode(['success' => true, 'message' => 'Admin user created! Email: admin@lms.com | Password: admin123']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}