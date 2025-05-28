<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Clear the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redirect to login page with logout message
header("Location: login.php?message=logged_out");
exit();
?>