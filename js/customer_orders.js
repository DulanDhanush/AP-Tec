/* Customer Orders Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Filter Tabs (Active vs History)
    const tabs = document.querySelectorAll('.tab-btn');
    const orderRows = document.querySelectorAll('.order-row');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Active State UI
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const filter = tab.getAttribute('data-filter');

            // Show/Hide Rows
            orderRows.forEach(row => {
                const status = row.getAttribute('data-status');
                if (filter === 'all') {
                    row.style.display = '';
                } else if (filter === 'active') {
                    if (status === 'Processing' || status === 'Shipped') row.style.display = '';
                    else row.style.display = 'none';
                } else if (filter === 'history') {
                    if (status === 'Delivered' || status === 'Cancelled') row.style.display = '';
                    else row.style.display = 'none';
                }
            });
        });
    });

    // 2. Order Details Modal
    const modal = document.getElementById('orderModal');
    const btnClose = document.querySelector('.close-modal');
    const viewBtns = document.querySelectorAll('.btn-view-order');

    // Dynamic Elements
    const modalOrderId = document.getElementById('modalOrderId');
    const modalStatus = document.getElementById('modalStatus');
    const trackBar = document.getElementById('modalTrackBar');

    viewBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const row = e.target.closest('tr');
            const id = row.querySelector('td:nth-child(1)').innerText;
            const status = row.getAttribute('data-status');

            // Update Modal Data
            modalOrderId.innerText = id;
            modalStatus.innerText = status;
            
            // Reset Classes
            modalStatus.className = 'status-badge';
            
            // Logic for Progress Bar inside Modal
            if(status === 'Processing') {
                modalStatus.classList.add('status-processing');
                trackBar.style.width = '33%';
            } else if(status === 'Shipped') {
                modalStatus.classList.add('status-shipped');
                trackBar.style.width = '66%';
            } else if(status === 'Delivered') {
                modalStatus.classList.add('status-delivered');
                trackBar.style.width = '100%';
            }

            modal.classList.add('active');
        });
    });

    // Close Modal Logic
    if(btnClose) btnClose.addEventListener('click', () => modal.classList.remove('active'));
    window.addEventListener('click', (e) => {
        if(e.target === modal) modal.classList.remove('active');
    });

    // 3. Reorder Logic
    const reorderBtns = document.querySelectorAll('.btn-reorder');
    reorderBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            alert("Items added to cart! Proceeding to checkout...");
        });
    });
});