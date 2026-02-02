import './bootstrap';

// Import Bootstrap JS components
import 'bootstrap';

// Admin template JS - commented out until all dependencies are installed
// Requires: alpinejs, chart.js, and other dependencies
// import '../admin-template/js/main'

// Basic sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const adminWrapper = document.getElementById('admin-wrapper');
    const adminSidebar = document.getElementById('admin-sidebar');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    
    if (sidebarToggle && adminSidebar) {
        sidebarToggle.addEventListener('click', function() {
            adminWrapper?.classList.toggle('sidebar-open');
            adminSidebar.classList.toggle('show');
            backdrop?.classList.toggle('show');
        });
        
        backdrop?.addEventListener('click', function() {
            adminWrapper?.classList.remove('sidebar-open');
            adminSidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    }
});