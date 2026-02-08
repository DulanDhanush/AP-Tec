// js/approvals.js
const grid = document.getElementById("approvalsGrid");

function escapeHtml(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function formatMoney(amount) {
  const n = Number(amount || 0);
  return (
    "Rs. " +
    n.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

function typeMeta(type) {
  if (type === "Purchase Order") {
    return {
      icon: "fa-cart-shopping",
      bg: "rgba(100, 255, 218, 0.1)",
      color: "var(--primary)",
      title: "Bulk Inventory Purchase",
      badge: "Pending",
      approveText: "Approve Order",
    };
  }
  if (type === "Contract Renewal") {
    return {
      icon: "fa-file-contract",
      bg: "rgba(241, 196, 15, 0.1)",
      color: "#f1c40f",
      title: "Supplier Contract Renewal",
      badge: "Action Required",
      approveText: "Sign & Approve",
    };
  }
  return {
    icon: "fa-file-signature",
    bg: "rgba(52, 152, 219, 0.12)",
    color: "#3498db",
    title: "Leave Request",
    badge: "Pending",
    approveText: "Approve",
  };
}

function renderCard(a) {
  const meta = typeMeta(a.type);
  const requester = escapeHtml(a.requester_name || "User #" + a.requester_id);
  const details = escapeHtml(a.details || "");

  let line2 = "";
  let line3 = "";

  if (a.type === "Purchase Order") {
    line2 = `Requested by: ${requester}`;
    line3 = `Total Value: <span style="color:#64ffda;">${formatMoney(a.amount)}</span>`;
  } else if (a.type === "Contract Renewal") {
    line2 = `Entity: ${details || requester}`;
    line3 = `Term/Details: ${details || "—"}`;
  } else {
    line2 = `Requested by: ${requester}`;
    line3 = `Reason: ${details || "—"}`;
  }

  return `
    <div class="wide-widget" style="height:auto;" data-id="${a.approval_id}">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div style="display:flex;gap:15px;">
          <div style="width:50px;height:50px;background:${meta.bg};border-radius:8px;display:flex;align-items:center;justify-content:center;color:${meta.color};font-size:1.5rem;">
            <i class="fa-solid ${meta.icon}"></i>
          </div>
          <div>
            <h3 style="color:white;font-size:1.1rem;">${escapeHtml(meta.title)}</h3>
            <p style="color:var(--secondary);font-size:0.9rem;">${line2}</p>
            <div style="margin-top:10px;color:var(--text-white);${a.type === "Purchase Order" ? "font-family:'Courier New';" : ""}">
              ${line3}
            </div>
          </div>
        </div>
        <span class="status-badge status-pending">${escapeHtml(meta.badge)}</span>
      </div>

      <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border-glass);display:flex;gap:10px;justify-content:flex-end;">
        <button class="btn btn-danger" data-action="reject">Reject</button>
        <button class="btn btn-primary" data-action="approve">${escapeHtml(meta.approveText)}</button>
      </div>
    </div>
  `;
}

async function loadApprovals() {
  grid.innerHTML = `<div class="wide-widget" style="height:auto;">Loading approvals...</div>`;

  const res = await fetch("../php/approvals_api.php?action=list", {
    cache: "no-store",
  });
  const data = await res.json();

  if (!data.ok) {
    grid.innerHTML = `<div class="wide-widget" style="height:auto;">${escapeHtml(data.error || "Failed to load")}</div>`;
    return;
  }

  if (!data.items || data.items.length === 0) {
    grid.innerHTML = `<div class="wide-widget" style="height:auto;">✅ No pending approvals.</div>`;
    return;
  }

  grid.innerHTML = data.items.map(renderCard).join("");
}

async function decide(id, decision) {
  const card = document.querySelector(`.wide-widget[data-id="${id}"]`);
  if (!card) return;

  const res = await fetch("../php/approvals_api.php?action=update", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ approval_id: id, decision }),
  });

  const data = await res.json();
  if (!data.ok) {
    alert(data.error || "Failed");
    return;
  }

  card.remove();
  if (!grid.querySelector(".wide-widget")) {
    grid.innerHTML = `<div class="wide-widget" style="height:auto;">✅ No pending approvals.</div>`;
  }
}

document.addEventListener("click", (e) => {
  const btn = e.target.closest("button");
  if (!btn) return;

  const card = btn.closest(".wide-widget");
  if (!card) return;

  const id = Number(card.getAttribute("data-id") || 0);
  const action = btn.getAttribute("data-action");

  if (action === "approve") {
    if (confirm("Approve this request?")) decide(id, "Approved");
  } else if (action === "reject") {
    if (confirm("Reject this request?")) decide(id, "Rejected");
  }
});

loadApprovals();
