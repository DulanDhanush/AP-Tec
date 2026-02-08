/* Customer Support Logic (REAL BACKEND) */

const API = "../php/customer_support_api.php";

let selectedTicketId = null;
let lastPollTime = "1970-01-01 00:00:00";

function escapeHtml(s) {
  return String(s)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function fmtDate(iso) {
  // iso: "2026-02-07 12:10:00"
  const d = new Date(String(iso).replace(" ", "T"));
  return d.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "2-digit",
  });
}

function badge(status) {
  // match your CSS badge classes
  let cls = "status-pending";
  if (status === "Resolved") cls = "status-active";
  if (status === "Closed" || status === "Cancelled") cls = "status-closed";
  if (status === "Open") cls = "status-open";
  if (status === "In Progress") cls = "status-pending";
  return `<span class="status-badge ${cls}">${escapeHtml(status)}</span>`;
}

async function apiGet(action, params = {}) {
  const url = new URL(API, window.location.href);
  url.searchParams.set("action", action);
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
  const res = await fetch(url.toString(), { credentials: "include" });
  return res.json();
}

async function apiPost(action, data) {
  const url = new URL(API, window.location.href);
  url.searchParams.set("action", action);
  const res = await fetch(url.toString(), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify(data || {}),
  });
  return res.json();
}

/* ---------- FAQ Accordion ---------- */
function initAccordion() {
  const accHeaders = document.querySelectorAll(".accordion-header");
  accHeaders.forEach((header) => {
    header.addEventListener("click", () => {
      const item = header.parentElement;
      const body = item.querySelector(".accordion-body");

      item.classList.toggle("active");
      if (item.classList.contains("active"))
        body.style.maxHeight = body.scrollHeight + "px";
      else body.style.maxHeight = null;
    });
  });
}

/* ---------- Stats ---------- */
async function loadStats() {
  const r = await apiGet("stats");
  if (!r.ok) return;

  // first stat: Open tickets
  const statH3 = document.querySelectorAll(".stat-card .stat-info h3");
  if (statH3.length >= 1) statH3[0].textContent = String(r.open_tickets ?? 0);

  // second stat: avg response
  if (statH3.length >= 2) {
    if (r.avg_response_minutes == null) statH3[1].textContent = "—";
    else {
      const mins = Number(r.avg_response_minutes);
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      statH3[1].textContent = h > 0 ? `${h}h ${m}m` : `${m}m`;
    }
  }
}

/* ---------- Ticket table ---------- */
function getTicketTbody() {
  return document.querySelector(".wide-widget table tbody");
}

async function loadTickets() {
  const r = await apiGet("list");
  if (!r.ok) return;

  const tbody = getTicketTbody();
  if (!tbody) return;

  tbody.innerHTML = "";

  if (!r.tickets || r.tickets.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" style="color: var(--secondary); padding: 18px;">
          No tickets yet. Click <b>Open Ticket</b> to create one.
        </td>
      </tr>
    `;
    return;
  }

  for (const t of r.tickets) {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td class="font-mono">#${escapeHtml(t.ticket_reference || "TCK-" + t.ticket_id)}</td>
      <td>${escapeHtml(t.subject)}</td>
      <td>${fmtDate(t.created_at)}</td>
      <td>${badge(t.status)}</td>
      <td style="position: relative; overflow: visible">
        <div class="action-container">
          <button class="btn-icon btn-trigger-menu">
            <i class="fa-solid fa-ellipsis-vertical"></i>
          </button>
          <div class="action-menu">
            <button class="action-item" data-act="view" data-id="${t.ticket_id}">
              <i class="fa-solid fa-eye"></i> View Details
            </button>
            <button class="action-item" data-act="edit" data-id="${t.ticket_id}">
              <i class="fa-solid fa-pen"></i> Edit Info
            </button>
            <div style="height:1px;background:rgba(255,255,255,0.1);margin:5px 0;"></div>
            <button class="action-item" data-act="cancel" data-id="${t.ticket_id}" style="color:#e74c3c">
              <i class="fa-solid fa-trash"></i> Cancel
            </button>
          </div>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  }

  // Hook row actions
  tbody.querySelectorAll("[data-act]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = Number(btn.dataset.id);
      const act = btn.dataset.act;

      if (act === "view") await openViewTicket(id);
      if (act === "edit") await openEditTicket(id);
      if (act === "cancel") await openCancelTicket(id);
    });
  });
}

/* ---------- Create Ticket Modal submit ---------- */
function hookCreateTicketForm() {
  const modal = document.getElementById("createTicketModal");
  if (!modal) return;

  const form = modal.querySelector("form");
  if (!form) return;

  const subjectInput = form.querySelector('input[type="text"].modal-input');
  const descInput = form.querySelector("textarea.modal-input");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const subject = subjectInput.value.trim();
    const description = descInput.value.trim();

    const r = await apiPost("create", {
      subject,
      description,
      category: "Other",
      priority: "Normal",
    });

    if (!r.ok) {
      alert(r.error || "Failed to create ticket");
      return;
    }

    // reset + close
    subjectInput.value = "";
    descInput.value = "";
    closeModal("createTicketModal");

    await loadStats();
    await loadTickets();

    // open view directly
    await openViewTicket(r.ticket_id);
  });
}

/* ---------- View Ticket Modal ---------- */
async function openViewTicket(ticketId) {
  const r = await apiGet("view", { ticket_id: ticketId });
  if (!r.ok) {
    alert(r.error || "Failed to load ticket");
    return;
  }

  selectedTicketId = r.ticket.ticket_id;

  const modal = document.getElementById("viewTicketModal");
  if (!modal) return;

  // Header
  modal.querySelector(".modal-header h2").textContent =
    `Ticket Details (#${r.ticket.ticket_reference || r.ticket.ticket_id})`;

  const badgeEl = modal.querySelector(".modal-header .status-badge");
  badgeEl.textContent = r.ticket.status;

  // Details box (your existing layout)
  const box = modal.querySelector(
    'div[style*="background: rgba(0, 0, 0, 0.2)"]',
  );
  box.querySelector("h4").textContent = r.ticket.subject;
  box.querySelector("p").textContent =
    `Reported: ${fmtDate(r.ticket.created_at)}`;
  box.querySelectorAll("p")[1].textContent = r.ticket.description || "";

  // Rebuild replies area under "Technician Reply:"
  const replyHeader = Array.from(modal.querySelectorAll("h4")).find((h) =>
    (h.textContent || "").toLowerCase().includes("technician reply"),
  );
  if (replyHeader) {
    // remove old reply blocks after this header (keep header)
    let n = replyHeader.nextElementSibling;
    while (n) {
      const toRemove = n;
      n = n.nextElementSibling;
      toRemove.remove();
    }

    // add all messages (customer + agent)
    for (const m of r.messages || []) {
      const wrap = document.createElement("div");
      wrap.style.display = "flex";
      wrap.style.gap = "10px";
      wrap.style.marginTop = "10px";

      const av = document.createElement("div");
      av.className = "user-avatar";
      av.style.width = "30px";
      av.style.height = "30px";
      av.textContent = m.sender_role === "Agent" ? "AG" : "ME";

      const p = document.createElement("p");
      p.style.color = "white";
      p.style.fontSize = "0.9rem";
      p.textContent = m.message;

      wrap.appendChild(av);
      wrap.appendChild(p);

      modal.appendChild(wrap);
      lastPollTime = m.created_at; // last timestamp for polling
    }
  }

  openModal("viewTicketModal");
}

/* ---------- Edit Ticket Modal (adds a note/message) ---------- */
async function openEditTicket(ticketId) {
  selectedTicketId = ticketId;

  const modal = document.getElementById("editTicketModal");
  if (!modal) return;

  // Set the small ID text in your modal
  const idText = modal.querySelector(".modal-header p");
  if (idText) {
    // load reference to show
    const r = await apiGet("view", { ticket_id: ticketId });
    if (r.ok)
      idText.textContent = `ID: #${r.ticket.ticket_reference || r.ticket.ticket_id}`;
    else idText.textContent = `ID: #${ticketId}`;
  }

  const textarea = modal.querySelector("textarea.modal-input");
  textarea.value = "";

  // Your Save button currently has inline onclick alert. Replace it with DB save:
  const saveBtn = modal.querySelector(".btn.btn-primary");
  saveBtn.onclick = async () => {
    const note = textarea.value.trim();
    if (!note) {
      alert("Please type an update note.");
      return;
    }
    const r = await apiPost("update", { ticket_id: selectedTicketId, note });
    if (!r.ok) {
      alert(r.error || "Update failed");
      return;
    }

    closeModal("editTicketModal");
    await loadStats();
    await loadTickets();
    await openViewTicket(selectedTicketId);
  };

  openModal("editTicketModal");
}

/* ---------- Cancel Ticket (sets status Closed) ---------- */
async function openCancelTicket(ticketId) {
  selectedTicketId = ticketId;

  const modal = document.getElementById("deleteModal");
  if (!modal) return;

  const yesBtn = modal.querySelector(".btn.btn-danger");
  yesBtn.onclick = async () => {
    const r = await apiPost("cancel", { ticket_id: selectedTicketId });
    if (!r.ok) {
      alert(r.error || "Cancel failed");
      return;
    }
    closeModal("deleteModal");
    await loadStats();
    await loadTickets();
  };

  openModal("deleteModal");
}

/* ---------- Live chat integration (used by live_chat_popup.js) ---------- */
window.sendChatMessage = async function (message) {
  if (!selectedTicketId) {
    alert("Open a ticket (View Details) first, then chat.");
    return;
  }
  const r = await apiPost("send_message", {
    ticket_id: selectedTicketId,
    message,
  });
  if (!r.ok) alert(r.error || "Send failed");
};

async function pollMessages() {
  if (!selectedTicketId) return;
  const r = await apiGet("poll", {
    ticket_id: selectedTicketId,
    after: lastPollTime,
  });
  if (!r.ok || !r.messages || r.messages.length === 0) return;

  const chatBody = document.getElementById("popupChatBody");
  if (!chatBody) return;

  for (const m of r.messages) {
    const div = document.createElement("div");
    div.className =
      "message-bubble " + (m.sender_role === "Agent" ? "msg-in" : "msg-out");
    div.textContent = m.message;
    chatBody.appendChild(div);
    chatBody.scrollTop = chatBody.scrollHeight;

    lastPollTime = m.created_at;
  }
}

/* ---------- Init ---------- */
document.addEventListener("DOMContentLoaded", async () => {
  initAccordion();
  hookCreateTicketForm();

  await loadStats();
  await loadTickets();

  setInterval(pollMessages, 3000);
});
