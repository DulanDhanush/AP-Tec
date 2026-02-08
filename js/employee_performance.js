// js/employee_performance.js

async function api(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params });
  const res = await fetch(
    `../php/employee_performance_api.php?${qs.toString()}`,
    {
      credentials: "include",
    },
  );

  const text = await res.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    console.error("NOT JSON RESPONSE:", text);
    throw new Error("Server returned invalid JSON. Check console output.");
  }

  if (!res.ok || !data.ok) throw new Error(data.error || "API error");
  return data;
}

function setText(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

function starsHTML(rating) {
  if (rating === null || rating === undefined) {
    return `<span style="color: var(--text-dim);">—</span>`;
  }
  const r = Number(rating);
  return `<i class="fa-solid fa-star"></i> ${r.toFixed(1)}`;
}

function statusBadge(status) {
  if (status === "On Job")
    return `<span class="status-badge status-active">On Job</span>`;
  if (status === "Available")
    return `<span class="status-badge status-pending">Available</span>`;
  if (status === "On Leave")
    return `<span class="status-badge" style="background: rgba(231,76,60,0.2); color: #e74c3c;">On Leave</span>`;
  return `<span class="status-badge" style="background: rgba(149,165,166,0.2); color: #95a5a6;">${status}</span>`;
}

function renderSkills() {
  return `<span style="color: var(--text-dim);">—</span>`;
}

async function loadSummary() {
  const d = await api("summary");

  setText("topName", d.top.full_name || "—");
  setText("topTasks", String(d.top.completed_tasks || 0));
  setText("topInitials", d.top.avatar_initials || "U");

  const topAvatar = document.getElementById("topAvatar");
  if (topAvatar) {
    topAvatar.style.background = d.top.avatar_color || "#f1c40f";
    topAvatar.style.color = "#000";
  }

  const topRating = document.getElementById("topRating");
  if (topRating) topRating.innerHTML = starsHTML(d.top.avg_rating);

  setText("onTimePct", `${d.on_time_pct}%`);
  setText("techActive", `${d.active_techs}/${d.total_techs}`);
}

async function loadTechnicians() {
  const d = await api("technicians");
  const tbody = document.getElementById("techTbody");
  if (!tbody) return;

  tbody.innerHTML = "";

  d.technicians.forEach((t) => {
    const tr = document.createElement("tr");

    tr.innerHTML = `
      <td>
        <div style="display:flex; align-items:center; gap:10px;">
          <div class="user-avatar" style="width:30px; height:30px; font-size:0.8rem; background:${t.avatar_color};">
            ${t.avatar_initials}
          </div>
          <span style="color:white; font-weight:600;">${t.full_name}</span>
        </div>
      </td>
      <td>${statusBadge(t.status)}</td>
      <td>${renderSkills()}</td>
      <td style="color: var(--primary); font-weight: bold;">${t.tasks_month}</td>
      <td><div class="star-rating">${starsHTML(t.avg_rating)}</div></td>
      <td>
        <button class="btn-icon btn-view" data-tech-id="${t.user_id}">
          <i class="fa-solid fa-eye"></i>
        </button>
      </td>
    `;

    tbody.appendChild(tr);
  });

  tbody.querySelectorAll("button[data-tech-id]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const id = btn.getAttribute("data-tech-id");
      try {
        const detail = await api("technician_detail", { id });
        const tech = detail.technician;

        const lines = [
          `Technician: ${tech.full_name}`,
          `Username: ${tech.username}`,
          `Email: ${tech.email}`,
          `Phone: ${tech.phone || "-"}`,
          `Status: ${tech.status}`,
          "",
          "Recent Tasks:",
          ...detail.recent_tasks.map(
            (x) =>
              `- [${x.status}] ${x.title} (Rating: ${x.customer_rating ?? "-"})`,
          ),
        ];

        alert(lines.join("\n"));
      } catch (e) {
        alert(e.message);
      }
    });
  });
}

async function loadFeedback() {
  const d = await api("feedback", { limit: 6 });
  const box = document.getElementById("feedbackGrid");
  if (!box) return;

  box.innerHTML = "";

  if (!d.feedback || d.feedback.length === 0) {
    box.innerHTML = `
      <div class="notif-item" style="grid-column: span 2;">
        <div class="notif-icon" style="background: rgba(149,165,166,0.15); color:#95a5a6;">
          <i class="fa-solid fa-message"></i>
        </div>
        <div class="notif-content">
          <h4>No feedback yet</h4>
          <p style="color: var(--text-dim);">When customers rate tasks, they will appear here.</p>
        </div>
      </div>
    `;
    return;
  }

  d.feedback.forEach((f) => {
    const rating = Number(f.customer_rating || 0).toFixed(1);

    const div = document.createElement("div");
    div.className = "notif-item";

    div.innerHTML = `
      <div class="notif-icon icon-success">
        <i class="fa-solid fa-thumbs-up"></i>
      </div>
      <div class="notif-content">
        <h4 style="display:flex; justify-content:space-between;">
          ${f.customer_name || "Customer"}
          <span class="star-rating"><i class="fa-solid fa-star"></i> ${rating}</span>
        </h4>
        <p>"${String(f.customer_feedback || "").replaceAll('"', "'")}"</p>
        <p style="color: var(--text-dim); font-size: 0.75rem; margin-top: 5px;">
          Technician: ${f.technician_name || "—"}
        </p>
      </div>
    `;

    box.appendChild(div);
  });
}

function bindExport() {
  const btn = document.getElementById("btnExport");
  if (!btn) return;

  btn.addEventListener("click", () => {
    window.location.href = "../php/employee_performance_report.php";
  });
}

document.addEventListener("DOMContentLoaded", async () => {
  bindExport();
  try {
    await loadSummary();
    await loadTechnicians();
    await loadFeedback();
  } catch (e) {
    console.error(e);
    alert("Failed to load performance data: " + e.message);
  }
});
