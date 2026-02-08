/* js/employee.js - Real Data Dashboard */

document.addEventListener("DOMContentLoaded", () => {
  const api = "../php/employee_dashboard_api.php";

  const taskGrid = document.getElementById("taskGrid");
  const routeTimeline = document.getElementById("routeTimeline");
  const reqError = document.getElementById("reqError");

  const msgBadge = document.getElementById("msgBadge");
  const userName = document.getElementById("userName");

  const availBtn = document.getElementById("availBtn");
  const statusText = document.getElementById("statusText");
  const statusDot = availBtn ? availBtn.querySelector(".status-dot") : null;

  const tabBtns = document.querySelectorAll(".tab-btn");

  // modal
  const modal = document.getElementById("taskModal");
  const closeModalBtn = modal.querySelector(".close-modal");
  const cancelBtn = document.getElementById("modalCancelBtn");
  const modalTitle = document.getElementById("modalTaskTitle");
  const modalTaskId = document.getElementById("modalTaskId");
  const modalTaskDbId = document.getElementById("modalTaskDbId");
  const modalStatus = document.getElementById("modalStatus");
  const modalNotes = document.getElementById("modalNotes");
  const proofUpload = document.getElementById("proofUpload");
  const proofPreview = document.getElementById("proofPreview");
  const modalError = document.getElementById("modalError");
  const taskUpdateForm = document.getElementById("taskUpdateForm");

  let allTasks = [];
  let currentFilter = "all";

  function showError(msg) {
    reqError.style.display = "block";
    reqError.textContent = msg;
  }
  function hideError() {
    reqError.style.display = "none";
    reqError.textContent = "";
  }

  function esc(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;");
  }

  function toFilterKey(status) {
    // DB values: Pending, In Progress, Waiting for Parts, Completed, Cancelled
    if (status === "Pending") return "pending";
    if (status === "In Progress") return "progress";
    if (status === "Waiting for Parts") return "waiting_parts";
    if (status === "Completed") return "completed";
    return "all";
  }

  function priorityClass(priority) {
    // UI has priority-high/med/low – map
    if (priority === "Urgent" || priority === "High") return "priority-high";
    if (priority === "Normal") return "priority-med";
    return "priority-low";
  }

  function badgeForStatusOrPriority(task) {
    // Keep your design style: show priority badge for Urgent, else show status
    if (task.priority === "Urgent") {
      return `<span class="status-badge" style="background: rgba(231,76,60,0.1); color:#e74c3c;">Urgent</span>`;
    }
    if (task.status === "Completed") {
      return `<span class="status-badge status-active">Done</span>`;
    }
    if (task.status === "In Progress") {
      return `<span class="status-badge status-pending">In Progress</span>`;
    }
    if (task.status === "Waiting for Parts") {
      return `<span class="status-badge" style="background: rgba(241,196,15,0.15); color:#f1c40f;">Waiting</span>`;
    }
    return `<span class="status-badge" style="background: rgba(52,152,219,0.12); color:#3498db;">Pending</span>`;
  }

  function formatDue(dueDateStr) {
    if (!dueDateStr) return "No due date";
    // show YYYY-MM-DD for now (simple + reliable)
    return dueDateStr;
  }

  function renderTasks() {
    taskGrid.innerHTML = "";

    const filtered = allTasks.filter((t) => {
      if (currentFilter === "all") return true;
      return toFilterKey(t.status) === currentFilter;
    });

    if (filtered.length === 0) {
      taskGrid.innerHTML = `
        <div style="color: var(--secondary); padding: 20px;">
          No tasks found for this filter.
        </div>
      `;
      return;
    }

    for (const t of filtered) {
      const card = document.createElement("div");
      card.className = `task-card ${priorityClass(t.priority)}`;
      card.dataset.status = toFilterKey(t.status);

      card.innerHTML = `
        <div class="task-header">
          <span class="task-id">#${esc(t.task_reference || "TASK-" + t.task_id)}</span>
          ${badgeForStatusOrPriority(t)}
        </div>

        <h3 style="color:white; font-size:1.1rem; margin-bottom:5px;">
          ${esc(t.title)}
        </h3>

        <p style="color: var(--secondary); font-size: 0.9rem; margin-bottom: 15px;">
          <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i>
          ${esc(t.location || t.customer_name || "Unknown location")}
        </p>

        <p style="color: var(--text-dim); font-size: 0.85rem;">
          ${esc(t.description || "")}
        </p>

        <div class="task-meta">
          <span><i class="fa-regular fa-clock"></i> Due: ${esc(formatDue(t.due_date))}</span>
          <button class="btn-icon btn-edit btn-update-task" title="Update Status">
            <i class="fa-solid fa-pen-to-square"></i>
          </button>
        </div>
      `;

      const btn = card.querySelector(".btn-update-task");
      btn.addEventListener("click", () => openModal(t));

      taskGrid.appendChild(card);
    }
  }

  function renderRoute(events) {
    routeTimeline.innerHTML = "";

    if (!events || events.length === 0) {
      routeTimeline.innerHTML = `
        <div style="color: var(--secondary); font-size: 0.9rem;">
          No route items for today.
        </div>
      `;
      return;
    }

    events.forEach((e, idx) => {
      const time = e.start_time ? e.start_time.slice(0, 5) : "--:--";
      const ampm = e.start_time
        ? new Date(`1970-01-01T${e.start_time}`).toLocaleTimeString([], {
            hour: "2-digit",
            minute: "2-digit",
          })
        : time;

      const item = document.createElement("div");
      item.className = "timeline-item";
      item.innerHTML = `
        <div class="timeline-dot ${idx === 0 ? "active" : ""}"></div>
        <div class="timeline-time">${esc(ampm)}</div>
        <div class="timeline-content">
          <div style="color:white; font-weight:600;">${esc(e.title)}</div>
          <div style="color: var(--secondary); font-size: 0.8rem;">${esc(e.location || "")}</div>
        </div>
      `;
      routeTimeline.appendChild(item);
    });
  }

  function setAvailabilityUI(isAvail) {
    if (!statusDot) return;
    if (isAvail) {
      statusText.textContent = "Available";
      statusText.style.color = "#2ecc71";
      statusDot.style.background = "#2ecc71";
    } else {
      statusText.textContent = "Busy";
      statusText.style.color = "#e67e22";
      statusDot.style.background = "#e67e22";
    }
  }

  function openModal(task) {
    modalError.style.display = "none";
    modalError.textContent = "";

    modal.style.display = "flex";
    modalTitle.textContent = task.title || "Task";
    modalTaskId.textContent =
      "#" + (task.task_reference || "TASK-" + task.task_id);
    modalTaskDbId.value = task.task_id;

    modalStatus.value = task.status || "Pending";
    modalNotes.value = task.technician_notes || "";
    proofUpload.value = "";
    proofPreview.textContent = task.proof_image_path
      ? `Current: ${task.proof_image_path}`
      : "";
  }

  function closeModal() {
    modal.style.display = "none";
  }

  closeModalBtn.addEventListener("click", closeModal);
  cancelBtn.addEventListener("click", closeModal);
  modal.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });

  proofUpload.addEventListener("change", () => {
    if (!proofUpload.files || proofUpload.files.length === 0) {
      proofPreview.textContent = "";
      return;
    }
    proofPreview.textContent = `Selected: ${proofUpload.files[0].name}`;
  });

  taskUpdateForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    modalError.style.display = "none";
    modalError.textContent = "";

    const taskId = parseInt(modalTaskDbId.value || "0", 10);
    const status = modalStatus.value;
    const notes = modalNotes.value;

    const fd = new FormData();
    fd.append("task_id", String(taskId));
    fd.append("status", status);
    fd.append("notes", notes);

    if (proofUpload.files && proofUpload.files[0]) {
      fd.append("proof", proofUpload.files[0]);
    }

    try {
      const res = await fetch(`${api}?action=update_task`, {
        method: "POST",
        body: fd,
      });
      const data = await res.json();
      if (!data.ok) {
        modalError.style.display = "block";
        modalError.textContent = data.message || "Update failed";
        return;
      }

      closeModal();
      await loadTasks(); // refresh
    } catch (err) {
      modalError.style.display = "block";
      modalError.textContent = "Network error";
    }
  });

  tabBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      tabBtns.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      currentFilter = btn.dataset.filter || "all";
      renderTasks();
    });
  });

  availBtn.addEventListener("click", async () => {
    // toggle current UI state
    const isNowAvail =
      statusText.textContent.trim().toLowerCase() !== "available";
    try {
      const fd = new FormData();
      fd.append("is_available", isNowAvail ? "1" : "0");

      const res = await fetch(`${api}?action=toggle_availability`, {
        method: "POST",
        body: fd,
      });
      const data = await res.json();
      if (!data.ok) {
        showError(data.message || "Availability update failed");
        return;
      }
      hideError();
      setAvailabilityUI(data.is_available === 1);
    } catch {
      showError("Network error");
    }
  });

  async function loadMe() {
    const res = await fetch(`${api}?action=me`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Request failed");

    const u = data.user;
    userName.textContent = u.full_name || u.username || "User";

    // messages badge
    const count = Number(u.unread_messages || 0);
    if (count > 0) {
      msgBadge.style.display = "inline-block";
      msgBadge.textContent = String(count);
    } else {
      msgBadge.style.display = "none";
    }

    setAvailabilityUI(Number(u.is_available || 1) === 1);
  }

  async function loadTasks() {
    const res = await fetch(`${api}?action=tasks`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Request failed");

    allTasks = data.tasks || [];
    renderTasks();
  }

  async function loadRoute() {
    const res = await fetch(`${api}?action=route`);
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Request failed");
    renderRoute(data.events || []);
  }

  async function init() {
    try {
      hideError();
      await loadMe();
      await loadTasks();
      await loadRoute();
    } catch (e) {
      showError(e.message || "Request failed");
    }
  }

  init();
});
