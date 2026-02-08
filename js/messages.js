// js/messages.js
(() => {
  const contactList = document.querySelector(".contact-list");
  const chatBody = document.getElementById("chatBody");
  const chatInput = document.getElementById("chatInput");
  const btnSend = document.getElementById("btnSend");

  const activeUserAvatar = document.getElementById("activeUserAvatar");
  const activeUserName = document.getElementById("activeUserName");
  const activeUserRole = document.getElementById("activeUserRole");

  let activeUserId = null;

  function escapeHtml(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function initials(name) {
    const parts = String(name || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean);
    const a = parts[0]?.[0] || "U";
    const b = parts.length > 1 ? parts[parts.length - 1][0] : "";
    return (a + b).toUpperCase();
  }

  function fmtTime(ts) {
    const d = new Date(String(ts).replace(" ", "T"));
    if (isNaN(d.getTime())) return "";
    return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  }

  async function apiGet(action, params = {}) {
    const url = new URL("../php/messages_api.php", window.location.href);
    url.searchParams.set("action", action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

    const res = await fetch(url.toString(), { credentials: "include" });
    const text = await res.text();

    let data = {};
    try {
      data = JSON.parse(text);
    } catch {
      data = { ok: false, message: text || "Invalid JSON" };
    }

    if (!res.ok || !data.ok) {
      throw new Error(data.message || `HTTP ${res.status}`);
    }
    return data;
  }

  async function apiPost(action, obj) {
    const url = new URL("../php/messages_api.php", window.location.href);
    url.searchParams.set("action", action);

    const res = await fetch(url.toString(), {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(obj),
    });

    const text = await res.text();
    let data = {};
    try {
      data = JSON.parse(text);
    } catch {
      data = { ok: false, message: text || "Invalid JSON" };
    }

    if (!res.ok || !data.ok) {
      throw new Error(data.message || `HTTP ${res.status}`);
    }
    return data;
  }

  function renderContacts(items) {
    contactList.innerHTML = "";

    if (!items.length) {
      contactList.innerHTML = `<div style="padding:16px; color: var(--secondary);">No conversations yet.</div>`;
      return;
    }

    items.forEach((it, idx) => {
      const div = document.createElement("div");
      div.className = "contact-item" + (idx === 0 ? " active" : "");
      div.dataset.userId = it.user_id;

      const unread = Number(it.unread || 0);
      const time = it.last_at ? fmtTime(it.last_at) : "";
      const ini = initials(it.full_name);

      div.innerHTML = `
        <div style="position: relative;">
          <div class="user-avatar" style="background:#34495e;">${escapeHtml(ini)}</div>
          <div class="online-dot" style="background:#2ecc71;"></div>
        </div>
        <div class="contact-info">
          <div class="contact-name">${escapeHtml(it.full_name)}</div>
          <div class="contact-preview">${escapeHtml(it.last_message || "No messages")}</div>
        </div>
        <div class="contact-meta">
          <div>${escapeHtml(time)}</div>
          ${unread > 0 ? `<div style="background: var(--primary); color: black; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; margin-top: 5px;">${unread}</div>` : ""}
        </div>
      `;

      div.addEventListener("click", async () => {
        document
          .querySelectorAll(".contact-item")
          .forEach((x) => x.classList.remove("active"));
        div.classList.add("active");
        await openThread(it.user_id, it.full_name, it.role);
      });

      contactList.appendChild(div);
    });
  }

  async function openThread(userId, name, role) {
    activeUserId = userId;

    if (activeUserName) activeUserName.textContent = name || "Chat";
    if (activeUserRole) activeUserRole.textContent = role || "";
    if (activeUserAvatar) activeUserAvatar.textContent = initials(name);

    chatBody.innerHTML = `
      <div style="text-align:center; opacity:.6; padding:20px;">Loading...</div>
    `;

    const data = await apiGet("thread", { user_id: userId });
    const items = data.items || [];

    chatBody.innerHTML = `
      <div style="text-align: center; margin-bottom: 20px; opacity: 0.5; font-size: 0.8rem;">
        Encryption: On <i class="fa-solid fa-lock"></i><br>Today
      </div>
    `;

    items.forEach((m) => {
      const isOut = Number(m.sender_id) !== Number(userId); // if sender isn't the other user => it's me
      const bubble = document.createElement("div");
      bubble.className = "message-bubble " + (isOut ? "msg-out" : "msg-in");
      bubble.innerHTML = `
        ${escapeHtml(m.message_text)}
        <span class="msg-time">${escapeHtml(fmtTime(m.sent_at))}${isOut ? ' <i class="fa-solid fa-check-double"></i>' : ""}</span>
      `;
      chatBody.appendChild(bubble);
    });

    chatBody.scrollTop = chatBody.scrollHeight;
  }

  async function refresh() {
    const data = await apiGet("contacts");
    const items = data.items || [];
    renderContacts(items);

    // auto open first chat
    if (items.length) {
      await openThread(items[0].user_id, items[0].full_name, items[0].role);
    }
  }

  async function sendMessage() {
    const text = (chatInput.value || "").trim();
    if (!text) return;
    if (!activeUserId) return;

    btnSend.disabled = true;
    try {
      await apiPost("send", { receiver_id: activeUserId, message: text });
      chatInput.value = "";
      await openThread(
        activeUserId,
        activeUserName?.textContent || "",
        activeUserRole?.textContent || "",
      );
      await refresh(); // updates preview + unread
    } catch (e) {
      alert(e.message || "Send failed");
    } finally {
      btnSend.disabled = false;
    }
  }

  btnSend?.addEventListener("click", sendMessage);
  chatInput?.addEventListener("keydown", (e) => {
    if (e.key === "Enter") sendMessage();
  });

  refresh().catch((e) => {
    contactList.innerHTML = `<div style="padding:16px; color:#e74c3c;">${escapeHtml(e.message || "Request failed")}</div>`;
  });
})();
