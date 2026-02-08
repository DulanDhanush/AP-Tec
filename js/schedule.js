// js/schedule.js
(() => {
  const monthDisplay = document.getElementById("currentMonthDisplay");
  const daysGrid = document.getElementById("daysGrid");
  const btnPrev = document.getElementById("btnPrevMonth");
  const btnNext = document.getElementById("btnNextMonth");

  const selectedDateDisplay = document.getElementById("selectedDateDisplay");
  const itineraryList = document.getElementById("itineraryList");

  const btnAddReminder = document.getElementById("btnAddReminder");
  const reminderModal = document.getElementById("reminderModal");
  const closeReminder = document.getElementById("closeReminder");
  const cancelReminder = document.getElementById("cancelReminder");

  const formReminder = document.getElementById("formReminder");
  const inpTitle = document.getElementById("inpTitle");
  const inpReminderDate = document.getElementById("inpReminderDate");
  const inpTime = document.getElementById("inpTime");
  const inpType = document.getElementById("inpType");
  const noteTextarea = formReminder.querySelector("textarea");

  // ====== State ======
  let viewDate = new Date(); // current month in view
  let selectedDate = new Date(); // selected day
  viewDate.setDate(1);

  // ====== Helpers ======
  const pad = (n) => String(n).padStart(2, "0");
  const toYMD = (d) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const toYM = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;

  function monthTitle(d) {
    return d.toLocaleString(undefined, { month: "long", year: "numeric" });
  }

  function formatDayLabel(d) {
    return d.toLocaleString(undefined, { month: "long", day: "numeric" });
  }

  function escapeHtml(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  async function apiGet(action, params = {}) {
    const url = new URL("../php/schedule_api.php", window.location.href);
    url.searchParams.set("action", action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    const res = await fetch(url.toString(), { credentials: "include" });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.message || "Request failed");
    return data;
  }

  async function apiPost(action, bodyObj = {}) {
    const url = new URL("../php/schedule_api.php", window.location.href);
    url.searchParams.set("action", action);

    const body = new URLSearchParams(bodyObj);
    const res = await fetch(url.toString(), {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.message || "Request failed");
    return data;
  }

  // ====== Render calendar ======
  async function renderCalendar() {
    if (monthDisplay) monthDisplay.textContent = monthTitle(viewDate);

    // Fetch month counts for dots
    let countsByDay = {};
    try {
      const ym = toYM(viewDate);
      const monthData = await apiGet("month", { ym });
      countsByDay = monthData.days || {};
    } catch (e) {
      countsByDay = {};
    }

    // Build day cells
    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();

    const firstDay = new Date(year, month, 1);
    const startWeekday = firstDay.getDay(); // 0=Sun

    const daysInMonth = new Date(year, month + 1, 0).getDate();

    daysGrid.innerHTML = "";

    // empty cells before day 1
    for (let i = 0; i < startWeekday; i++) {
      const empty = document.createElement("div");
      empty.className = "day-cell empty";
      empty.style.opacity = "0.3";
      daysGrid.appendChild(empty);
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const cellDate = new Date(year, month, day);
      const ymd = toYMD(cellDate);

      const cell = document.createElement("div");
      cell.className = "day-cell";
      cell.dataset.date = ymd;
      cell.innerHTML = `
        <div class="day-number">${day}</div>
        <div class="day-dots" style="display:flex; gap:6px; margin-top:8px;"></div>
      `;

      // highlight selected
      if (toYMD(selectedDate) === ymd) {
        cell.style.outline = "2px solid var(--primary)";
        cell.style.borderRadius = "10px";
      }

      // dots
      const dotsWrap = cell.querySelector(".day-dots");
      const c = countsByDay[ymd];
      if (c) {
        if (c.urgent > 0) dotsWrap.appendChild(makeDot("dot-task"));
        if (c.routine > 0) dotsWrap.appendChild(makeDot("dot-routine"));
        if (c.leave > 0) dotsWrap.appendChild(makeDot("dot-leave"));
      }

      cell.addEventListener("click", async () => {
        selectedDate = cellDate;
        await renderCalendar(); // rerender highlight
        await renderItinerary();
      });

      daysGrid.appendChild(cell);
    }
  }

  function makeDot(dotClass) {
    const d = document.createElement("div");
    d.className = `event-dot ${dotClass}`;
    return d;
  }

  // ====== Itinerary ======
  async function renderItinerary() {
    const ymd = toYMD(selectedDate);
    if (selectedDateDisplay)
      selectedDateDisplay.textContent = formatDayLabel(selectedDate);

    itineraryList.innerHTML = `
      <div style="color: var(--secondary); font-size: 0.9rem;">Loading...</div>
    `;

    try {
      const data = await apiGet("day", { date: ymd });
      const items = data.items || [];

      if (!items.length) {
        itineraryList.innerHTML = `
          <div style="color: var(--secondary); font-size: 0.9rem;">No reminders for this day.</div>
        `;
        return;
      }

      itineraryList.innerHTML = "";
      items.forEach((it) => {
        const time = new Date(it.start_at.replace(" ", "T")).toLocaleTimeString(
          [],
          {
            hour: "2-digit",
            minute: "2-digit",
          },
        );

        const badgeColor =
          it.type === "urgent"
            ? "var(--primary)"
            : it.type === "leave"
              ? "#f1c40f"
              : "rgba(255,255,255,0.15)";

        const card = document.createElement("div");
        card.className = "glass-card";
        card.style.padding = "14px";
        card.style.marginBottom = "12px";
        card.style.borderRadius = "12px";
        card.style.display = "flex";
        card.style.justifyContent = "space-between";
        card.style.alignItems = "center";
        card.innerHTML = `
          <div style="display:flex; flex-direction:column; gap:6px;">
            <div style="display:flex; align-items:center; gap:10px;">
              <span style="color:white; font-weight:600;">${escapeHtml(it.title)}</span>
              <span style="font-size:0.75rem; padding:4px 10px; border-radius:999px; background:${badgeColor}; color:white;">
                ${escapeHtml(it.type)}
              </span>
            </div>
            <div style="color: var(--secondary); font-size:0.85rem;">
              ${escapeHtml(it.note || "")}
            </div>
          </div>

          <div style="display:flex; align-items:center; gap:10px;">
            <div style="color: var(--secondary); font-family: 'Courier New', monospace;">${time}</div>
            <button class="btn-icon" title="Delete" data-del="${it.schedule_id}">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        `;

        card.querySelector("[data-del]").addEventListener("click", async () => {
          if (!confirm("Delete this reminder?")) return;
          try {
            await apiPost("delete", { schedule_id: it.schedule_id });
            await renderItinerary();
            await renderCalendar();
          } catch (e) {
            alert(e.message || "Delete failed");
          }
        });

        itineraryList.appendChild(card);
      });
    } catch (e) {
      itineraryList.innerHTML = `
        <div style="color:#e74c3c; font-size: 0.9rem;">${escapeHtml(e.message || "Failed to load")}</div>
      `;
    }
  }

  // ====== Modal ======
  function openModal() {
    // Prefill date with selected day
    inpReminderDate.value = toYMD(selectedDate);
    reminderModal.classList.add("active");
    reminderModal.style.display = "flex";
  }

  function closeModal() {
    reminderModal.classList.remove("active");
    reminderModal.style.display = "none";
    formReminder.reset();
    inpTime.value = "09:00";
    inpType.value = "routine";
  }

  btnAddReminder?.addEventListener("click", openModal);
  closeReminder?.addEventListener("click", closeModal);
  cancelReminder?.addEventListener("click", closeModal);

  reminderModal?.addEventListener("click", (e) => {
    if (e.target === reminderModal) closeModal();
  });

  // ====== Save reminder ======
  formReminder?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const title = inpTitle.value.trim();
    const date = inpReminderDate.value;
    const time = inpTime.value;
    const type = inpType.value;
    const note = (noteTextarea?.value || "").trim();

    try {
      await apiPost("create", { title, date, time, type, note });
      closeModal();
      await renderItinerary();
      await renderCalendar();
    } catch (err) {
      alert(err.message || "Save failed");
    }
  });

  // ====== Month nav ======
  btnPrev?.addEventListener("click", async () => {
    viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1);
    await renderCalendar();
  });

  btnNext?.addEventListener("click", async () => {
    viewDate = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1);
    await renderCalendar();
  });

  // ====== Init ======
  (async () => {
    // default to today in the current view month
    selectedDate = new Date();
    viewDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);

    await renderCalendar();
    await renderItinerary();
  })();
})();
