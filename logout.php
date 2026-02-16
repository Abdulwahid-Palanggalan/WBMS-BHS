<?php
session_start();

// Check if logout was requested
if (isset($_POST['logout'])) {
    // Destroy all session data
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finally, destroy the session
    session_destroy();

    // Redirect to login page with success message
    header("Location: login.php?logout=success");
    exit();
} else {
    // If someone tries to access logout.php directly, redirect to dashboard
    header("Location: dashboard.php");
    exit();
}
?>