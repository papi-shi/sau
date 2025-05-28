<?php
// admin_dashboard.php - Admin Dashboard
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$message = '';
$messageType = '';

// Handle user approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];
        
        if ($action === 'approve') {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND role = 'user'");
                if ($stmt->execute([$user_id])) {
                    $message = 'User approved successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to approve user.';
                    $messageType = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error occurred.';
                $messageType = 'error';
            }
        } elseif ($action === 'reject') {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'user'");
                if ($stmt->execute([$user_id])) {
                    $message = 'User rejected successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to reject user.';
                    $messageType = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error occurred.';
                $messageType = 'error';
            }
        }
    }
}

// Get all users
try {
    $stmt = $pdo->prepare("SELECT id, username, role, status, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $message = 'Failed to load users.';
    $messageType = 'error';
}

// Get statistics
try {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'pending'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'approved'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'rejected'")->fetchColumn()
    ];
} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - User Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .user-info h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .user-info p {
            color: #666;
        }

        .logout-btn {
            padding: 0.5rem 1rem;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #5a6268;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }

        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }

        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .users-section h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .user-table th,
        .user-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .user-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .user-table tr:hover {
            background: #f8f9fa;
        }

        .status {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .action-btn {
            padding: 0.25rem 0.5rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
            margin: 0 0.25rem;
            transition: transform 0.2s;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .no-users {
            text-align: center;
            color: #6c757d;
            padding: 2rem;
            font-style: italic;
        }

        .filters {
            margin-bottom: 1rem;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 3px;
            transition: all 0.3s;
        }

        .filter-btn.active,
        .filter-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                <p><strong>Role:</strong> Administrator</p>
            </div>
            <a href="index.php" class="logout-btn">go to dashboard</a>
            <a href="logout.php" class="logout-btn">Logout</a>
            
        </div>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['approved']; ?></div>
                <div class="stat-label">Approved Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Rejected Users</div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="users-section">
            <h3>User Management</h3>
            
            <!-- Filters -->
            <div class="filters">
                <button class="filter-btn active" onclick="filterUsers('all')">All Users</button>
                <button class="filter-btn" onclick="filterUsers('pending')">Pending</button>
                <button class="filter-btn" onclick="filterUsers('approved')">Approved</button>
                <button class="filter-btn" onclick="filterUsers('rejected')">Rejected</button>
            </div>

            <?php if (!empty($users)): ?>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Registration Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr class="user-row" data-status="<?php echo $user['status']; ?>">
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <span class="status status-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="action-btn btn-approve"
                                                    onclick="return confirm('Are you sure you want to approve this user?')">
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="action" value="reject" class="action-btn btn-reject"
                                                    onclick="return confirm('Are you sure you want to reject this user?')">
                                                Reject
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">No actions available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-users">
                    No users found in the system.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterUsers(status) {
            const rows = document.querySelectorAll('.user-row');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Filter rows
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Auto-refresh every 30 seconds to check for new registrations
        setInterval(function() {
            // Only refresh if there are pending users to avoid unnecessary requests
            const pendingCount = <?php echo $stats['pending']; ?>;
            if (pendingCount > 0) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>