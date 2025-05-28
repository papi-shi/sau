<?php
session_start();
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = $_POST['role'];
    $username = trim($_POST['username']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $personnel_name = trim($_POST['personnel_name']);
    $rank = trim($_POST['rank']);
    
    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['register_error'] = "Username already exists";
            header("Location: login.php");
            exit();
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        if ($role === 'station') {
            // Create station and user
            $station_name = trim($_POST['station_name']);
            
            $stmt = $conn->prepare("INSERT INTO stations (name, location) VALUES (:name, '')");
            $stmt->bindParam(':name', $station_name);
            $stmt->execute();
            $station_id = $conn->lastInsertId();
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, station_id, personnel_name, rank, status) 
                                   VALUES (:username, :password, :role, :station_id, :personnel_name, :rank, 'pending')");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':station_id', $station_id);
            $stmt->bindParam(':personnel_name', $personnel_name);
            $stmt->bindParam(':rank', $rank);
            $stmt->execute();
            
        } elseif ($role === 'substation') {
            // Create substation and user
            $substation_name = trim($_POST['substation_name']);
            $parent_station = $_POST['parent_station'];
            
            $stmt = $conn->prepare("INSERT INTO substations (name, station_id, location) 
                                   VALUES (:name, :station_id, '')");
            $stmt->bindParam(':name', $substation_name);
            $stmt->bindParam(':station_id', $parent_station);
            $stmt->execute();
            $substation_id = $conn->lastInsertId();
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, substation_id, personnel_name, rank, status) 
                                   VALUES (:username, :password, :role, :substation_id, :personnel_name, :rank, 'pending')");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':substation_id', $substation_id);
            $stmt->bindParam(':personnel_name', $personnel_name);
            $stmt->bindParam(':rank', $rank);
            $stmt->execute();
        }
        
        // Create notification for admin
        $user_id = $conn->lastInsertId();
        $message = "New $role account request from $personnel_name ($username)";
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':message', $message);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['register_success'] = "Account created successfully! Waiting for admin approval.";
        header("Location: login.php");
        exit();
        
    } catch(PDOException $e) {
        $conn->rollBack();
        error_log("Database error: " . $e->getMessage());
        $_SESSION['register_error'] = "Error creating account. Please try again.";
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>