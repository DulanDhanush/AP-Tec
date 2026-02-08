// js/customer_dashboard.js
(() => {
  const elTitle = document.getElementById("activeRepairTitle");
  const elTicket = document.getElementById("activeRepairTicket");
  const elTech = document.getElementById("activeRepairTech");
  const elDue = document.getElementById("totalDueAmount");
  const elActiveOrders = document.getElementById("activeOrdersCount");
  const tbody = document.getElementById("recentActivityBody");

  const stepEls = [
    document.getElementById("step1"),
    document.getElementById("step2"),
    document.getElementById("step3"),
    document.getElementById("step4"),
  ];

  function money(v) {
    const n = Number(v || 0);
    // If you want NO $ sign, keep it like this.
    return n.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function fmtDate(dt) {
    if (!dt) return "";
    const d = new Date(String(dt).replace(" ", "T"));
    if (isNaN(d.getTime())) return String(dt);
    return d.toLocaleDateString(undefined, { month: "short", day: "2-digit" });
  }

  function statusBadgeClass(status) {
    const s = String(status || "").toLowerCase();
    if (s === "completed") return "status-active";
    if (s === "cancelled") return "status-danger";
    return "status-pending";
  }

  function updateSteps(step) {
    // step: 1..4
    stepEls.forEach((el, idx) => {
      if (!el) return;
      el.classList.remove("completed", "active");
      const n = idx + 1;
      if (n < step) el.classList.add("completed");
      if (n === step) el.classList.add("active");
    });
  }

  async function load() {
    const url = new URL(
      "../php/customer_dashboard_api.php",
      window.location.href,
    );
    url.searchParams.set("action", "summary");

    const res = await fetch(url.toString(), { credentials: "include" });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.message || "Request failed");

    // widgets
    if (elDue) elDue.textContent = money(data.widgets?.total_due);
    if (elActiveOrders)
      elActiveOrders.textContent = String(data.widgets?.active_orders ?? 0);

    // active repair
    if (data.active_repair) {
      if (elTitle)
        elTitle.textContent = `${data.active_repair.title}${data.active_repair.location ? " (" + data.active_repair.location + ")" : ""}`;
      if (elTicket)
        elTicket.textContent = `Ticket #${data.active_repair.task_reference}`;
      if (elTech)
        elTech.textContent = `Technician Assigned: ${data.active_repair.technician_name || "Not Assigned"}`;
      updateSteps(Number(data.active_repair.step || 1));
    } else {
      if (elTitle) elTitle.textContent = "No Active Repair";
      if (elTicket) elTicket.textContent = "No Ticket";
      if (elTech) elTech.textContent = "Technician Assigned: -";
      updateSteps(0);
    }

    // recent activity
    if (tbody) {
      tbody.innerHTML = "";
      (data.recent_activity || []).forEach((r) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td class="font-mono">${r.order_id}</td>
          <td>${r.service}</td>
          <td>${fmtDate(r.date)}</td>
          <td><span class="status-badge ${statusBadgeClass(r.status)}">${r.status}</span></td>
          <td><button class="btn-glass" style="padding: 5px 10px; font-size: 0.8rem;" disabled>Invoice</button></td>
        `;
        tbody.appendChild(tr);
      });

      if (!(data.recent_activity || []).length) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:var(--secondary); padding:12px;">No activity yet.</td></tr>`;
      }
    }
  }

  load().catch((e) => console.error(e));
})();
