<?php
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

global $pdo;

// Approve user
if (isset($_POST['approve'])) {
    $user_id = $_POST['user_id'];
    $stmt = $pdo->prepare("UPDATE users SET is_approved = TRUE WHERE id = ?");
    $stmt->execute([$user_id]);
    
    $_SESSION['success'] = "User approved successfully.";
    redirect('dashboard.php');
}

// Reject user (delete)
if (isset($_POST['reject'])) {
    $user_id = $_POST['user_id'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    $_SESSION['success'] = "User rejected and removed.";
    redirect('dashboard.php');
}

redirect('dashboard.php');
?>