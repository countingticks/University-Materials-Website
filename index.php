<?php
// The Front Controller for the PHP Built-in Web Server

// 1. Static File Routing
// This is the key to making CSS, JS, and images work with the built-in server.
// It checks if the requested URI is a real file, and if so, tells PHP to serve it directly.
if (php_sapi_name() === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = __DIR__ . $uri;
    if (is_file($path)) {
        return false; // Serve the requested file as-is.
    }
}

// 2. Load Configuration and Core Functions
// This is now the one and only place where the config is loaded.
require_once __DIR__ . '/src/config/config.php';

// 2.5. Handle logout parameter (for any remaining ?logout=1 links)
if (isset($_GET['logout']) && isLoggedIn()) {
    // Redirect to proper logout route
    header("Location: /logout");
    exit();
}

// 3. Application Routing
// Get the requested URI from the server, not a GET parameter.
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = trim($requestUri, '/');

// Handle the root URL ('') as a special case, redirecting based on login status.
if ($route === '') {
    if (isLoggedIn()) {
        header("Location: /home");
    } else {
        header("Location: /login");
    }
    exit();
}

// Route the request to the correct page
switch ($route) {
    case 'home':
        requireLogin();
        include __DIR__ . '/src/home/home.php';
        break;
    case 'home/download':
        requireLogin();
        include __DIR__ . '/src/home/download.php';
        break;
    case 'home/view':
        requireLogin();
        include __DIR__ . '/src/home/view.php';
        break;

    case 'login':
        // If the user is already logged in, send them to the home page.
        if (isLoggedIn()) {
            header("Location: /home");
            exit();
        }
        include __DIR__ . '/src/login/login.php';
        break;

    case 'admin':
        requireLogin();
        requireAdmin();
        include __DIR__ . '/src/admin/admin.php';
        break;

    case 'logout':
        // Handle logout logic directly inside the router
        if (isLoggedIn()) {
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
        
        // Add cache busting and no-cache headers for clean logout
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
        header("Location: /login?t=" . time()); // Add timestamp to prevent caching
        exit();
        break;

    default:
        // If no route matches, it's a 404 error.
        http_response_code(404);
        echo "<h1>404 Page Not Found</h1>";
        exit();
}
?> 