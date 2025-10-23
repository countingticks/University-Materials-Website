// Home page specific functionality

ready(function() {
    // Welcome animations
    function animateWelcome() {
        const content = document.querySelector('.content');
        if (content) {
            content.style.opacity = '0';
            content.style.transform = 'translateY(20px)';
            content.style.transition = 'all 0.6s ease';
            
            setTimeout(() => {
                content.style.opacity = '1';
                content.style.transform = 'translateY(0)';
            }, 100);
        }
    }

    // Add smooth fade-in animation
    animateWelcome();

    // Quick actions for future features
    const quickActions = {
        newProject: function() {
            Utils.showNotification('Funcționalitatea "Proiect Nou" va fi disponibilă în curând', 'info');
        },

        openGallery: function() {
            Utils.showNotification('Galeria va fi disponibilă în curând', 'info');
        },

        viewReports: function() {
            Utils.showNotification('Rapoartele vor fi disponibile în curând', 'info');
        }
    };

    // Make available globally for future use
    window.homeActions = quickActions;

    // Add keyboard shortcuts for home page
    document.addEventListener('keydown', function(e) {
        // Alt + N for new project
        if (e.altKey && e.key.toLowerCase() === 'n') {
            e.preventDefault();
            quickActions.newProject();
        }
        
        // Alt + G for gallery
        if (e.altKey && e.key.toLowerCase() === 'g') {
            e.preventDefault();
            quickActions.openGallery();
        }
        
        // Alt + R for reports
        if (e.altKey && e.key.toLowerCase() === 'r') {
            e.preventDefault();
            quickActions.viewReports();
        }
    });

    // Future: Add dashboard widgets
    function initializeDashboard() {
        // This function will be expanded when adding dashboard features
        console.log('🏠 Pagina principală a fost încărcată');
        
        // Placeholder for future dashboard initialization
        const dashboardArea = document.querySelector('.content');
        if (dashboardArea && dashboardArea.children.length === 0) {
            // If content is empty, we could add some welcome content
            const welcomeText = document.createElement('div');
            welcomeText.className = 'home-welcome';
            welcomeText.innerHTML = `
                <h2 style="text-align: center; color: #667eea; margin-bottom: 2rem;">
                    Bine ați venit la Grafică!
                </h2>
                <p style="text-align: center; color: #4a5568; font-size: 1.1rem;">
                    Platforma dumneavoastră pentru design grafic și creativitate.
                </p>
            `;
            dashboardArea.appendChild(welcomeText);
        }
    }

    // Initialize dashboard
    initializeDashboard();

    // Activity logger for analytics (future feature)
    function logActivity(action) {
        // In a real application, this would send data to analytics
        console.log(`Activitate: ${action} la ${new Date().toISOString()}`);
    }

    // Log page visit
    logActivity('Vizitare pagină principală');

    // Define fallbacks only if not already defined by page script
    if (typeof window.downloadCourse !== 'function') {
        window.downloadCourse = function(courseId) {
            window.open(`/src/home/download.php?id=${courseId}`, '_blank');
        };
    }
    if (typeof window.viewCourse !== 'function') {
        window.viewCourse = function(courseId) {
            window.open(`/src/home/view.php?id=${courseId}`, '_blank');
        };
    }

    // Time tracking
    const startTime = Date.now();
    
    window.addEventListener('beforeunload', function() {
        const timeSpent = Math.round((Date.now() - startTime) / 1000);
        logActivity(`Timp petrecut pe pagină: ${timeSpent} secunde`);
    });

    // Add ripple effect to interactive elements (future enhancement)
    function addRippleEffect() {
        const buttons = document.querySelectorAll('button, .btn');
        
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                ripple.className = 'ripple';
                ripple.style.left = e.offsetX + 'px';
                ripple.style.top = e.offsetY + 'px';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    }

    // Initialize ripple effects
    addRippleEffect();
}); 