<?php
// register.php - Registration Form
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin_dashboard.php');
    } else {
        redirect('user_dashboard.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores.';
    } else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = 'Username already exists. Please choose another.';
            } else {
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, 'user', 'pending')");
                
                if ($stmt->execute([$username, $hashed_password])) {
                    $success = 'Registration successful! Your account is pending approval. Please wait for an administrator to approve your account.';
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - User Management System</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: #333;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
        }

        .error {
            color: #dc3545;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .success {
            color: #28a745;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .links {
            text-align: center;
            margin-top: 1rem;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .password-strength {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .weak { color: #dc3545; }
        .medium { color: #ffc107; }
        .strong { color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores allowed">
                <small style="color: #666; font-size: 0.8rem;">3+ characters, letters, numbers, and underscores only</small>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <div id="password-strength" class="password-strength"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <div id="password-match" style="font-size: 0.8rem; margin-top: 0.25rem;"></div>
            </div>
            
            <button type="submit">Register</button>
        </form>
        
        <div class="links">
            <a href="login.php">Already have an account? Login</a>
        </div>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthDiv = document.getElementById('password-strength');
        const confirmInput = document.getElementById('confirm_password');
        const matchDiv = document.getElementById('password-match');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    feedback = '<span class="weak">Weak</span>';
                    break;
                case 2:
                case 3:
                    feedback = '<span class="medium">Medium</span>';
                    break;
                case 4:
                case 5:
                    feedback = '<span class="strong">Strong</span>';
                    break;
            }
            
            strengthDiv.innerHTML = password.length > 0 ? 'Strength: ' + feedback : '';
        });
        
        // Password match indicator
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            
            if (confirm.length > 0) {
                if (password === confirm) {
                    matchDiv.innerHTML = '<span style="color: #28a745;">Passwords match</span>';
                } else {
                    matchDiv.innerHTML = '<span style="color: #dc3545;">Passwords do not match</span>';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        }
        
        confirmInput.addEventListener('input', checkPasswordMatch);
        passwordInput.addEventListener('input', checkPasswordMatch);
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!username) {
                alert('Username is required');
                e.preventDefault();
                return;
            }
            
            if (username.length < 3) {
                alert('Username must be at least 3 characters long');
                e.preventDefault();
                return;
            }
            
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                alert('Username can only contain letters, numbers, and underscores');
                e.preventDefault();
                return;
            }
            
            if (!password) {
                alert('Password is required');
                e.preventDefault();
                return;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long');
                e.preventDefault();
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>