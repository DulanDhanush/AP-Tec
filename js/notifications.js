// js/notifications.js
(() => {
  const notifList = document.getElementById("notifList");
  const notifCount = document.getElementById("notifCount");
  const btnClear = document.getElementById("btnClear");

  const tabBtns = document.querySelectorAll(".tab-btn");
  const sections = document.querySelectorAll(".tab-section");

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  async function apiGet(url) {
    const res = await fetch(url, {
      cache: "no-store",
      credentials: "same-origin",
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data || data.ok !== true) {
      throw new Error(
        data && data.error ? data.error : `Request failed: ${res.status}`,
      );
    }
    return data;
  }

  // ✅ Matches your CSS: icon-alert / icon-system / icon-success
  // ✅ Matches your sample icons: triangle-exclamation / server / cart-shopping
  function iconMeta(n) {
    const type = String(n.type || "").toLowerCase();
    const title = String(n.title || "").toLowerCase();

    // Alerts
    if (
      type === "alert" ||
      title.includes("critical") ||
      title.includes("urgent") ||
      title.includes("failed")
    ) {
      return { wrapClass: "icon-alert", iconClass: "fa-triangle-exclamation" };
    }

    // System
    if (
      type === "system" ||
      title.includes("backup") ||
      title.includes("database") ||
      title.includes("server")
    ) {
      return { wrapClass: "icon-system", iconClass: "fa-server" };
    }

    // Success / Order
    if (
      type === "success" ||
      title.includes("order") ||
      title.includes("approved") ||
      title.includes("resolved")
    ) {
      return { wrapClass: "icon-success", iconClass: "fa-cart-shopping" };
    }

    // Default -> system style
    return { wrapClass: "icon-system", iconClass: "fa-bell" };
  }

  function renderNotifications(rows) {
    if (!notifList) return;

    if (!rows || rows.length === 0) {
      notifList.innerHTML = `<div style="color: var(--secondary); padding: 10px;">No notifications.</div>`;
      return;
    }

    // ✅ EXACT UI structure from your notifications.html
    notifList.innerHTML = rows
      .map((n) => {
        const isRead = Number(n.is_read) === 1;
        const meta = iconMeta(n);

        return `
        <div class="notif-item ${isRead ? "" : "unread"}">
          <div class="notif-icon ${esc(meta.wrapClass)}">
            <i class="fa-solid ${esc(meta.iconClass)}"></i>
          </div>
          <div class="notif-content">
            <h4>${esc(n.title || "Notification")}</h4>
            <p>${esc(n.message || "")}</p>
          </div>
          <div class="notif-time">${esc(n.created_at || "")}</div>
        </div>
      `;
      })
      .join("");
  }

  async function loadInbox() {
    if (!notifList) return;

    notifList.innerHTML = `<div style="color: var(--secondary); padding: 10px;">Loading notifications...</div>`;

    const data = await apiGet("../php/notifications_api.php?action=list");

    // ✅ Update inbox badge (your page uses id="notifCount")
    if (notifCount) notifCount.textContent = String(data.unread ?? 0);

    // ✅ Render list
    renderNotifications(data.notifications || []);
  }

  async function markAllRead() {
    if (!btnClear) return;

    btnClear.disabled = true;
    const old = btnClear.textContent;
    btnClear.textContent = "Marking...";

    try {
      await apiGet("../php/notifications_api.php?action=mark_all_read");
      await loadInbox(); // ✅ refresh list + badge
    } catch (e) {
      alert("Mark all as read failed: " + e.message);
    } finally {
      btnClear.disabled = false;
      btnClear.textContent = old;
    }
  }

  // ✅ Button click
  if (btnClear) {
    btnClear.addEventListener("click", (e) => {
      e.preventDefault();
      markAllRead();
    });
  }

  // ✅ Tabs (keep your UI behavior)
  tabBtns.forEach((btn) => {
    btn.addEventListener("click", async () => {
      tabBtns.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");

      const target = btn.getAttribute("data-target");
      sections.forEach((sec) => {
        sec.style.display = sec.id === target ? "" : "none";
      });

      if (target === "section-inbox") await loadInbox();
    });
  });

  // Initial load
  loadInbox().catch((e) => {
    if (notifList)
      notifList.innerHTML = `<div style="color:#e74c3c; padding:10px;">${esc(e.message)}</div>`;
  });
})();
