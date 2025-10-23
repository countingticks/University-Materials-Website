    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <!-- Global JavaScript -->
    <script src="/src/assets/global.js"></script>
    
    <!-- Page-specific JS -->
    <?php
    $currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
    if ($currentPage === 'index' && strpos($_SERVER['REQUEST_URI'], '/admin') !== false) {
        $currentPage = 'admin';
    } else if ($currentPage === 'index' && strpos($_SERVER['REQUEST_URI'], '/home') !== false) {
        $currentPage = 'home';
    } else if ($currentPage === 'index' && strpos($_SERVER['REQUEST_URI'], '/login') !== false) {
        $currentPage = 'login';
    }
    
    switch($currentPage) {
        case 'login':
            echo '<script src="/src/login/login.js"></script>';
            break;
        case 'home':
            echo '<script src="/src/home/home.js"></script>';
            break;
        case 'admin':
            echo '<script src="/src/admin/admin.js"></script>';
            break;
    }
    ?>
</body>
</html> 