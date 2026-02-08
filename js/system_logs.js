// js/system_logs.js
(() => {
  const body = document.getElementById("logTableBody");
  const dateFrom = document.getElementById("dateFrom");
  const dateTo = document.getElementById("dateTo");
  const levelFilter = document.getElementById("levelFilter");
  const logSearch = document.getElementById("logSearch");
  const btnExport = document.getElementById("btnExportCSV");

  let debounceTimer = null;

  function esc(s) {
    return String(s ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function levelBadge(level) {
    const v = String(level || "").toUpperCase();
    let cls = "badge";
    if (v === "INFO") cls += " badge-success";
    else if (v === "WARNING") cls += " badge-warning";
    else if (v === "ERROR") cls += " badge-danger";
    else cls += " badge-secondary";

    // If your CSS doesn't have these badge classes, it will still show plain text.
    return `<span class="${cls}" style="padding:4px 10px;border-radius:999px;font-size:0.8rem;">${esc(v)}</span>`;
  }

  function formatTs(ts) {
    // MySQL timestamp string -> nicer
    if (!ts) return "-";
    // keep it simple to avoid timezone confusion
    return esc(String(ts).replace("T", " ").replace(".000Z", ""));
  }

  function buildParams(action) {
    const params = new URLSearchParams();
    params.set("action", action);

    if (dateFrom?.value) params.set("from", dateFrom.value);
    if (dateTo?.value) params.set("to", dateTo.value);
    if (levelFilter?.value && levelFilter.value !== "ALL")
      params.set("level", levelFilter.value);
    if (logSearch?.value && logSearch.value.trim())
      params.set("q", logSearch.value.trim());

    // only for list
    if (action === "list") params.set("limit", "200");
    return params;
  }

  async function loadLogs() {
    if (!body) return;

    body.innerHTML = `<tr><td colspan="6" style="color:#ccc;">Loading logs...</td></tr>`;

    const params = buildParams("list");

    try {
      const res = await fetch(
        `../php/system_logs_api.php?${params.toString()}`,
        {
          credentials: "include", // ✅ safer for PHP sessions than same-origin
          headers: { Accept: "application/json" },
        },
      );

      // If PHP redirects to login, res might be HTML. This catches it cleanly.
      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        body.innerHTML = `<tr><td colspan="6" style="color:#ffb4b4;">API returned non-JSON (probably redirect / PHP error).</td></tr>`;
        return;
      }

      if (!res.ok || !data.ok) {
        body.innerHTML = `<tr><td colspan="6" style="color:#ffb4b4;">${esc(
          data.error || "API error loading logs.",
        )}</td></tr>`;
        return;
      }

      const rows = Array.isArray(data.rows) ? data.rows : [];
      if (rows.length === 0) {
        body.innerHTML = `<tr><td colspan="6" style="color:#ccc;">No logs found.</td></tr>`;
        return;
      }

      body.innerHTML = rows
        .map((r) => {
          const user =
            r.full_name ||
            r.username ||
            (r.user_id ? "User #" + r.user_id : "System");
          const ip = r.ip_address || "-";
          return `
            <tr>
              <td>${formatTs(r.created_at)}</td>
              <td>${levelBadge(r.level)}</td>
              <td>${esc(r.module || "-")}</td>
              <td>${esc(r.message || "-")}</td>
              <td>${esc(user)}<br><span style="color:var(--secondary);font-size:0.8rem;">${esc(ip)}</span></td>
              <td style="color:#aaa;">-</td>
            </tr>
          `;
        })
        .join("");
    } catch (err) {
      body.innerHTML = `<tr><td colspan="6" style="color:#ffb4b4;">API error loading logs.</td></tr>`;
      console.error(err);
    }
  }

  function debouncedLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadLogs, 250);
  }

  // Events
  dateFrom?.addEventListener("change", loadLogs);
  dateTo?.addEventListener("change", loadLogs);
  levelFilter?.addEventListener("change", loadLogs);
  logSearch?.addEventListener("input", debouncedLoad);

  // ✅ Export CSV (same behavior as uploaded file)
  btnExport?.addEventListener("click", () => {
    const params = buildParams("csv");
    window.location.href = `../php/system_logs_api.php?${params.toString()}`;
  });

  // First load
  loadLogs();
})();
