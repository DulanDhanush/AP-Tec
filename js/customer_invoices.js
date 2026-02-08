/* Customer Invoices & Payment Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // 1. Filter Tabs
    const tabs = document.querySelectorAll('.tab-btn');
    const invoiceRows = document.querySelectorAll('.invoice-row');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const filter = tab.getAttribute('data-filter');

            invoiceRows.forEach(row => {
                const status = row.getAttribute('data-status');
                if (filter === 'all') {
                    row.style.display = '';
                } else if (filter === 'unpaid') {
                    if (status === 'Unpaid' || status === 'Overdue') row.style.display = '';
                    else row.style.display = 'none';
                } else if (filter === 'paid') {
                    if (status === 'Paid') row.style.display = '';
                    else row.style.display = 'none';
                }
            });
        });
    });

    // 2. Payment Modal Logic
    const modal = document.getElementById('payModal');
    const btnClose = document.querySelector('.close-modal');
    const payBtns = document.querySelectorAll('.btn-pay-now');
    const formPay = document.getElementById('paymentForm');
    const modalAmount = document.getElementById('modalAmount');
    const modalInvNum = document.getElementById('modalInvNum');
    
    let currentRow = null; // Store which row triggered the modal

    payBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            currentRow = e.target.closest('tr');
            const id = currentRow.querySelector('td:nth-child(1)').innerText;
            const amount = currentRow.querySelector('td:nth-child(4)').innerText;

            modalInvNum.innerText = id;
            modalAmount.innerText = amount;
            
            modal.classList.add('active');
        });
    });

    // Close Modal
    const closeModal = () => modal.classList.remove('active');
    if(btnClose) btnClose.addEventListener('click', closeModal);
    window.addEventListener('click', (e) => {
        if(e.target === modal) closeModal();
    });

    // 3. Simulate Payment Processing
    if(formPay) {
        formPay.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const btnSubmit = formPay.querySelector('button[type="submit"]');
            const originalText = btnSubmit.innerHTML;
            
            // Loading State
            btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            btnSubmit.disabled = true;

            setTimeout(() => {
                alert(`Payment Successful! Invoice ${modalInvNum.innerText} marked as Paid.`);
                
                // Update UI (Mark row as paid)
                if(currentRow) {
                    const statusCell = currentRow.querySelector('td:nth-child(5)');
                    statusCell.innerHTML = '<span class="status-badge status-paid">Paid</span>';
                    currentRow.setAttribute('data-status', 'Paid');
                    currentRow.querySelector('.btn-pay-now').remove(); // Remove Pay button
                }

                closeModal();
                btnSubmit.innerHTML = originalText;
                btnSubmit.disabled = false;
            }, 2000);
        });
    }
});