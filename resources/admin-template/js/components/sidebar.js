// ==========================================================================
// Sidebar Manager - Handle sidebar toggle and state
// Desktop: toggle between full and collapsed sidebar
// Mobile (<992px): toggle overlay sidebar with backdrop
// ==========================================================================

const MOBILE_BREAKPOINT = 992;

export class SidebarManager {
  constructor() {
    this.wrapper = document.getElementById('admin-wrapper');
    this.sidebar = document.getElementById('admin-sidebar');
    this.toggleButton = document.querySelector('[data-sidebar-toggle]');
    this.backdrop = document.querySelector('.sidebar-backdrop');

    if (!this.wrapper || !this.sidebar || !this.toggleButton) {
      console.warn('SidebarManager: Essential elements not found. Manager will be inactive.');
      return;
    }

    // Create backdrop if it doesn't exist in HTML
    if (!this.backdrop) {
      this.backdrop = document.createElement('div');
      this.backdrop.className = 'sidebar-backdrop';
      this.wrapper.appendChild(this.backdrop);
    }

    this.init();
    this.bindEvents();
  }

  get isMobile() {
    return window.innerWidth < MOBILE_BREAKPOINT;
  }

  init() {
    // Restore desktop collapsed state
    if (!this.isMobile) {
      const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
      if (isCollapsed) {
        this.wrapper.classList.add('sidebar-collapsed');
        this.toggleButton.classList.add('is-active');
      }
    }
  }

  bindEvents() {
    // Toggle button click
    this.toggleButton.addEventListener('click', () => this.toggle());

    // Close sidebar when backdrop is clicked
    this.backdrop.addEventListener('click', () => this.closeMobile());

    // Close mobile sidebar on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.isMobile && this.sidebar.classList.contains('show')) {
        this.closeMobile();
      }
    });

    // Handle window resize: clean up mobile state when switching to desktop
    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(() => this.handleResize(), 150);
    });
  }

  toggle() {
    if (this.isMobile) {
      this.toggleMobile();
    } else {
      this.toggleDesktop();
    }
  }

  // --- Desktop behavior: collapse/expand sidebar ---
  toggleDesktop() {
    const isCurrentlyCollapsed = this.wrapper.classList.contains('sidebar-collapsed');
    if (isCurrentlyCollapsed) {
      this.expandDesktop();
    } else {
      this.collapseDesktop();
    }
  }

  collapseDesktop() {
    this.wrapper.classList.add('sidebar-collapsed');
    this.toggleButton.classList.add('is-active');
    localStorage.setItem('sidebar-collapsed', 'true');
  }

  expandDesktop() {
    this.wrapper.classList.remove('sidebar-collapsed');
    this.toggleButton.classList.remove('is-active');
    localStorage.setItem('sidebar-collapsed', 'false');
  }

  // --- Mobile behavior: overlay sidebar with backdrop ---
  toggleMobile() {
    if (this.sidebar.classList.contains('show')) {
      this.closeMobile();
    } else {
      this.openMobile();
    }
  }

  openMobile() {
    this.sidebar.classList.add('show');
    this.backdrop.classList.add('show');
    document.body.style.overflow = 'hidden'; // prevent background scroll
  }

  closeMobile() {
    this.sidebar.classList.remove('show');
    this.backdrop.classList.remove('show');
    document.body.style.overflow = '';
  }

  // Clean up when crossing the mobile/desktop breakpoint
  handleResize() {
    if (!this.isMobile) {
      // Switched to desktop: remove mobile overlay state
      this.sidebar.classList.remove('show');
      this.backdrop.classList.remove('show');
      document.body.style.overflow = '';

      // Restore desktop collapsed state
      const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
      if (isCollapsed) {
        this.wrapper.classList.add('sidebar-collapsed');
        this.toggleButton.classList.add('is-active');
      }
    } else {
      // Switched to mobile: ensure sidebar is hidden
      this.sidebar.classList.remove('show');
      this.backdrop.classList.remove('show');
      document.body.style.overflow = '';
    }
  }
}
