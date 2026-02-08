/* Owner Dashboard - Chart.js Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // Common Chart Options for "Dark Neon" Look
    Chart.defaults.color = '#8892b0';
    Chart.defaults.font.family = "'Poppins', sans-serif";
    
    // --- CHART 1: REVENUE vs EXPENSES (Line Chart) ---
    const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
    
    // Create Gradients
    const gradientRevenue = ctxRevenue.createLinearGradient(0, 0, 0, 400);
    gradientRevenue.addColorStop(0, 'rgba(100, 255, 218, 0.5)'); // Neon Cyan
    gradientRevenue.addColorStop(1, 'rgba(100, 255, 218, 0.0)');

    const gradientExpense = ctxRevenue.createLinearGradient(0, 0, 0, 400);
    gradientExpense.addColorStop(0, 'rgba(231, 76, 60, 0.5)');   // Red
    gradientExpense.addColorStop(1, 'rgba(231, 76, 60, 0.0)');

    new Chart(ctxRevenue, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Revenue',
                data: [12000, 19000, 15000, 25000, 22000, 30000],
                borderColor: '#64ffda',
                backgroundColor: gradientRevenue,
                fill: true,
                tension: 0.4 // Smooth curves
            }, {
                label: 'Expenses',
                data: [8000, 12000, 10000, 14000, 11000, 13000],
                borderColor: '#e74c3c',
                backgroundColor: gradientExpense,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    backgroundColor: 'rgba(17, 34, 64, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(100, 255, 218, 0.2)',
                    borderWidth: 1
                }
            },
            scales: {
                y: { grid: { color: 'rgba(255, 255, 255, 0.05)' } },
                x: { grid: { display: false } }
            }
        }
    });

    // --- CHART 2: SERVICE CATEGORIES (Doughnut) ---
    const ctxService = document.getElementById('serviceChart').getContext('2d');
    
    new Chart(ctxService, {
        type: 'doughnut',
        data: {
            labels: ['Toner Refill', 'Printer Repair', 'Hardware Sales', 'Maintenance'],
            datasets: [{
                data: [45, 25, 20, 10],
                backgroundColor: [
                    '#64ffda', // Cyan
                    '#4b6cb7', // Blue
                    '#f1c40f', // Yellow
                    '#e74c3c'  // Red
                ],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            },
            cutout: '70%' // Thin ring style
        }
    });
});