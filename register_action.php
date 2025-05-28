<?php
require 'db.php';

$username = $_POST['username'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$role = $_POST['role'];

$stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $password, $role);

if ($stmt->execute()) {
    echo "Registration successful. Awaiting admin approval.";
} else {
    echo "Error: " . $stmt->error;
}
?>
