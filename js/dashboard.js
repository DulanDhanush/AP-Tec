/* Dashboard Logic */

document.addEventListener("DOMContentLoaded", () => {
    // 1. Highlight Active Menu Item
    const currentPath = window.location.pathname.split("/").pop();
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        if(link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        }
    });

    // 2. Simple 'Logout' Confirmation
    const logoutBtn = document.querySelector('.logout-btn');
    if(logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if(confirm("Are you sure you want to end your session?")) {
                window.location.href = "../index.html"; // Go back to landing
            }
        });
    }
});