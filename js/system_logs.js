// js/system_logs.js
(() => {
  const body = document.getElementById("logTableBody");
  const dateFrom = document.getElementById("dateFrom");
  const dateTo = document.getElementById("dateTo");
  const levelFilter = document.getElementById("levelFilter");
  const logSearch = document.getElementById("logSearch");
  const btnPDF = document.getElementById("btnPDF");

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

    return `<span class="${cls}" style="padding:4px 10px;border-radius:999px;font-size:0.8rem;">${esc(v)}</span>`;
  }

  function formatTs(ts) {
    if (!ts) return "-";
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

    if (action === "list") params.set("limit", "200");
    return params;
  }

  async function loadLogs() {
    if (!body) return;

    body.innerHTML = `<tr><td colspan="6" style="color:#ccc;">Loading logs...</td></tr>`;

    const params = buildParams("list");

    try {
      // ✅ API is SAME FILE (html/system_logs.php)
      const res = await fetch(`system_logs.php?${params.toString()}`, {
        credentials: "include",
        headers: { Accept: "application/json" },
      });

      const text = await res.text();

      let data;
      try {
        data = JSON.parse(text);
      } catch {
        body.innerHTML = `<tr><td colspan="6" style="color:#ffb4b4;">
          API returned non-JSON (redirect / PHP error).<br>
          <small>Open DevTools → Network → Response to see what came back.</small>
        </td></tr>`;
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

  function download(url) {
    const a = document.createElement("a");
    a.href = url;
    a.download = "";
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  // Events
  dateFrom?.addEventListener("change", loadLogs);
  dateTo?.addEventListener("change", loadLogs);
  levelFilter?.addEventListener("change", loadLogs);
  logSearch?.addEventListener("input", debouncedLoad);

  // ✅ PDF download without leaving page
  btnPDF?.addEventListener("click", () => {
    const p = new URLSearchParams();
    if (dateFrom?.value) p.set("from", dateFrom.value);
    if (dateTo?.value) p.set("to", dateTo.value);
    if (levelFilter?.value && levelFilter.value !== "ALL")
      p.set("level", levelFilter.value);
    if (logSearch?.value && logSearch.value.trim())
      p.set("q", logSearch.value.trim());
    download(`../php/generate_pdf.php?${p.toString()}`);
  });

  // First load
  loadLogs();
})();
