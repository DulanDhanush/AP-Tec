/* Customer Orders Logic (DB version - no UI change) */

document.addEventListener("DOMContentLoaded", () => {
  // 1) Filter Tabs (same behavior as your current file)
  const tabs = document.querySelectorAll(".tab-btn");
  const orderRows = document.querySelectorAll(".order-row");

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      tabs.forEach((t) => t.classList.remove("active"));
      tab.classList.add("active");

      const filter = tab.getAttribute("data-filter");

      orderRows.forEach((row) => {
        const status = (row.getAttribute("data-status") || "").toLowerCase();

        if (filter === "all") {
          row.style.display = "";
        } else if (filter === "active") {
          // show Processing/Shipped/Pending etc (hide delivered/cancelled)
          if (status === "delivered" || status === "cancelled")
            row.style.display = "none";
          else row.style.display = "";
        } else if (filter === "history") {
          if (status === "delivered" || status === "cancelled")
            row.style.display = "";
          else row.style.display = "none";
        }
      });
    });
  });

  // 2) Modal
  const modal = document.getElementById("orderModal");
  const btnClose = modal ? modal.querySelector(".close-modal") : null;

  const modalOrderId = document.getElementById("modalOrderId");
  const modalStatus = document.getElementById("modalStatus");
  const trackBar = document.getElementById("modalTrackBar");

  // Optional elements (if you added them in PHP version)
  const modalItems = document.getElementById("modalItems");
  const modalTotal = document.getElementById("modalTotal");
  const btnDownloadInvoice = document.getElementById("btnDownloadInvoice");

  function openModal() {
    if (!modal) return;
    modal.classList.add("active");
    modal.style.display = "flex"; // makes sure modal is visible even if CSS differs
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove("active");
    modal.style.display = "none";
  }

  if (btnClose) btnClose.addEventListener("click", closeModal);
  window.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });

  function badgeClass(status) {
    const s = String(status || "").toLowerCase();
    if (s === "processing") return "status-processing";
    if (s === "shipped") return "status-shipped";
    if (s === "delivered") return "status-delivered";
    if (s === "cancelled") return "status-inactive";
    return "status-pending";
  }

  function setTrack(trackingStep) {
    // 1..4 => 0/33/66/100
    const step = Math.max(1, Math.min(4, parseInt(trackingStep || 1, 10)));
    const widths = ["0%", "33%", "66%", "100%"];
    if (trackBar) trackBar.style.width = widths[step - 1];
  }

  function escapeHtml(str) {
    return String(str)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function renderItems(items) {
    if (!modalItems) return; // if your HTML still has static items, you can ignore this
    modalItems.innerHTML = "";

    if (!items || items.length === 0) {
      modalItems.innerHTML = `<div style="color: var(--secondary); padding: 10px;">No items found.</div>`;
      return;
    }

    items.forEach((it) => {
      const name = it.item_name || "Item";
      const cat = it.category || "Supplies";
      const qty = it.quantity || 1;
      const price = Number(it.price || 0).toFixed(2);

      const row = document.createElement("div");
      row.className = "order-item-row";
      row.innerHTML = `
        <div style="display:flex; gap:15px; align-items:center">
          <div class="item-thumb"><i class="fa-solid fa-box"></i></div>
          <div>
            <div style="color:white; font-weight:600">${escapeHtml(name)}</div>
            <div style="color: var(--secondary); font-size:0.8rem">${escapeHtml(cat)}</div>
          </div>
        </div>
        <div style="text-align:right">
          <div style="color:white">x${escapeHtml(String(qty))}</div>
          <div style="color: var(--primary); font-size:0.9rem">$${escapeHtml(price)}</div>
        </div>
      `;
      modalItems.appendChild(row);
    });
  }

  async function loadOrder(orderId) {
    const res = await fetch(
      `../php/customer_orders_api.php?action=detail&order_id=${encodeURIComponent(orderId)}`,
      { credentials: "same-origin" },
    );
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || "Failed to load order");
    return data;
  }

  // 3) View order buttons (now uses DB)
  document.querySelectorAll(".btn-view-order").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const orderId = btn.dataset.orderId;
      if (!orderId) {
        console.error("Missing data-order-id on .btn-view-order");
        return;
      }

      openModal();

      try {
        // optional loading state
        if (modalItems)
          modalItems.innerHTML = `<div style="color: var(--secondary); padding: 10px;">Loading...</div>`;
        if (btnDownloadInvoice) btnDownloadInvoice.disabled = true;

        const data = await loadOrder(orderId);
        const o = data.order;

        if (modalOrderId)
          modalOrderId.innerText =
            "#" + (o.order_reference || "ORD-" + o.order_id);

        if (modalStatus) {
          modalStatus.innerText = o.status;
          modalStatus.className = "status-badge " + badgeClass(o.status);
        }

        setTrack(o.tracking_step);

        if (modalTotal)
          modalTotal.innerText = "$" + Number(o.total_amount || 0).toFixed(2);
        renderItems(data.items);

        if (btnDownloadInvoice) {
          btnDownloadInvoice.disabled = false; // ALWAYS clickable

          btnDownloadInvoice.onclick = async () => {
            try {
              // if invoice already exists -> download PDF
              if (data.invoice_id) {
                window.location.href = `../php/invoice_pdf.php?invoice_id=${data.invoice_id}`;
                return;
              }

              // else create invoice, then download
              const form = new FormData();
              form.append("order_id", o.order_id);

              const res2 = await fetch(
                `../php/customer_invoice_api.php?action=ensure_invoice`,
                {
                  method: "POST",
                  body: form,
                  credentials: "same-origin",
                },
              );

              const d2 = await res2.json();
              if (!d2.ok)
                throw new Error(d2.error || "Failed to create invoice");

              window.location.href = `../php/invoice_pdf.php?invoice_id=${d2.invoice_id}`;
            } catch (err) {
              alert("Invoice error: " + err.message);
            }
          };
        }
      } catch (err) {
        console.error(err);
        if (modalItems) {
          modalItems.innerHTML = `<div style="color:#ff7675; padding:10px;">${escapeHtml(err.message)}</div>`;
        }
      }
    });
  });

  // 4) Invoice icon buttons (optional)
  document.querySelectorAll(".btn-invoice").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const orderId = btn.dataset.orderId;
      if (!orderId) return;

      try {
        const data = await loadOrder(orderId);
        if (data.invoice_id) {
          window.location.href = `customer_invoices.html?invoice_id=${data.invoice_id}`;
        } else {
          alert("No invoice available yet for this order.");
        }
      } catch (e) {
        alert("Error: " + e.message);
      }
    });
  });

  // 5) Reorder Logic (keep your current placeholder)
  document.querySelectorAll(".btn-reorder").forEach((btn) => {
    btn.addEventListener("click", () => {
      alert("Items added to cart! Proceeding to checkout...");
    });
  });
});
