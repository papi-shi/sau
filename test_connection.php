<?php
require_once 'db_config.php';

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected successfully!";
} catch(PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}
?>