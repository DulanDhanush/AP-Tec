/* Live Chat Side-Panel Logic (FIXED: no duplicates, correct direction, remembers messages) */

const CHAT_API = "../php/live_chat_api.php";

let chatAgent = null;
let myUserId = null;

let lastMsgId = 0;
let pollTimer = null;

// track already-rendered message IDs (prevents duplicates)
const renderedIds = new Set();

async function chatGet(action, params = {}) {
  const url = new URL(CHAT_API, window.location.href);
  url.searchParams.set("action", action);
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
  const res = await fetch(url.toString(), { credentials: "include" });
  return res.json();
}

async function chatPost(action, data) {
  const url = new URL(CHAT_API, window.location.href);
  url.searchParams.set("action", action);
  const res = await fetch(url.toString(), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",
    body: JSON.stringify(data || {}),
  });
  return res.json();
}

function timeLabel(dateStr) {
  try {
    const d = new Date(String(dateStr).replace(" ", "T"));
    return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  } catch {
    return "Now";
  }
}

function renderMessage(body, m) {
  const id = Number(m.message_id);
  if (renderedIds.has(id)) return; // ✅ prevent duplicates
  renderedIds.add(id);

  // ✅ correct direction
  const isMine = myUserId != null && Number(m.sender_id) === Number(myUserId);
  const dirClass = isMine ? "msg-out" : "msg-in";

  const div = document.createElement("div");
  div.className = `message-bubble ${dirClass} fade-in`;
  div.innerHTML = `${m.message_text}<span class="msg-time">${timeLabel(m.sent_at)}</span>`;
  body.appendChild(div);
  body.scrollTop = body.scrollHeight;

  lastMsgId = Math.max(lastMsgId, id);
}

async function loadMe() {
  const me = await chatGet("me");
  if (me.ok) myUserId = Number(me.user_id);
}

async function startChatAndLoadHistory(body, headerTitle) {
  // reset state ONLY when opening chat
  renderedIds.clear();
  lastMsgId = 0;

  const start = await chatGet("start");
  body.innerHTML = "";

  if (!start.ok) {
    body.innerHTML = "";
    const div = document.createElement("div");
    div.className = "message-bubble msg-in fade-in";
    div.innerHTML = `${start.error || "No agents available"}<span class="msg-time">Now</span>`;
    body.appendChild(div);
    return false;
  }

  chatAgent = start.agent;
  if (headerTitle)
    headerTitle.textContent = chatAgent.agent_name || "Support Agent";

  // load history (do NOT clear later)
  const hist = await chatGet("history");
  if (hist.ok) {
    for (const m of hist.messages) renderMessage(body, m);
  } else {
    const div = document.createElement("div");
    div.className = "message-bubble msg-in fade-in";
    div.innerHTML = `${hist.error || "Unable to load chat"}<span class="msg-time">Now</span>`;
    body.appendChild(div);
  }

  // If no history, show greeting
  if (!hist.ok || (hist.messages && hist.messages.length === 0)) {
    const div = document.createElement("div");
    div.className = "message-bubble msg-in fade-in";
    div.innerHTML = `Hello! How can I help?<span class="msg-time">Now</span>`;
    body.appendChild(div);
  }

  body.scrollTop = body.scrollHeight;
  return true;
}

async function pollNew(body) {
  if (!chatAgent) return;

  const r = await chatGet("poll", { last_id: lastMsgId });
  if (!r.ok) return;

  for (const m of r.messages) renderMessage(body, m);
}

document.addEventListener("DOMContentLoaded", () => {
  const chatTriggerBtns = document.querySelectorAll(".trigger-live-chat");
  const chatPanel = document.getElementById("liveChatPanel");
  const sidebar = document.querySelector(".sidebar");
  const closeBtn = document.getElementById("closeLiveChat");
  const backdrop = document.getElementById("chatBackdrop");

  const input = document.getElementById("popupChatInput");
  const body = document.getElementById("popupChatBody");
  const sendBtn = document.getElementById("popupSendBtn");

  const headerTitle = chatPanel?.querySelector(".chat-panel-header h4");

  const openChat = async () => {
    sidebar?.classList.add("hidden");
    chatPanel?.classList.add("active");
    backdrop?.classList.add("active");

    await loadMe();
    const ok = await startChatAndLoadHistory(body, headerTitle);
    if (!ok) return;

    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(() => {
      if (chatPanel.classList.contains("active")) pollNew(body);
    }, 1500);
  };

  const closeChat = () => {
    chatPanel?.classList.remove("active");
    backdrop?.classList.remove("active");

    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }

    setTimeout(() => sidebar?.classList.remove("hidden"), 100);
  };

  chatTriggerBtns.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      openChat();
    });
  });

  closeBtn?.addEventListener("click", closeChat);
  backdrop?.addEventListener("click", closeChat);

  const sendMessage = async () => {
    const text = input.value.trim();
    if (!text) return;

    // ✅ Do NOT fake-reply anymore.
    // We will optimistically render, but also ensure it won’t duplicate when poll returns.
    // Insert a temporary bubble without message_id: use a local object (no dedupe needed)
    const tempDiv = document.createElement("div");
    tempDiv.className = "message-bubble msg-out fade-in";
    tempDiv.innerHTML = `${text}<span class="msg-time">Now</span>`;
    body.appendChild(tempDiv);
    body.scrollTop = body.scrollHeight;

    input.value = "";

    const r = await chatPost("send", { message: text });

    if (!r.ok) {
      // show error bubble
      const err = document.createElement("div");
      err.className = "message-bubble msg-in fade-in";
      err.innerHTML = `${r.error || "Send failed"}<span class="msg-time">Now</span>`;
      body.appendChild(err);
      body.scrollTop = body.scrollHeight;
      return;
    }

    // ✅ IMPORTANT: Don’t clear chat.
    // We just wait for poll to fetch the DB saved message_id and future replies.
    // If you want instant message_id update, modify API to return inserted message_id.
  };

  sendBtn?.addEventListener("click", sendMessage);
  input?.addEventListener("keypress", (e) => {
    if (e.key === "Enter") sendMessage();
  });
});
