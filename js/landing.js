/* * Landing Page Animations
 * Uses Intersection Observer for high-performance scroll detection
 */

document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Select all elements we want to animate
    const observerElements = document.querySelectorAll('.hidden');

    // 2. Setup the Observer
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                // Add the 'show' class when element enters viewport
                entry.target.classList.add('show');
            }
        });
    }, {
        threshold: 0.1 // Trigger when 10% of the element is visible
    });

    // 3. Start observing
    observerElements.forEach((el) => observer.observe(el));
});