<?php
// Website Configuration File

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Website settings
define('SITE_NAME', 'Grafică');

// Database configuration - SQL Server
define('DB_HOST', '192.168.1.1'); // Local SQL Server host (same machine as web server)
define('DB_NAME', 'grafica'); // Database name
define('DB_USER', 'grafica'); // SQL Server username
define('DB_PASS', 'PwA7IoT544RhLW'); // Password for the 'grafica' user (set if not empty)
define('DB_PORT', '1433'); // Default SQL Server port

// Session settings
define('SESSION_NAME', 'my_website_session');

// File upload settings (used by admin upload UI and validation)
define('MAX_FILE_SIZE', 52428800); // 50MB

// Time zone
date_default_timezone_set('Europe/Bucharest'); // Change to your timezone

// Start session with secure settings (adjusted for localhost development)
if (session_status() === PHP_SESSION_NONE) {
ini_set('session.cookie_httponly', 1);
// Only use secure cookies in production with HTTPS
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS
ini_set('session.use_strict_mode', 1);
session_name(SESSION_NAME);
session_start();
}

// Database connection function for SQL Server
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // Check if SQL Server PDO driver is available
            if (!extension_loaded('pdo_sqlsrv')) {
                throw new Exception("SQL Server PDO driver (pdo_sqlsrv) is not installed. Please install Microsoft Drivers for PHP for SQL Server.");
            }
            
            // Build connection string based on configuration
            $server = DB_HOST;
            if (!empty(DB_PORT)) {
                $server .= ',' . DB_PORT; // sqlsrv expects host,port
            }
            // Use the working LocalDB connection settings
            if (empty(DB_USER) && empty(DB_PASS)) {
                // Windows Authentication (for LocalDB or Windows integrated security)
                $dsn = "sqlsrv:Server=" . $server . ";Database=" . DB_NAME . ";TrustServerCertificate=true";
                $username = null;
                $password = null;
            } else {
                // SQL Server Authentication (for remote servers)
                $dsn = "sqlsrv:Server=" . $server . ";Database=" . DB_NAME . ";TrustServerCertificate=true";
                $username = DB_USER;
                $password = DB_PASS;
            }
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, $username, $password, $options);
            
        } catch (PDOException $e) {
            $errorMsg = "Database connection failed: " . $e->getMessage();
            
            if (strpos($e->getMessage(), 'could not find driver') !== false) {
                $errorMsg .= "\nInstall and enable pdo_sqlsrv + sqlsrv, then restart Apache.";
            }
            
            error_log($errorMsg);
            die($errorMsg);
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            die($e->getMessage());
        }
    }
    
    return $pdo;
}

// Utility functions
function redirectTo($url) {
    header("Location: $url");
    exit();
}

// Authentication System - Database-driven
require_once __DIR__ . '/database.php';

// Authentication functions
function validateUser($username, $password) {
    return validateUserLogin($username, $password);
}

function login($username, $password) {
    $user = validateUser($username, $password);
    
    if ($user) {
        // Clear any existing session data first
        $_SESSION = array();
        
        // Regenerate session ID for security (only if headers haven't been sent)
        if (!headers_sent()) {
        session_regenerate_id(true);
        }
        
        // Store user info in session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['group_name'] = $user['group_name'] ?? null;
        $_SESSION['login_time'] = time();
        
        return true;
    }
    
    return false;
}

function logout() {
    // Clear all session data
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page using clean URL
    redirectTo('/login');
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function clearSession() {
    // Complete session cleanup
    $_SESSION = array();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session if headers not sent
    if (!headers_sent()) {
        session_destroy();
        session_start(); // Restart for new session
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirectTo('/login');
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        redirectTo('/home');
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return $_SESSION['username'];
    }
    return null;
}
