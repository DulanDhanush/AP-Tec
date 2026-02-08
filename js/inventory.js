/* Inventory & Supplier Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // Elements
    const modal = document.getElementById('addModal');
    const modalTitle = document.getElementById('modalTitle');
    const formContainer = document.getElementById('dynamicForm');
    const btnClose = document.querySelector('.close-modal');
    const btnCancel = document.querySelector('.btn-cancel');

    // Button Triggers
    const btnAddStock = document.getElementById('btnAddStock');
    const btnAddSupplier = document.getElementById('btnAddSupplier');

    // --- HTML TEMPLATES FOR FORMS ---
    
    const stockFormHTML = `
        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Item Name</label>
            <input type="text" class="modal-input" placeholder="e.g. HP 85A Black Toner">
        </div>
        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Category</label>
            <select class="modal-input">
                <option>Toner Cartridge</option>
                <option>Drum Unit</option>
                <option>Printer Part</option>
                <option>Maintenance Kit</option>
            </select>
        </div>
        <div style="display: flex; gap: 15px;">
            <div class="form-group" style="flex: 1;">
                <label class="form-label" style="color: var(--secondary);">Quantity</label>
                <input type="number" class="modal-input" placeholder="0">
            </div>
            <div class="form-group" style="flex: 1;">
                <label class="form-label" style="color: var(--secondary);">Alert Threshold</label>
                <input type="number" class="modal-input" placeholder="5">
            </div>
        </div>
    `;

    const supplierFormHTML = `
        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Company Name</label>
            <input type="text" class="modal-input" placeholder="e.g. Global Tech Supplies">
        </div>
        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Contact Email</label>
            <input type="email" class="modal-input" placeholder="orders@company.com">
        </div>
        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Service Type</label>
            <select class="modal-input">
                <option>Hardware Supplier</option>
                <option>Maintenance Contractor</option>
                <option>Logistics Partner</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Next Delivery Date</label>
            <input type="date" class="date-input" style="width: 100%; color: white; background: rgba(2,12,27,0.4);">
        </div>
    `;

    // --- FUNCTIONS ---

    const openModal = (type) => {
        if(type === 'stock') {
            modalTitle.innerText = "Add Inventory Item";
            formContainer.innerHTML = stockFormHTML;
        } else {
            modalTitle.innerText = "Register New Supplier";
            formContainer.innerHTML = supplierFormHTML;
        }
        modal.classList.add('active');
    };

    const closeModal = () => {
        modal.classList.remove('active');
    };

    // --- EVENT LISTENERS ---

    if(btnAddStock) {
        btnAddStock.addEventListener('click', () => openModal('stock'));
    }

    if(btnAddSupplier) {
        btnAddSupplier.addEventListener('click', () => openModal('supplier'));
    }

    if(btnClose) btnClose.addEventListener('click', closeModal);
    if(btnCancel) btnCancel.addEventListener('click', closeModal);

    window.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
});