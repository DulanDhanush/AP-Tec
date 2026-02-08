// js/system_logs.js

const api = "../php/logs_api.php";
const exportUrl = "../php/logs_export.php";

const body = document.getElementById("logTableBody");
const dateFrom = document.getElementById("dateFrom");
const dateTo = document.getElementById("dateTo");
const levelFilter = document.getElementById("levelFilter");
const logSearch = document.getElementById("logSearch");
const btnExport = document.getElementById("btnExportCSV");

function badge(level) {
  const lv = (level || "").toUpperCase();
  if (lv === "ERROR")
    return `<span class="badge-log log-error">CRITICAL</span>`;
  if (lv === "WARNING")
    return `<span class="badge-log log-warn">WARNING</span>`;
  return `<span class="badge-log log-info">INFO</span>`;
}

function escapeHtml(s) {
  return String(s ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function toggleDetails(btn) {
  const currentRow = btn.closest("tr");
  const detailsRow = currentRow.nextElementSibling;

  if (detailsRow && detailsRow.classList.contains("log-details-row")) {
    if (detailsRow.style.display === "table-row") {
      detailsRow.style.display = "none";
      btn.innerHTML = '<i class="fa-solid fa-angle-down"></i>';
      currentRow.style.background = "transparent";
    } else {
      detailsRow.style.display = "table-row";
      btn.innerHTML = '<i class="fa-solid fa-angle-up"></i>';
      currentRow.style.background = "rgba(100, 255, 218, 0.05)";
    }
  }
}
window.toggleDetails = toggleDetails;

function buildDetails(log) {
  const lines = [];
  lines.push(`LOG_ID: ${escapeHtml(log.log_id)}`);
  lines.push(`LEVEL: ${escapeHtml(log.level)}`);
  lines.push(`MODULE: ${escapeHtml(log.module || "-")}`);
  lines.push(`USER: ${escapeHtml(log.username || "SYSTEM")}`);
  lines.push(`IP: ${escapeHtml(log.ip_address || "")}`);

  // if `details` exists in DB, show it
  if (log.details) {
    lines.push("");
    lines.push(String(log.details));
  }
  return lines.join("<br>");
}

function render(rows) {
  body.innerHTML = "";

  if (!rows || rows.length === 0) {
    body.innerHTML = `<tr><td colspan="6" style="color:#ccc;">No logs found.</td></tr>`;
    return;
  }

  rows.forEach((log) => {
    const time = escapeHtml(log.created_at);
    const level = badge(log.level);
    const module = escapeHtml(log.module || "-");
    const msg = escapeHtml(log.message || "");
    const username = escapeHtml(log.username || "SYSTEM");
    const ip = escapeHtml(log.ip_address || "");

    const mainRow = document.createElement("tr");
    if ((log.level || "").toUpperCase() === "ERROR") {
      mainRow.style.borderLeft = "3px solid #e74c3c";
    }

    mainRow.innerHTML = `
      <td class="font-mono">${time}</td>
      <td>${level}</td>
      <td>${module}</td>
      <td>${msg}</td>
      <td>
        <div style="display:flex; flex-direction:column;">
          <span>${username}</span>
          <span class="font-mono" style="font-size:0.75rem; color: var(--secondary);">${ip}</span>
        </div>
      </td>
      <td><button class="btn-icon btn-view" onclick="toggleDetails(this)"><i class="fa-solid fa-angle-down"></i></button></td>
    `;

    const detailsRow = document.createElement("tr");
    detailsRow.className = "log-details-row";
    detailsRow.style.display = "none";
    detailsRow.innerHTML = `
      <td colspan="6">
        <div class="log-details-content">${buildDetails(log)}</div>
      </td>
    `;

    body.appendChild(mainRow);
    body.appendChild(detailsRow);
  });
}

async function loadLogs() {
  const params = new URLSearchParams();
  if (dateFrom.value) params.set("from", dateFrom.value);
  if (dateTo.value) params.set("to", dateTo.value);
  if (levelFilter.value) params.set("level", levelFilter.value);
  if (logSearch.value.trim()) params.set("q", logSearch.value.trim());
  params.set("_", Date.now());

  const url = `${api}?${params.toString()}`;

  try {
    const res = await fetch(url, { credentials: "same-origin" });
    const json = await res.json();
    if (!json.ok) {
      body.innerHTML = `<tr><td colspan="6" style="color:#ccc;">${escapeHtml(json.error || "Failed to load logs")}</td></tr>`;
      return;
    }
    render(json.data);
  } catch (e) {
    body.innerHTML = `<tr><td colspan="6" style="color:#ccc;">API error loading logs.</td></tr>`;
  }
}

// debounce search
let t = null;
logSearch.addEventListener("input", () => {
  clearTimeout(t);
  t = setTimeout(loadLogs, 250);
});

[levelFilter, dateFrom, dateTo].forEach((el) =>
  el.addEventListener("change", loadLogs),
);

btnExport.addEventListener("click", () => {
  const params = new URLSearchParams();
  if (dateFrom.value) params.set("from", dateFrom.value);
  if (dateTo.value) params.set("to", dateTo.value);
  if (levelFilter.value) params.set("level", levelFilter.value);
  if (logSearch.value.trim()) params.set("q", logSearch.value.trim());

  window.location.href = `${exportUrl}?${params.toString()}`;
});

// initial
loadLogs();
