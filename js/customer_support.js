/* Customer Support Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // 1. FAQ Accordion Logic
    const accHeaders = document.querySelectorAll('.accordion-header');

    accHeaders.forEach(header => {
        header.addEventListener('click', () => {
            const item = header.parentElement;
            const body = item.querySelector('.accordion-body');
            
            // Close others (Optional - currently allows multiple open)
            // document.querySelectorAll('.accordion-item').forEach(i => {
            //     if(i !== item) {
            //         i.classList.remove('active');
            //         i.querySelector('.accordion-body').style.maxHeight = null;
            //     }
            // });

            // Toggle Current
            item.classList.toggle('active');
            
            if (item.classList.contains('active')) {
                body.style.maxHeight = body.scrollHeight + "px";
            } else {
                body.style.maxHeight = null;
            }
        });
    });

    // 2. New Ticket Modal Logic
    const modal = document.getElementById('ticketModal');
    const btnOpen = document.getElementById('btnCreateTicket');
    const btnClose = document.querySelector('.close-modal');
    const btnCancel = document.querySelector('.btn-cancel');
    const formTicket = document.getElementById('ticketForm');

    if(btnOpen) btnOpen.addEventListener('click', () => modal.classList.add('active'));
    
    const closeModal = () => modal.classList.remove('active');
    
    if(btnClose) btnClose.addEventListener('click', closeModal);
    if(btnCancel) btnCancel.addEventListener('click', closeModal);
    
    window.addEventListener('click', (e) => {
        if(e.target === modal) closeModal();
    });

    // 3. Submit Ticket Simulation
    if(formTicket) {
        formTicket.addEventListener('submit', (e) => {
            e.preventDefault();
            alert("Ticket #9924 Created Successfully! A technician will review it shortly.");
            closeModal();
        });
    }
});

document.addEventListener("DOMContentLoaded", () => {
    // ... (Keep existing Accordion & New Ticket logic) ...

    /* --- EDIT TICKET MODAL LOGIC --- */
    const editModal = document.getElementById('editTicketModal');
    const closeEdit = document.getElementById('closeEditModal');
    const cancelEdit = document.getElementById('cancelEdit');

    // Function to Open Edit Modal
    window.openEditModal = (ticketId) => {
        // In a real app, you'd fetch data here. For now, we simulate.
        document.getElementById('editTicketId').innerText = ticketId;
        editModal.classList.add('active');
        
        // Close the dropdown menu if it's still open
        document.querySelectorAll('.action-menu.active').forEach(m => m.classList.remove('active'));
    };

    // Close Logic
    const hideEdit = () => editModal.classList.remove('active');
    if(closeEdit) closeEdit.addEventListener('click', hideEdit);
    if(cancelEdit) cancelEdit.addEventListener('click', hideEdit);
    
    // Save Changes (Simulation)
    const editForm = document.getElementById('editTicketForm');
    if(editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            alert("Ticket Updated Successfully!");
            hideEdit();
        });
    }
});