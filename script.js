/**
 * File: script.js
 * Description: Client-side interactive scripts including theme initialization, theme toggling, and side-navigation drawer triggers.
 * Importance: Drives page transitions, user interaction, drawer animations, and responsive menu behavior.
 */

// Apply theme immediately on script load (in addition to inline head scripts)
(function() {
  const currentTheme = localStorage.getItem('theme');
  if (currentTheme === 'light') {
    document.documentElement.classList.add('light-mode');
  }
})();

// Side Navigation Drawer Toggle
function toggleMenu() {
  const menuBtn = document.querySelector('.menu-btn');
  const navMenu = document.getElementById('side-nav-menu');
  if (menuBtn && navMenu) {
    menuBtn.classList.toggle('active');
    navMenu.classList.toggle('open');
  }
}

// Close side menu if user clicks outside of the menu container
document.addEventListener('click', function(e) {
  const menuBtn = document.querySelector('.menu-btn');
  const navMenu = document.getElementById('side-nav-menu');
  if (navMenu && navMenu.classList.contains('open')) {
    // If click was outside both the drawer and the menu button, close it
    if (!navMenu.contains(e.target) && !menuBtn.contains(e.target)) {
      toggleMenu();
    }
  }
});

// Theme Toggle Handler
function toggleTheme() {
  const isLight = document.documentElement.classList.toggle('light-mode');
  localStorage.setItem('theme', isLight ? 'light' : 'dark');
}

// Future Improvements: Modularize UI event handlers, implement debouncing on resize/scroll events, and add custom event triggers.
