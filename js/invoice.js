/* Invoice Generator Logic */

document.addEventListener("DOMContentLoaded", () => {
    
    // --- LIVE UPDATE REFERENCES ---
    // Inputs
    const inpClient = document.getElementById('inpClient');
    const inpDate = document.getElementById('inpDate');
    const inpDue = document.getElementById('inpDue');
    const lineItemsContainer = document.getElementById('lineItemsContainer');
    const btnAddRow = document.getElementById('btnAddRow');
    const btnPrint = document.getElementById('btnPrint');

    // Outputs (Paper)
    const outClient = document.getElementById('outClient');
    const outDate = document.getElementById('outDate');
    const outInvNum = document.getElementById('outInvNum');
    const outTableBody = document.getElementById('outTableBody');
    const outSubtotal = document.getElementById('outSubtotal');
    const outTax = document.getElementById('outTax');
    const outTotal = document.getElementById('outTotal');

    // --- FUNCTIONS ---

    // 1. Update Header Info
    const updateHeader = () => {
        outClient.innerText = inpClient.value || "Client Name";
        // Format Date
        const dateObj = new Date(inpDate.value);
        if(inpDate.value) outDate.innerText = dateObj.toLocaleDateString();
    };

    // 2. Add New Row
    const addRow = () => {
        const div = document.createElement('div');
        div.className = 'line-item-row fade-in';
        div.innerHTML = `
            <input type="text" class="modal-input item-desc" placeholder="Description (e.g. Toner)">
            <input type="number" class="modal-input item-qty" value="1" min="1">
            <input type="number" class="modal-input item-price" placeholder="0.00">
            <button class="btn-icon btn-delete remove-row"><i class="fa-solid fa-trash"></i></button>
        `;
        lineItemsContainer.appendChild(div);

        // Add listeners to new inputs for live calculation
        div.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', calculateTotals);
        });

        // Add delete listener
        div.querySelector('.remove-row').addEventListener('click', () => {
            div.remove();
            calculateTotals();
        });
    };

    // 3. Calculate Totals & Render Table
    const calculateTotals = () => {
        let subtotal = 0;
        let htmlRows = '';

        const rows = document.querySelectorAll('.line-item-row');
        
        rows.forEach(row => {
            const desc = row.querySelector('.item-desc').value || "Item";
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const lineTotal = qty * price;

            subtotal += lineTotal;

            // Generate HTML for the paper view
            htmlRows += `
                <tr>
                    <td>${desc}</td>
                    <td class="text-right">${qty}</td>
                    <td class="text-right">$${price.toFixed(2)}</td>
                    <td class="text-right">$${lineTotal.toFixed(2)}</td>
                </tr>
            `;
        });

        // Render Rows
        outTableBody.innerHTML = htmlRows;

        // Calculate Final Math
        const taxRate = 0.10; // 10% Tax
        const tax = subtotal * taxRate;
        const total = subtotal + tax;

        // Render Totals
        outSubtotal.innerText = `$${subtotal.toFixed(2)}`;
        outTax.innerText = `$${tax.toFixed(2)}`;
        outTotal.innerText = `$${total.toFixed(2)}`;
    };

    // --- EVENTS ---

    // Listeners for Header Inputs
    inpClient.addEventListener('input', updateHeader);
    inpDate.addEventListener('input', updateHeader);

    // Add Row Button
    btnAddRow.addEventListener('click', addRow);

    // Print Button
    btnPrint.addEventListener('click', () => {
        window.print();
    });

    // Initialize
    const today = new Date().toISOString().split('T')[0];
    inpDate.value = today;
    updateHeader();
    
    // Generate Random Invoice Num
    outInvNum.innerText = "INV-" + Math.floor(1000 + Math.random() * 9000);
});