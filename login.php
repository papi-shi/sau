<?php
// login.php - Enhanced Login Form with Session Isolation
require_once 'config.php';

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for existing valid session and redirect appropriately
if (isset($_SESSION['user_id']) && isLoggedIn()) {
    // Verify session integrity and type
    if (validateSession()) {
        if (isAdmin()) {
            // Check if admin session is still valid in database
            if (validateAdminSession($_SESSION['user_id'], $_SESSION['session_token'])) {
                redirect('admin_dashboard.php');
            } else {
                // Admin session invalidated elsewhere (possibly by another login)
                destroySession();
                $error = 'Your session has been terminated by another login.';
            }
        } else {
            // Regular user session
            redirect('user_dashboard.php');
        }
    } else {
        // Invalid session, destroy it
        destroySession();
    }
}

$error = '';
$success = '';
$loginAttempts = 0;
$maxAttempts = 5;
$lockoutTime = 900; // 15 minutes

// Check for login attempts tracking
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

// Check if user is locked out
if ($_SESSION['login_attempts'] >= $maxAttempts) {
    $timeSinceLastAttempt = time() - $_SESSION['last_attempt'];
    if ($timeSinceLastAttempt < $lockoutTime) {
        $remainingTime = $lockoutTime - $timeSinceLastAttempt;
        $error = "Too many failed login attempts. Please try again in " . ceil($remainingTime / 60) . " minutes.";
    } else {
        // Reset attempts after lockout period
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['login_attempts'] < $maxAttempts) {
    // CSRF Protection - Check if token exists in both session and POST
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
        // Regenerate CSRF token after failed validation
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $rememberMe = isset($_POST['remember_me']);
        
        // Basic validation
        if (empty($username) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else if (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters long.';
        } else if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {
            try {
                // Check user credentials with additional security checks
                $stmt = $pdo->prepare("SELECT id, username, password, role, status, last_login, failed_attempts, locked_until FROM users WHERE username = ? AND status != 'deleted'");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Check if account is temporarily locked
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $error = 'Account is temporarily locked due to multiple failed login attempts.';
                    } else if (password_verify($password, $user['password'])) {
                        // Check if user is approved (except admin)
                        if ($user['role'] === 'admin' || $user['status'] === 'approved') {
                            // For admin users: Terminate all other sessions
                            if ($user['role'] === 'admin') {
                                terminateOtherAdminSessions($user['id']);
                            }
                            
                            // Reset failed attempts on successful login
                            $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
                            $stmt->execute([$user['id']]);
                            
                            // Regenerate session ID for security
                            session_regenerate_id(true);
                            
                            // Generate unique session token
                            $sessionToken = bin2hex(random_bytes(32));
                            
                            // Set session variables with additional security data
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                            $_SESSION['session_token'] = $sessionToken;
                            $_SESSION['is_admin'] = ($user['role'] === 'admin');
                            
                            // Store session in database for admin users
                            if ($user['role'] === 'admin') {
                                $stmt = $pdo->prepare("INSERT INTO admin_sessions (user_id, session_token, ip_address, user_agent, created_at, expires_at) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY))");
                                $stmt->execute([$user['id'], $sessionToken, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
                            }
                            
                            // Handle "Remember Me" functionality
                            if ($rememberMe) {
                                $rememberToken = bin2hex(random_bytes(32));
                                $hashedToken = hash('sha256', $rememberToken);
                                $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
                                
                                // Store remember token in database
                                $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token_hash = ?, expires_at = ?");
                                $stmt->execute([$user['id'], $hashedToken, $expires, $hashedToken, $expires]);
                                
                                // Set remember me cookie
                                setcookie('remember_token', $rememberToken, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                            }
                            
                            // Reset login attempts
                            $_SESSION['login_attempts'] = 0;
                            
                            // Log successful login
                            logActivity($user['id'], 'login_success', 'User logged in successfully');
                            
                            // Redirect based on role
                            if ($user['role'] === 'admin') {
                                redirect('admin_dashboard.php');
                            } else {
                                redirect('user_dashboard.php');
                            }
                        } else if ($user['status'] === 'pending') {
                            $error = 'Your account is pending approval. Please wait for admin approval.';
                        } else if ($user['status'] === 'rejected') {
                            $error = 'Your account has been rejected. Please contact the administrator.';
                        } else {
                            $error = 'Account access denied.';
                        }
                    } else {
                        // Increment failed attempts for the user
                        $failedAttempts = $user['failed_attempts'] + 1;
                        $lockedUntil = null;
                        
                        if ($failedAttempts >= 5) {
                            $lockedUntil = date('Y-m-d H:i:s', time() + 900); // Lock for 15 minutes
                        }
                        
                        $stmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                        $stmt->execute([$failedAttempts, $lockedUntil, $user['id']]);
                        
                        $error = 'Invalid username or password.';
                        
                        // Log failed login attempt
                        logActivity($user['id'], 'login_failed', 'Failed login attempt for username: ' . $username);
                    }
                } else {
                    $error = 'Invalid username or password.';
                }
                
                // Increment session-based login attempts
                if ($error && $error !== 'Account is temporarily locked due to multiple failed login attempts.') {
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt'] = time();
                }
                
            } catch (PDOException $e) {
                $error = 'Database error occurred. Please try again later.';
                error_log('Login database error: ' . $e->getMessage());
            }
        }
    }
}

// Helper function to validate session integrity
function validateSession() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time']) || !isset($_SESSION['session_token'])) {
        return false;
    }
    
    // Check session timeout (24 hours for regular users, 1 hour for admins)
    $sessionTimeout = (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) ? 3600 : 86400;
    if (time() - $_SESSION['login_time'] > $sessionTimeout) {
        return false;
    }
    
    // For admin users, validate against database session
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        return validateAdminSession($_SESSION['user_id'], $_SESSION['session_token']);
    }
    
    return true;
}

// Validate admin session against database
function validateAdminSession($userId, $sessionToken) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM admin_sessions WHERE user_id = ? AND session_token = ? AND expires_at > NOW()");
        $stmt->execute([$userId, $sessionToken]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log('Admin session validation error: ' . $e->getMessage());
        return false;
    }
}

// Terminate all other admin sessions for this user
function terminateOtherAdminSessions($userId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM admin_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log('Error terminating admin sessions: ' . $e->getMessage());
    }
}

// Helper function to destroy session completely
function destroySession() {
    global $pdo;
    
    // If this was an admin session, remove it from database
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] && isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM admin_sessions WHERE user_id = ? AND session_token = ?");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['session_token']]);
        } catch (PDOException $e) {
            error_log('Error removing admin session: ' . $e->getMessage());
        }
    }
    
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// Helper function to log activities
function logActivity($userId, $action, $description) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $description, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
    } catch (PDOException $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login - User Management System</title>
    <meta name="robots" content="noindex, nofollow">
     <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 420px;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
            font-weight: 600;
            font-size: 1.8rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fafafa;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 0.5rem;
            transform: scale(1.2);
        }

        .checkbox-group label {
            margin-bottom: 0;
            font-size: 0.9rem;
            cursor: pointer;
        }

        button {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        button:active {
            transform: translateY(0);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error {
            color: #dc3545;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            padding: 0.75rem;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }

        .success {
            color: #155724;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            padding: 0.75rem;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
        }

        .links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .security-info {
            font-size: 0.8rem;
            color: #666;
            text-align: center;
            margin-top: 1rem;
            padding: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .loading {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .strength-meter {
            height: 3px;
            background-color: #e0e0e0;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-meter-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 2rem;
                margin: 10px;
            }
            
            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2> Philippine Coast Guard</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required autocomplete="username"
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       <?php echo ($_SESSION['login_attempts'] >= $maxAttempts) ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       <?php echo ($_SESSION['login_attempts'] >= $maxAttempts) ? 'disabled' : ''; ?>>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="remember_me" name="remember_me" 
                       <?php echo ($_SESSION['login_attempts'] >= $maxAttempts) ? 'disabled' : ''; ?>>
                <label for="remember_me">Remember me for 30 days</label>
            </div>
            
            <button type="submit" id="loginBtn" 
                    <?php echo ($_SESSION['login_attempts'] >= $maxAttempts) ? 'disabled' : ''; ?>>
                <span id="loginText">Login</span>
                <div class="loading" id="loading"></div>
            </button>
        </form>
        
        <div class="links">
            <a href="register.php">Don't have an account? Register</a><br>
            <a href="forgot_password.php" style="margin-top: 0.5rem; display: inline-block;">Forgot Password?</a>
        </div>
        
        <div class="security-info">
            🛡️ Your connection is secured with SSL encryption<br>
            Login attempts: <?php echo $_SESSION['login_attempts']; ?>/<?php echo $maxAttempts; ?>
        </div>
    </div>

     <script>
        // Enhanced client-side validation and security
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const loading = document.getElementById('loading');
            const loginText = document.getElementById('loginText');

            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value;
                
                // Client-side validation
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

                // Show loading state
                loginBtn.disabled = true;
                loginText.style.opacity = '0';
                loading.style.display = 'block';
            });

            // Auto-clear error messages after 10 seconds
            const errorDiv = document.querySelector('.error');
            if (errorDiv) {
                setTimeout(() => {
                    errorDiv.style.opacity = '0';
                    setTimeout(() => {
                        errorDiv.style.display = 'none';
                    }, 300);
                }, 10000);
            }

            // Prevent multiple rapid submissions
            let isSubmitting = false;
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return;
                }
                isSubmitting = true;
                setTimeout(() => {
                    isSubmitting = false;
                }, 2000);
            });

            // Security: Clear password field on page visibility change
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    // Optional: Clear sensitive fields when tab is hidden
                    // document.getElementById('password').value = '';
                }
            });

            // Prevent form caching
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        });

        // Security: Disable right-click context menu on form inputs
        document.querySelectorAll('input[type="password"]').forEach(function(input) {
            input.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
        });

        // Auto-logout warning (optional)
        let inactivityTimer;
        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                if (confirm('You have been inactive for a while. Do you want to stay logged in?')) {
                    resetInactivityTimer();
                } else {
                    window.location.href = 'logout.php';
                }
            }, 1800000); // 30 minutes
        }

        // Track user activity
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(function(event) {
            document.addEventListener(event, resetInactivityTimer, true);
        });

        resetInactivityTimer();
    </script>
</body>
</html>