<?php
// Include config for authentication functions
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>Grafică | UTCN</title>
    <meta name="description" content="<?php echo isset($pageDescription) ? htmlspecialchars($pageDescription) : 'Platformă de cursuri pentru disciplina Grafică Computerizată - Universitatea Tehnică din Cluj-Napoca'; ?>">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/src/assets/global.css">
    <link rel="stylesheet" href="/src/components/header.css">
    
    <!-- Page-specific CSS -->
    <?php
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $currentPage = trim($currentPath, '/');
    switch($currentPage) {
        case 'login':
            echo '<link rel="stylesheet" href="/src/login/login.css">';
            break;
        case 'home':
            echo '<link rel="stylesheet" href="/src/home/home.css">';
            // Reuse admin styles for unified filters and pager appearance
            echo '<link rel="stylesheet" href="/src/admin/admin.css">';
            break;
        case 'admin':
            echo '<link rel="stylesheet" href="/src/admin/admin.css">';
            break;
    }
    ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    
    <!-- Additional meta tags for academic platform -->
    <meta name="keywords" content="grafică computerizată, UTCN, cursuri, universidad tehnică cluj">
    <meta name="author" content="Universitatea Tehnică din Cluj-Napoca">
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="<?php echo basename($_SERVER['SCRIPT_NAME'], '.php') . '-body'; ?>">
    <?php if (isLoggedIn()): ?>
    <header>
        <nav>
            <div class="container">
                    <ul>
                        <?php if (isAdmin()): ?>
                        <li>
                            <?php $isHomeActive = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/home'; ?>
                            <a href="/home" <?php echo $isHomeActive ? 'style="color: #3498db"' : ''; ?>>
                                Acasă
                            </a>
                        </li>
                            <li>
                                <?php $isAdminActive = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/admin'; ?>
                                <a href="/admin" <?php echo $isAdminActive ? 'style="color: #3498db"' : ''; ?>>
                                    Panou Admin
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="user-menu">
                        <span><?php echo htmlspecialchars(getCurrentUser()); ?></span>
                        <a href="/logout" class="logout-btn">
                            Deconectare
                        </a>
                    </div>
            </div>
        </nav>
    </header> 
    <?php endif; ?> 