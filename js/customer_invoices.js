// js/customer_invoices.js

const API = "/DULA_FNL/php/customer_invoice_api.php";

function money(n) {
  return "" + Number(n || 0).toFixed(2);
}

function formatDate(yyyy_mm_dd) {
  if (!yyyy_mm_dd) return "—";
  const d = new Date(yyyy_mm_dd + "T00:00:00");
  if (isNaN(d.getTime())) return yyyy_mm_dd;
  return d.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "2-digit",
  });
}

function statusBadge(status) {
  const s = (status || "").toLowerCase();
  if (s === "paid") return `<span class="status-badge status-paid">Paid</span>`;
  if (s === "overdue")
    return `<span class="status-badge status-unpaid">Overdue</span>`;
  return `<span class="status-badge status-unpaid">Unpaid</span>`;
}

async function fetchJson(url, options = {}) {
  const res = await fetch(url, { credentials: "include", ...options });

  const text = await res.text();

  // If PHP sends HTML error page / redirect page, this catches it.
  try {
    const data = JSON.parse(text);
    return { ok: true, status: res.status, data };
  } catch (e) {
    console.error("❌ API did not return JSON:", url);
    console.error("HTTP:", res.status);
    console.error("Response text:", text);
    return {
      ok: false,
      status: res.status,
      data: { ok: false, error: "API_NOT_JSON" },
      raw: text,
    };
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("invoiceTbody");

  const statOutstanding = document.getElementById("statOutstanding");
  const statPaidYtd = document.getElementById("statPaidYtd");
  const statLastPaid = document.getElementById("statLastPaid");
  const statOverdue = document.getElementById("statOverdue");

  const payModal = document.getElementById("payModal");
  const closeModalBtn = document.querySelector(".close-modal");

  const modalInvNum = document.getElementById("modalInvNum");
  const modalAmount = document.getElementById("modalAmount");
  const modalInvoiceId = document.getElementById("modalInvoiceId");

  const paymentForm = document.getElementById("paymentForm");
  const cardholder = document.getElementById("cardholder");
  const cardNumber = document.getElementById("cardNumber");
  const cvc = document.getElementById("cvc");

  let currentFilter = "all";

  if (!tbody) {
    console.error("❌ Missing <tbody id='invoiceTbody'> in HTML.");
    return;
  }

  function openModal() {
    if (!payModal) return;
    payModal.classList.add("show");
    payModal.style.display = "flex";
  }

  function closeModal() {
    if (!payModal) return;
    payModal.classList.remove("show");
    payModal.style.display = "none";
  }

  closeModalBtn?.addEventListener("click", closeModal);
  payModal?.addEventListener("click", (e) => {
    if (e.target === payModal) closeModal();
  });

  function rowHtml(inv) {
    const canPay = inv.status === "Unpaid" || inv.status === "Overdue";
    const dueStyle =
      inv.status === "Overdue"
        ? `style="color: #e74c3c; font-weight: bold"`
        : "";

    return `
      <tr class="invoice-row" data-status="${inv.status}">
        <td class="font-mono">${inv.invoice_number}</td>
        <td>${formatDate(inv.issue_date)}</td>
        <td ${dueStyle}>${formatDate(inv.due_date)}</td>
        <td style="color: white; font-weight: 600">${money(inv.total_amount)}</td>
        <td>${statusBadge(inv.status)}</td>
        <td>
          <div class="action-buttons">
            ${
              canPay
                ? `<button class="btn btn-primary btn-pay-now"
                    data-id="${inv.invoice_id}"
                    data-num="${inv.invoice_number}"
                    data-amt="${inv.total_amount}"
                    style="padding: 5px 15px; font-size: 0.8rem">
                    Pay Now
                  </button>`
                : ``
            }
            <button class="btn-icon btn-view" data-download="${inv.invoice_id}" title="Download PDF">
              <i class="fa-solid fa-download"></i>
            </button>
          </div>
        </td>
      </tr>
    `;
  }

  async function loadStats() {
    const r = await fetchJson(`${API}?action=stats`);
    if (!r.ok || !r.data.ok) return;

    const s = r.data.stats;

    statOutstanding && (statOutstanding.textContent = money(s.outstanding));
    statPaidYtd && (statPaidYtd.textContent = money(s.paid_ytd));

    if (statLastPaid) {
      if (s.last_payment_date) {
        const dt = new Date(String(s.last_payment_date).replace(" ", "T"));
        statLastPaid.textContent = isNaN(dt.getTime())
          ? String(s.last_payment_date)
          : dt.toLocaleDateString(undefined, {
              year: "numeric",
              month: "short",
              day: "2-digit",
            });
      } else {
        statLastPaid.textContent = "—";
      }
    }

    statOverdue && (statOverdue.textContent = String(s.overdue_count));
  }

  async function loadInvoices(filter = "all") {
    currentFilter = filter;

    const r = await fetchJson(
      `${API}?action=list&filter=${encodeURIComponent(filter)}`,
    );

    if (!r.ok || !r.data.ok) {
      tbody.innerHTML = `<tr><td colspan="6">Failed to load invoices.</td></tr>`;
      return;
    }

    const invoices = r.data.invoices || [];
    if (!invoices.length) {
      tbody.innerHTML = `<tr><td colspan="6">No invoices found.</td></tr>`;
      return;
    }

    tbody.innerHTML = invoices.map(rowHtml).join("");

    // wire pay buttons
    tbody.querySelectorAll(".btn-pay-now").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-id");
        const num = btn.getAttribute("data-num");
        const amt = btn.getAttribute("data-amt");

        if (modalInvoiceId) modalInvoiceId.value = id || "";
        if (modalInvNum) modalInvNum.textContent = num || "INV";
        if (modalAmount) modalAmount.textContent = money(amt);

        openModal();
      });
    });

    // wire download buttons
    tbody.querySelectorAll("[data-download]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const invoiceId = btn.getAttribute("data-download");
        window.open(
          `../php/invoice_print.php?invoice_id=${encodeURIComponent(invoiceId)}`,
          "_blank",
        );
      });
    });
  }

  // tabs
  document.querySelectorAll(".tab-btn").forEach((tab) => {
    tab.addEventListener("click", () => {
      document
        .querySelectorAll(".tab-btn")
        .forEach((t) => t.classList.remove("active"));
      tab.classList.add("active");
      loadInvoices(tab.dataset.filter || "all");
    });
  });

  // payment submit
  paymentForm?.addEventListener("submit", async (e) => {
    e.preventDefault();

    const form = new FormData();
    form.append("invoice_id", modalInvoiceId?.value || "");
    form.append("cardholder", cardholder?.value || "");
    form.append("card_number", cardNumber?.value || "");
    form.append("cvc", cvc?.value || "");

    const r = await fetchJson(`${API}?action=pay`, {
      method: "POST",
      body: form,
    });

    if (!r.ok || !r.data.ok) {
      alert(r.data?.error || "Payment failed");
      return;
    }

    alert("Payment successful!");
    closeModal();
    await loadStats();
    await loadInvoices(currentFilter);
  });

  // init
  (async function init() {
    await loadStats();
    await loadInvoices("all");
  })();
});
