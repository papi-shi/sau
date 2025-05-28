<?php
// session_manager.php - Advanced Session Management System
// Include this in your config.php file

class SessionManager {
    private $pdo;
    private $maxUserSessions = 1; // Regular users: 1 session
    private $maxAdminSessions = 3; // Admins: 3 concurrent sessions
    private $sessionTimeout = 1800; // 30 minutes
    private $adminTimeout = 7200; // 2 hours for admins
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initializeSessionTable();
    }
    
    // Initialize the active sessions table
    private function initializeSessionTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS active_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                session_id VARCHAR(128) NOT NULL,
                session_token VARCHAR(64) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_admin BOOLEAN DEFAULT FALSE,
                browser_fingerprint VARCHAR(64),
                INDEX idx_user_id (user_id),
                INDEX idx_session_id (session_id),
                INDEX idx_last_activity (last_activity),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            error_log('Session table creation error: ' . $e->getMessage());
        }
    }
    
    // Start a secure session with user isolation
    public function startSecureSession($userType = 'user') {
        // Set different session names for different user types
        $sessionName = ($userType === 'admin') ? 'UMS_ADMIN_SESSION' : 'UMS_USER_SESSION';
        session_name($sessionName);
        
        // Configure session security settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', $userType === 'admin' ? $this->adminTimeout : $this->sessionTimeout);
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically
        $this->regenerateSessionIfNeeded();
        
        return session_id();
    }
    
    // Create a new user session
    public function createUserSession($user, $rememberMe = false) {
        $isAdmin = ($user['role'] === 'admin');
        
        // Start appropriate session type
        $this->startSecureSession($isAdmin ? 'admin' : 'user');
        
        // Check session limits before creating
        if (!$this->canCreateSession($user['id'], $isAdmin)) {
            $this->terminateOldestSession($user['id'], $isAdmin);
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $_SESSION['session_token'] = $this->generateSecureToken();
        $_SESSION['browser_fingerprint'] = $this->generateBrowserFingerprint();
        $_SESSION['is_protected'] = $isAdmin; // Extra protection for admin sessions
        
        // Store session in database
        $this->storeSessionInDatabase($user['id'], $isAdmin);
        
        // Handle remember me functionality
        if ($rememberMe && !$isAdmin) { // Only for regular users
            $this->setRememberMeToken($user['id']);
        }
        
        // Log session creation
        $this->logSessionActivity($user['id'], 'session_created', 'New session created');
        
        return true;
    }
    
    // Validate current session
    public function validateSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Check session timeout
        $timeout = ($_SESSION['role'] === 'admin') ? $this->adminTimeout : $this->sessionTimeout;
        if (time() - $_SESSION['login_time'] > $timeout) {
            $this->destroyCurrentSession();
            return false;
        }
        
        // Validate against database
        if (!$this->validateSessionInDatabase()) {
            return false;
        }
        
        // Update last activity
        $this->updateSessionActivity();
        
        // Additional security checks
        if (!$this->validateSecurityChecks()) {
            $this->destroyCurrentSession();
            return false;
        }
        
        return true;
    }
    
    // Check if user can create a new session
    private function canCreateSession($userId, $isAdmin) {
        $maxSessions = $isAdmin ? $this->maxAdminSessions : $this->maxUserSessions;
        $timeout = $isAdmin ? $this->adminTimeout : $this->sessionTimeout;
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as active_sessions 
                FROM active_sessions 
                WHERE user_id = ? 
                AND last_activity > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$userId, $timeout]);
            $result = $stmt->fetch();
            
            return $result['active_sessions'] < $maxSessions;
            
        } catch (PDOException $e) {
            error_log('Session limit check error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Terminate oldest session for user
    private function terminateOldestSession($userId, $isAdmin) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM active_sessions 
                WHERE user_id = ? 
                ORDER BY last_activity ASC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            
            $this->logSessionActivity($userId, 'session_terminated', 'Oldest session terminated due to limit');
            
        } catch (PDOException $e) {
            error_log('Session termination error: ' . $e->getMessage());
        }
    }
    
    // Store session information in database
    private function storeSessionInDatabase($userId, $isAdmin) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO active_sessions 
                (user_id, session_id, session_token, ip_address, user_agent, is_admin, browser_fingerprint) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                session_token = VALUES(session_token),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                last_activity = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $userId,
                session_id(),
                $_SESSION['session_token'],
                $_SESSION['ip_address'],
                $_SESSION['user_agent'],
                $isAdmin ? 1 : 0,
                $_SESSION['browser_fingerprint']
            ]);
            
        } catch (PDOException $e) {
            error_log('Session storage error: ' . $e->getMessage());
        }
    }
    
    // Validate session against database
    private function validateSessionInDatabase() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, last_activity 
                FROM active_sessions 
                WHERE user_id = ? 
                AND session_id = ? 
                AND session_token = ?
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                session_id(),
                $_SESSION['session_token']
            ]);
            
            return $stmt->fetch() !== false;
            
        } catch (PDOException $e) {
            error_log('Session validation error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Update session activity timestamp
    private function updateSessionActivity() {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE active_sessions 
                SET last_activity = CURRENT_TIMESTAMP 
                WHERE user_id = ? AND session_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], session_id()]);
            
        } catch (PDOException $e) {
            error_log('Session activity update error: ' . $e->getMessage());
        }
    }
    
    // Additional security validation
    private function validateSecurityChecks() {
        // Check IP consistency (optional - may cause issues with dynamic IPs)
        // if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        //     $this->logSessionActivity($_SESSION['user_id'], 'security_violation', 'IP address mismatch');
        //     return false;
        // }
        
        // Check browser fingerprint
        $currentFingerprint = $this->generateBrowserFingerprint();
        if (isset($_SESSION['browser_fingerprint']) && $_SESSION['browser_fingerprint'] !== $currentFingerprint) {
            $this->logSessionActivity($_SESSION['user_id'], 'security_violation', 'Browser fingerprint mismatch');
            return false;
        }
        
        return true;
    }
    
    // Generate browser fingerprint
    private function generateBrowserFingerprint() {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    // Destroy current session completely
    public function destroyCurrentSession() {
        if (isset($_SESSION['user_id'])) {
            try {
                // Remove from database
                $stmt = $this->pdo->prepare("
                    DELETE FROM active_sessions 
                    WHERE user_id = ? AND session_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], session_id()]);
                
                // Log session destruction
                $this->logSessionActivity($_SESSION['user_id'], 'session_destroyed', 'Session destroyed');
                
            } catch (PDOException $e) {
                error_log('Session destruction error: ' . $e->getMessage());
            }
        }
        
        // Clear session data
        $_SESSION = array();
        
        // Remove session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    // Destroy all sessions for a specific user
    public function destroyAllUserSessions($userId, $exceptCurrent = false) {
        try {
            $sql = "DELETE FROM active_sessions WHERE user_id = ?";
            $params = [$userId];
            
            if ($exceptCurrent && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
                $sql .= " AND session_id != ?";
                $params[] = session_id();
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $this->logSessionActivity($userId, 'all_sessions_destroyed', 'All user sessions destroyed');
            
        } catch (PDOException $e) {
            error_log('All sessions destruction error: ' . $e->getMessage());
        }
    }
    
    // Get active sessions for a user
    public function getActiveSessions($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT session_id, ip_address, user_agent, login_time, last_activity, is_admin
                FROM active_sessions 
                WHERE user_id = ? 
                ORDER BY last_activity DESC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log('Get active sessions error: ' . $e->getMessage());
            return [];
        }
    }
    
    // Clean up expired sessions
    public function cleanupExpiredSessions() {
        try {
            // Clean regular user sessions
            $stmt = $this->pdo->prepare("
                DELETE FROM active_sessions 
                WHERE is_admin = 0 
                AND last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$this->sessionTimeout]);
            
            // Clean admin sessions
            $stmt = $this->pdo->prepare("
                DELETE FROM active_sessions 
                WHERE is_admin = 1 
                AND last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$this->adminTimeout]);
            
        } catch (PDOException $e) {
            error_log('Session cleanup error: ' . $e->getMessage());
        }
    }
    
    // Check if user is admin based on current session
    public function isCurrentUserAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    // Check if current user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && $this->validateSession();
    }
    
    // Regenerate session ID if needed
    private function regenerateSessionIfNeeded() {
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } else if (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    // Generate secure token
    private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    // Set remember me token
    private function setRememberMeToken($userId) {
        $token = $this->generateSecureToken();
        $hashedToken = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO remember_tokens (user_id, token_hash, expires_at) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE token_hash = ?, expires_at = ?
            ");
            $stmt->execute([$userId, $hashedToken, $expires, $hashedToken, $expires]);
            
            // Set cookie
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
            
        } catch (PDOException $e) {
            error_log('Remember me token error: ' . $e->getMessage());
        }
    }
    
    // Log session activity
    private function logSessionActivity($userId, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $action,
                $description,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT']
            ]);
            
        } catch (PDOException $e) {
            error_log('Activity logging error: ' . $e->getMessage());
        }
    }
}

// Global session manager instance
$sessionManager = new SessionManager($pdo);

// Enhanced helper functions for backward compatibility
function isLoggedIn() {
    global $sessionManager;
    return $sessionManager->isLoggedIn();
}

function isAdmin() {
    global $sessionManager;
    return $sessionManager->isCurrentUserAdmin();
}

function validateSession() {
    global $sessionManager;
    return $sessionManager->validateSession();
}

function validateUserSession() {
    global $sessionManager;
    return $sessionManager->validateSession();
}

function destroySession() {
    global $sessionManager;
    return $sessionManager->destroyCurrentSession();
}

// Additional helper functions
function createSecureUserSession($user) {
    global $sessionManager;
    return $sessionManager->createUserSession($user);
}

function manageConcurrentSessions($userId, $userRole) {
    global $sessionManager;
    // This is now handled automatically by the SessionManager
    return true;
}

function hasSessionConflict() {
    // Session conflicts are now prevented by the session manager
    return false;
}

function resolveSessionConflict() {
    // Not needed with the new session manager
    return true;
}

function updateAdminActivity($userId) {
    global $sessionManager;
    // Activity is updated automatically by validateSession()
    return true;
}

// Auto-cleanup expired sessions (run periodically)
if (rand(1, 100) === 1) { // 1% chance to run cleanup
    $sessionManager->cleanupExpiredSessions();
}
?>