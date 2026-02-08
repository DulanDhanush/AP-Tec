// ../js/notifications.js
(() => {
  const notifList = document.getElementById("notifList");
  const notifCount = document.getElementById("notifCount");
  const btnClear = document.getElementById("btnClear");

  // Settings elements (you already have them in HTML)
  const set_low_inventory = document.getElementById("set_low_inventory");
  const set_server_downtime = document.getElementById("set_server_downtime");
  const set_daily_summary = document.getElementById("set_daily_summary");
  const set_new_user_signups = document.getElementById("set_new_user_signups");
  const btnSaveSettings = document.getElementById("btnSaveSettings");

  function esc(s) {
    return String(s ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function formatTs(ts) {
    // Your API gives "YYYY-MM-DD HH:MM:SS"
    return ts ? esc(ts) : "-";
  }

  function typeBadge(type) {
    // Keep it simple; uses inline styling so no CSS change required
    const t = String(type || "INFO");
    let bg = "rgba(255,255,255,0.08)";
    let fg = "var(--secondary)";

    const up = t.toUpperCase();
    if (up.includes("ALERT") || up.includes("ERROR") || up.includes("CRIT")) {
      bg = "rgba(231, 76, 60, 0.18)";
      fg = "#ffb4b4";
    } else if (up.includes("WARN")) {
      bg = "rgba(241, 196, 15, 0.18)";
      fg = "#ffe7a3";
    } else if (up.includes("SYSTEM")) {
      bg = "rgba(46, 204, 113, 0.14)";
      fg = "#bff4d2";
    }

    return `<span style="padding:4px 10px;border-radius:999px;background:${bg};color:${fg};font-size:0.75rem;">${esc(t)}</span>`;
  }

  async function apiGet(url) {
    const res = await fetch(url, { credentials: "same-origin" });
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch {
      throw new Error("API returned non-JSON (redirect/PHP error).");
    }
    if (!res.ok || !data.ok) throw new Error(data.error || "API error");
    return data;
  }

  async function apiPost(url, payload) {
    const res = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload || {}),
    });
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch {
      throw new Error("API returned non-JSON (redirect/PHP error).");
    }
    if (!res.ok || !data.ok) throw new Error(data.error || "API error");
    return data;
  }

  function render(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
      notifList.innerHTML = `<div style="color: var(--secondary); padding: 10px;">No notifications.</div>`;
      return;
    }

    notifList.innerHTML = rows
      .map((r) => {
        const unread = String(r.is_read) === "0" || r.is_read === 0;
        return `
        <div class="notification-item"
             data-id="${esc(r.notif_id)}"
             style="
               padding: 14px;
               border: 1px solid var(--border-glass);
               border-radius: 12px;
               margin-bottom: 12px;
               background: ${unread ? "rgba(0, 255, 200, 0.06)" : "rgba(2, 12, 27, 0.25)"};
               cursor: pointer;
             "
             title="Click to mark as read"
        >
          <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
            <div style="display:flex; flex-direction:column; gap:6px;">
              <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                ${typeBadge(r.type)}
                <span style="color: white; font-weight: 600;">${esc(r.title)}</span>
                ${unread ? `<span style="color:#7fffd4;font-size:0.75rem;">• Unread</span>` : ""}
              </div>
              <div style="color: var(--secondary); font-size: 0.9rem; line-height: 1.4;">
                ${esc(r.message)}
              </div>
            </div>
            <div style="color: var(--secondary); font-size: 0.8rem; white-space:nowrap;">
              ${formatTs(r.created_at)}
            </div>
          </div>
        </div>
      `;
      })
      .join("");

    // click to mark read
    notifList.querySelectorAll(".notification-item").forEach((el) => {
      el.addEventListener("click", async () => {
        const id = Number(el.dataset.id || 0);
        if (!id) return;
        try {
          await apiPost(`../php/notifications_api.php?action=mark_read`, {
            notif_id: id,
          });
          // Reload list (keeps count accurate)
          await loadNotifications();
        } catch (e) {
          console.error(e);
          alert(e.message || "Failed to mark as read");
        }
      });
    });
  }

  async function loadNotifications() {
    try {
      notifList.innerHTML = `<div style="color: var(--secondary); padding: 10px;">Loading notifications...</div>`;
      const data = await apiGet(
        `../php/notifications_api.php?action=list&limit=100`,
      );
      render(data.rows);
      if (notifCount) notifCount.textContent = String(data.unread ?? 0);
    } catch (e) {
      console.error(e);
      notifList.innerHTML = `<div style="color: #ffb4b4; padding: 10px;">${esc(e.message || "Failed to load notifications")}</div>`;
    }
  }

  async function loadSettings() {
    // If settings section exists, load it too
    if (!set_low_inventory || !btnSaveSettings) return;

    try {
      const data = await apiGet(
        `../php/notifications_api.php?action=settings_get`,
      );
      const s = data.settings || {};
      if (set_low_inventory)
        set_low_inventory.checked = !!Number(s.low_inventory);
      if (set_server_downtime)
        set_server_downtime.checked = !!Number(s.server_downtime);
      if (set_daily_summary)
        set_daily_summary.checked = !!Number(s.daily_summary);
      if (set_new_user_signups)
        set_new_user_signups.checked = !!Number(s.new_user_signups);
    } catch (e) {
      console.error(e);
      // silently ignore to not break inbox
    }
  }

  async function saveSettings() {
    try {
      await apiPost(`../php/notifications_api.php?action=settings_save`, {
        low_inventory: !!set_low_inventory?.checked,
        server_downtime: !!set_server_downtime?.checked,
        daily_summary: !!set_daily_summary?.checked,
        new_user_signups: !!set_new_user_signups?.checked,
      });
      alert("Configuration saved.");
    } catch (e) {
      console.error(e);
      alert(e.message || "Failed to save configuration");
    }
  }

  // Mark all as read
  btnClear?.addEventListener("click", async () => {
    try {
      await apiGet(`../php/notifications_api.php?action=mark_all_read`);
      await loadNotifications();
    } catch (e) {
      console.error(e);
      alert(e.message || "Failed to mark all as read");
    }
  });

  // Save config
  btnSaveSettings?.addEventListener("click", saveSettings);

  // initial load
  loadNotifications();
  loadSettings();
})();
