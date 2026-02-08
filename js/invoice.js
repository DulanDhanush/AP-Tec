// js/invoice.js
(() => {
  const inpClient = document.getElementById("inpClient");
  const inpDate = document.getElementById("inpDate");
  const inpDue = document.getElementById("inpDue");

  const outInvNum = document.getElementById("outInvNum");
  const outDate = document.getElementById("outDate");
  const outClient = document.getElementById("outClient");

  const lineItemsContainer = document.getElementById("lineItemsContainer");
  const outTableBody = document.getElementById("outTableBody");

  const outSubtotal = document.getElementById("outSubtotal");
  const outTax = document.getElementById("outTax");
  const outTotal = document.getElementById("outTotal");

  const btnAddRow = document.getElementById("btnAddRow");
  const btnPrint = document.getElementById("btnPrint");

  const TAX_RATE = 0.1;

  function esc(s) {
    return String(s ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function money(v) {
    const n = Number.isFinite(v) ? v : 0;
    return `$${n.toFixed(2)}`;
  }

  function formatDateHuman(yyyy_mm_dd) {
    if (!yyyy_mm_dd) return "-";
    const d = new Date(yyyy_mm_dd + "T00:00:00");
    if (isNaN(d.getTime())) return yyyy_mm_dd;
    const opts = { year: "numeric", month: "short", day: "2-digit" };
    return d.toLocaleDateString(undefined, opts);
  }

  function genInvoiceNo() {
    // simple local invoice number (you can replace with DB later)
    const rand = Math.floor(1000 + Math.random() * 9000);
    return `INV-${rand}`;
  }

  function rowTemplate() {
    return `
      <div class="line-item-row" style="display:grid; grid-template-columns: 1.6fr .5fr .7fr auto; gap:10px; margin-bottom:10px;">
        <input class="modal-input inpDesc" placeholder="Description" />
        <input class="modal-input inpQty" type="number" step="0.01" min="0" value="1" placeholder="Qty" />
        <input class="modal-input inpPrice" type="number" step="0.01" min="0" value="0" placeholder="Price" />
        <button class="btn-glass btnRemove" type="button" title="Remove" style="padding:10px 12px;">
          <i class="fa-solid fa-trash"></i>
        </button>
      </div>
    `;
  }

  function addRow(prefill = {}) {
    if (!lineItemsContainer) return;
    const wrap = document.createElement("div");
    wrap.innerHTML = rowTemplate();
    const row = wrap.firstElementChild;

    const desc = row.querySelector(".inpDesc");
    const qty = row.querySelector(".inpQty");
    const price = row.querySelector(".inpPrice");
    const btnRemove = row.querySelector(".btnRemove");

    desc.value = prefill.description ?? "";
    qty.value = prefill.qty ?? 1;
    price.value = prefill.price ?? 0;

    const onChange = () => renderPreview();

    desc.addEventListener("input", onChange);
    qty.addEventListener("input", onChange);
    price.addEventListener("input", onChange);

    btnRemove.addEventListener("click", () => {
      row.remove();
      renderPreview();
    });

    lineItemsContainer.appendChild(row);
    renderPreview();
  }

  function collectItems() {
    const items = [];
    document.querySelectorAll(".line-item-row").forEach((row) => {
      const description = row.querySelector(".inpDesc")?.value?.trim() || "";
      const qty = parseFloat(row.querySelector(".inpQty")?.value || "0");
      const price = parseFloat(row.querySelector(".inpPrice")?.value || "0");
      if (description && qty > 0) items.push({ description, qty, price });
    });
    return items;
  }

  function renderPreview() {
    // client + date
    if (outClient)
      outClient.textContent = inpClient?.value?.trim() || "Client Name";
    if (outDate) outDate.textContent = formatDateHuman(inpDate?.value || "");

    // items table
    const items = collectItems();

    if (outTableBody) {
      if (!items.length) {
        outTableBody.innerHTML = "";
      } else {
        outTableBody.innerHTML = items
          .map((it) => {
            const lineTotal = it.qty * it.price;
            return `
              <tr>
                <td>${esc(it.description)}</td>
                <td class="text-right" style="text-align:right;">${it.qty.toFixed(2)}</td>
                <td class="text-right" style="text-align:right;">${money(it.price)}</td>
                <td class="text-right" style="text-align:right;">${money(lineTotal)}</td>
              </tr>
            `;
          })
          .join("");
      }
    }

    // totals
    const subtotal = items.reduce((sum, it) => sum + it.qty * it.price, 0);
    const tax = subtotal * TAX_RATE;
    const total = subtotal + tax;

    if (outSubtotal) outSubtotal.textContent = money(subtotal);
    if (outTax) outTax.textContent = money(tax);
    if (outTotal) outTotal.textContent = money(total);
  }

  function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename || "invoice.pdf";
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  async function printPDF() {
    const invoice_no = outInvNum?.textContent?.trim() || "INV-0000";
    const issue_date = inpDate?.value || "";
    const due_date = inpDue?.value || "";
    const client = inpClient?.value?.trim() || "Client";

    const items = collectItems();
    if (!items.length) {
      alert("Add at least one invoice item.");
      return;
    }

    const payload = {
      invoice_no,
      issue_date,
      due_date,
      client,
      address: "", // optional
      tax_rate: TAX_RATE,
      items,
    };

    const res = await fetch("../php/invoice_pdf.php", {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const t = await res.text();
      alert("PDF failed: " + t);
      return;
    }

    const blob = await res.blob();
    downloadBlob(blob, `${invoice_no}.pdf`);
  }

  // Wire events
  inpClient?.addEventListener("input", renderPreview);
  inpDate?.addEventListener("change", renderPreview);

  btnAddRow?.addEventListener("click", () => addRow());
  btnPrint?.addEventListener("click", printPDF);

  // Init
  if (outInvNum) outInvNum.textContent = genInvoiceNo();

  // default issue date = today
  if (inpDate && !inpDate.value) {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    inpDate.value = `${yyyy}-${mm}-${dd}`;
  }

  // add one starter row
  addRow({ description: "Service", qty: 1, price: 0 });
})();
