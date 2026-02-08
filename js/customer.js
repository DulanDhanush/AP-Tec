/* Customer Portal Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Animate Progress Bar (Visual Effect)
    const progressBar = document.querySelector('.track-progress-bar');
    if(progressBar) {
        // Set to 75% width (Repairing Stage)
        setTimeout(() => {
            progressBar.style.width = "66%"; 
        }, 500);
    }

    // 2. New Request Modal Logic
    const modal = document.getElementById('requestModal');
    const btnNewReq = document.getElementById('btnNewRequest');
    const btnClose = document.querySelector('.close-modal');
    const btnCancel = document.querySelector('.btn-cancel');

    if(btnNewReq) {
        btnNewReq.addEventListener('click', () => {
            modal.classList.add('active');
        });
    }

    const closeModal = () => modal.classList.remove('active');

    if(btnClose) btnClose.addEventListener('click', closeModal);
    if(btnCancel) btnCancel.addEventListener('click', closeModal);

    window.addEventListener('click', (e) => {
        if(e.target === modal) closeModal();
    });

    // 3. Tab Switching for Order History
    const tabs = document.querySelectorAll('.tab-btn');
    const historyRows = document.querySelectorAll('tbody tr');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            // Mock filter logic
            // In real app, this filters the table rows
        });
    });
});
