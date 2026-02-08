/* Inventory & Supplier Logic (REAL BACKEND) */

document.addEventListener("DOMContentLoaded", () => {
  // ===== API =====
  const API = "../php/inventory_api.php"; // ✅ adjust if your folder differs

  // ===== Elements =====
  const modal = document.getElementById("addModal");
  const modalTitle = document.getElementById("modalTitle");
  const formContainer = document.getElementById("dynamicForm");
  const btnClose = document.querySelector(".close-modal");
  const btnCancel = document.querySelector(".btn-cancel");
  const modalForm = modal.querySelector("form");

  const btnAddStock = document.getElementById("btnAddStock");
  const btnAddSupplier = document.getElementById("btnAddSupplier");

  // These containers exist in your page already
  const inventoryWidget = document.querySelectorAll(".wide-widget")[0];
  const suppliersWidget = document.querySelectorAll(".wide-widget")[1];
  const supplierListEl = suppliersWidget.querySelector(".supplier-list");

  let currentMode = "stock"; // 'stock' or 'supplier'
  let suppliersCache = []; // used for dropdown (optional in future)

  // ===== Templates (UPDATED: added name="" so backend works) =====
  const stockFormHTML = `
        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Item Name</label>
            <input name="item_name" type="text" class="modal-input" placeholder="e.g. HP 85A Black Toner" required>
        </div>

        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Category</label>
            <select name="category" class="modal-input" required>
                <option value="Toner">Toner Cartridge</option>
                <option value="Printer Part">Drum Unit</option>
                <option value="Printer Part">Printer Part</option>
                <option value="Maintenance">Maintenance Kit</option>
                <option value="Stationery">Stationery</option>
                <option value="Network">Network</option>
                <option value="Accessory">Accessory</option>
                <option value="PC Part">PC Part</option>
            </select>
        </div>

        <div style="display: flex; gap: 15px;">
            <div class="form-group" style="flex: 1;">
                <label class="form-label" style="color: var(--secondary);">Quantity</label>
                <input name="quantity" type="number" class="modal-input" placeholder="0" min="0" value="0">
            </div>

            <div class="form-group" style="flex: 1;">
                <label class="form-label" style="color: var(--secondary);">Alert Threshold</label>
                <input name="alert_threshold" type="number" class="modal-input" placeholder="5" min="1" value="5">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Unit Price (LKR)</label>
            <input name="unit_price" type="number" class="modal-input" placeholder="0.00" min="0" step="0.01" value="0">
        </div>

        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Supplier (Optional)</label>
            <select name="supplier_id" class="modal-input">
                <option value="">-- Not Assigned --</option>
                ${"" /* will be filled dynamically when opening modal */}
            </select>
        </div>
    `;

  const supplierFormHTML = `
        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Company Name</label>
            <input name="company_name" type="text" class="modal-input" placeholder="e.g. Global Tech Supplies" required>
        </div>

        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Contact Email</label>
            <input name="email" type="email" class="modal-input" placeholder="orders@company.com">
        </div>

        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Phone</label>
            <input name="phone" type="text" class="modal-input" placeholder="0712345678">
        </div>

        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Service Type</label>
            <select name="service_type" class="modal-input">
                <option value="Hardware">Hardware Supplier</option>
                <option value="Maintenance">Maintenance Contractor</option>
                <option value="Logistics">Logistics Partner</option>
                <option value="Stationery">Stationery</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Contract Status</label>
            <select name="contract_status" class="modal-input">
                <option value="Active">Active</option>
                <option value="Pending">Pending</option>
                <option value="Expired">Expired</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" style="color: var(--secondary);">Next Delivery Date</label>
            <input name="next_delivery_date" type="date" class="date-input"
                style="width: 100%; color: white; background: rgba(2,12,27,0.4);">
        </div>
    `;

  // ===== Helpers =====
  const openModal = (type) => {
    currentMode = type;

    if (type === "stock") {
      modalTitle.innerText = "Add Inventory Item";
      formContainer.innerHTML = stockFormHTML;

      // Fill supplier dropdown options
      const supplierSelect = formContainer.querySelector(
        'select[name="supplier_id"]',
      );
      if (supplierSelect) {
        const options = suppliersCache
          .map(
            (s) =>
              `<option value="${s.supplier_id}">${escapeHtml(s.company_name)}</option>`,
          )
          .join("");
        supplierSelect.insertAdjacentHTML("beforeend", options);
      }
    } else {
      modalTitle.innerText = "Register New Supplier";
      formContainer.innerHTML = supplierFormHTML;
    }

    modal.classList.add("active");
  };

  const closeModal = () => {
    modal.classList.remove("active");
    modalForm.reset();
  };

  function escapeHtml(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  async function api(action, payload = null) {
    const url = `${API}?action=${encodeURIComponent(action)}`;
    const res = await fetch(
      url,
      payload
        ? {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
          }
        : { method: "GET" },
    );

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.error || "Request failed");
    return data;
  }

  // Stock display logic
  function calcPercent(qty, threshold) {
    const base = Math.max(1, threshold * 5);
    const p = Math.round((qty / base) * 100);
    return Math.max(0, Math.min(100, p));
  }

  function statusOf(qty, threshold) {
    if (qty <= threshold) return { label: "CRITICAL", color: "#e74c3c" };
    if (qty <= threshold * 2) return { label: "Moderate", color: "#f1c40f" };
    return { label: "Safe", color: "#2ecc71" };
  }

  function formatDate(iso) {
    if (!iso) return "—";
    const [y, m, d] = String(iso).split("-");
    const months = [
      "Jan",
      "Feb",
      "Mar",
      "Apr",
      "May",
      "Jun",
      "Jul",
      "Aug",
      "Sep",
      "Oct",
      "Nov",
      "Dec",
    ];
    return `${months[(parseInt(m, 10) || 1) - 1] ?? m} ${d}`;
  }

  // ===== Render Inventory (replace your dummy blocks) =====
  function renderInventory(items) {
    // Remove old stock blocks
    inventoryWidget
      .querySelectorAll(".stock-level-container")
      .forEach((el) => el.remove());

    // Insert after the header row inside inventory widget
    const headerRow = inventoryWidget.querySelector(
      "div[style*='justify-content: space-between']",
    );
    const afterEl = headerRow || inventoryWidget.firstElementChild;

    items.forEach((it) => {
      const qty = parseInt(it.quantity, 10) || 0;
      const th = parseInt(it.alert_threshold, 10) || 5;
      const percent = calcPercent(qty, th);
      const st = statusOf(qty, th);

      const block = document.createElement("div");
      block.className = "stock-level-container";

      block.innerHTML = `
                <div class="level-label">
                    <span>${escapeHtml(it.item_name)}</span>
                    <span style="color:${st.color};">${percent}% (${st.label})</span>
                </div>
                <div class="level-bar-bg">
                    <div class="level-bar-fill"
                        style="width:${percent}%; background:${st.color}; box-shadow:0 0 10px ${st.color};">
                    </div>
                </div>

                <div style="display:flex; gap:10px; align-items:center; margin-top:10px; flex-wrap:wrap;">
                    <span style="font-size:0.8rem; color: var(--secondary);">
                        Qty: <b style="color:white;">${qty}</b>
                        | Threshold: ${th}
                        ${it.supplier_name ? `| Supplier: <b style="color:white;">${escapeHtml(it.supplier_name)}</b>` : ""}
                    </span>

                    <button class="btn btn-primary js-addstock"
                        data-id="${it.item_id}"
                        style="margin-left:auto; font-size:0.8rem; padding:5px 15px;">
                        Add Stock
                    </button>

                    <button class="btn btn-primary js-restock"
                        data-id="${it.item_id}"
                        style="font-size:0.8rem; padding:5px 15px; ${qty <= th ? "" : "opacity:0.5; pointer-events:none;"}">
                        Order Restock
                    </button>
                </div>
            `;

      afterEl.insertAdjacentElement("afterend", block);
    });
  }

  // ===== Render Suppliers (replace your dummy cards) =====
  function renderSuppliers(list) {
    supplierListEl.innerHTML = "";

    list.forEach((sp) => {
      const initials = (sp.company_name || "SUP")
        .split(" ")
        .slice(0, 2)
        .map((x) => x[0] || "")
        .join("")
        .toUpperCase();

      const card = document.createElement("div");
      card.className = "supplier-card";

      card.innerHTML = `
                <div class="supplier-logo">${escapeHtml(initials)}</div>
                <div style="flex: 1;">
                    <h4 style="color: white;">${escapeHtml(sp.company_name)}</h4>
                    <p style="font-size: 0.8rem; color: var(--secondary);">
                        Next Delivery: ${formatDate(sp.next_delivery_date)}
                    </p>
                </div>
                <span class="status-badge ${sp.contract_status === "Active" ? "status-active" : "status-pending"}">
                    ${escapeHtml(sp.contract_status)}
                </span>
                <button class="btn-icon btn-view js-call" data-phone="${escapeHtml(sp.phone || "")}">
                    <i class="fa-solid fa-phone"></i>
                </button>
            `;

      supplierListEl.appendChild(card);
    });
  }

  // ===== Load data =====
  async function loadAll() {
    const s = await api("list_suppliers");
    suppliersCache = s.suppliers || [];
    renderSuppliers(suppliersCache);

    const inv = await api("list_inventory");
    renderInventory(inv.items || []);
  }

  // ===== Events =====
  if (btnAddStock)
    btnAddStock.addEventListener("click", () => openModal("stock"));
  if (btnAddSupplier)
    btnAddSupplier.addEventListener("click", () => openModal("supplier"));

  if (btnClose) btnClose.addEventListener("click", closeModal);
  if (btnCancel) btnCancel.addEventListener("click", closeModal);

  window.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });

  // Submit modal form -> backend
  modalForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    try {
      const fd = new FormData(modalForm);
      const data = Object.fromEntries(fd.entries());

      if (currentMode === "stock") {
        // convert types
        data.quantity = parseInt(data.quantity || "0", 10);
        data.alert_threshold = parseInt(data.alert_threshold || "5", 10);
        data.unit_price = parseFloat(data.unit_price || "0");
        if (data.supplier_id === "") delete data.supplier_id;

        await api("add_item", data);
        closeModal();
        await loadAll();
      } else {
        // supplier
        if (data.next_delivery_date === "") delete data.next_delivery_date;
        await api("add_supplier", data);
        closeModal();
        await loadAll();
      }
    } catch (err) {
      alert(err.message);
    }
  });

  // Buttons created dynamically (Add Stock / Restock / Call)
  document.addEventListener("click", async (e) => {
    const add = e.target.closest(".js-addstock");
    const restock = e.target.closest(".js-restock");
    const call = e.target.closest(".js-call");

    try {
      if (add) {
        const id = parseInt(add.dataset.id, 10);
        const qty = parseInt(prompt("Enter quantity to add:", "1") || "0", 10);
        if (!qty || qty <= 0) return;

        const r = await api("add_stock", { item_id: id, add_qty: qty });
        console.log("ADD STOCK RESULT:", r);
        await loadAll();
      }

      if (restock) {
        const id = parseInt(restock.dataset.id, 10);
        const qty = parseInt(
          prompt("Restock quantity (creates approval request):", "10") || "0",
          10,
        );
        if (!qty || qty <= 0) return;

        await api("request_restock", { item_id: id, qty });
        alert("Restock request sent for approval!");
      }

      if (call) {
        const phone = (call.dataset.phone || "").trim();
        if (!phone) return alert("No phone number found for this supplier.");
        window.location.href = `tel:${phone}`;
      }
    } catch (err) {
      alert(err.message);
    }
  });

  // ===== Init =====
  loadAll().catch((err) => {
    console.error(err);
    alert("Backend load failed. Check API path and PHP errors.");
  });
});
