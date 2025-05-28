<?php
require_once 'functions.php';

if (!isAdmin()) {
    redirect('login.php');
}

// Get pending users
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM users WHERE is_approved = FALSE");
$stmt->execute();
$pending_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Power Management System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .header { background: #333; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .sidebar { width: 250px; background: #444; color: white; position: fixed; height: 100%; padding-top: 20px; }
        .sidebar a { color: white; padding: 10px 15px; text-decoration: none; display: block; }
        .sidebar a:hover { background: #555; }
        .main-content { margin-left: 250px; padding: 20px; }
        .card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
        .btn { padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-approve { background: #5cb85c; color: white; }
        .btn-reject { background: #d9534f; color: white; }
        .logout { background: #d9534f; color: white; padding: 8px 12px; border-radius: 4px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Power Management System - Admin Dashboard</h2>
        <a href="login.php" class="logout">Logout</a>
    </div>
    
    <div class="sidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="approve_users.php">Approve Users</a>
        <a href="#">Substation Management</a>
        <a href="#">Station Management</a>
        <a href="#">Reports</a>
        <a href="#">Settings</a>
    </div>
    
    <div class="main-content">
        <div class="card">
            <h3>Pending Approvals</h3>
            <?php if (count($pending_users) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>User Type</th>
                            <th>Registered On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo ucfirst($user['user_type']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <form action="approve_users.php" method="post" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="approve" class="btn btn-approve">Approve</button>
                                    </form>
                                    <form action="approve_users.php" method="post" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="reject" class="btn btn-reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No pending approvals.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>