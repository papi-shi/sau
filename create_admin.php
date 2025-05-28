<?php
require_once 'db_config.php';

$full_name = 'Admin User';
$rank = 'commodore';
$contact_number = '09653845774';
$username = 'admin';
$password = 'admin123'; // Change this to a strong password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO users (full_name, rank, contact_number, username, password, user_type, status) VALUES (?, ?, ?, ?, ?, 'admin', 'approved')");
    $stmt->execute([$full_name, $rank, $contact_number, $username, $hashed_password]);
    
    echo "Admin account created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "<strong>Important:</strong> Delete this file after creating the admin account!";
} catch (PDOException $e) {
    echo "Error creating admin account: " . $e->getMessage();
}
?>