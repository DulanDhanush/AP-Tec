// js/employee.js
(() => {
  // DB stores status text exactly like these:
  const STATUS_MAP = {
    Pending: "Pending",
    "In Progress": "In Progress",
    Completed: "Completed",
    "Waiting for Parts": "Waiting for Parts",
    Cancelled: "Cancelled",
  };

  const taskGrid = document.getElementById("taskGrid");
  const tabBtns = document.querySelectorAll(".tab-btn");
  const availBtn = document.getElementById("availBtn");
  const statusText = document.getElementById("statusText");

  // modal
  const modal = document.getElementById("taskModal");
  const closeModalBtn = modal?.querySelector(".close-modal");
  const cancelBtn = modal?.querySelector(".btn-cancel");
  const form = modal?.querySelector("form");

  const modalTaskTitle = document.getElementById("modalTaskTitle");
  const modalTaskId = document.getElementById("modalTaskId");
  const selStatus = modal?.querySelector("select.modal-input");
  const txtNotes = modal?.querySelector("textarea.modal-input");
  const proofUpload = document.getElementById("proofUpload");
  const proofPreview = document.getElementById("proofPreview");

  let currentFilter = "all";
  let activeTask = null;

  function escapeHtml(s) {
    return String(s ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  async function apiGet(action, params = {}) {
    const url = new URL("../php/employee_api.php", window.location.href);
    url.searchParams.set("action", action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

    const res = await fetch(url.toString(), { credentials: "include" });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.message || "Request failed");
    return data;
  }

  async function apiPostForm(action, formData) {
    const url = new URL("../php/employee_api.php", window.location.href);
    url.searchParams.set("action", action);

    const res = await fetch(url.toString(), {
      method: "POST",
      credentials: "include",
      body: formData,
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) throw new Error(data.message || "Request failed");
    return data;
  }

  // DB priority values: Low, Normal, High, Urgent
  function mapPriorityClass(p) {
    const x = String(p || "").toLowerCase();
    if (x === "urgent" || x === "high") return "priority-high";
    if (x === "low") return "priority-low";
    return "priority-med"; // Normal
  }

  // DB status values are Title Case strings
  function mapStatusBadge(status) {
    const s = String(status || "").toLowerCase();

    if (s === "completed") return { label: "Done", badge: "status-active" };
    if (s === "pending") return { label: "Pending", badge: "status-pending" };
    if (s === "in progress")
      return { label: "In Progress", badge: "status-pending" };
    if (s === "waiting for parts")
      return { label: "Waiting for Parts", badge: "status-pending" };
    if (s === "cancelled")
      return { label: "Cancelled", badge: "status-pending" };

    return { label: status, badge: "status-pending" };
  }

  // due_date is DATE (YYYY-MM-DD)
  function dueLabel(dueDate) {
    if (!dueDate) return "";
    const d = new Date(String(dueDate) + "T00:00:00");
    if (isNaN(d.getTime())) return String(dueDate);
    return d.toLocaleDateString([], {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }

  function openModal(task) {
    activeTask = task;

    modalTaskTitle.textContent = task.title || "Task";
    // ✅ your DB uses task_reference
    modalTaskId.textContent = "#" + (task.task_reference || "");

    // ✅ task.status already matches dropdown values
    // ensure option exists
    const st = task.status || "Pending";
    selStatus.value = STATUS_MAP[st] ? st : "Pending";

    // preload existing notes (optional)
    txtNotes.value = task.technician_notes || "";
    proofUpload.value = "";
    proofPreview.textContent = "";

    modal.classList.add("active");
    modal.style.display = "flex";
  }

  function closeModal() {
    activeTask = null;
    modal.classList.remove("active");
    modal.style.display = "none";
  }

  function renderTasks(items) {
    taskGrid.innerHTML = "";

    if (!items.length) {
      taskGrid.innerHTML = `<div style="color: var(--secondary); padding: 10px;">No tasks found.</div>`;
      return;
    }

    items.forEach((t) => {
      const priClass = mapPriorityClass(t.priority);
      const st = mapStatusBadge(t.status);

      // ✅ your API returns due_date (DATE)
      const due = dueLabel(t.due_date);

      const loc = t.location || "";
      const desc = t.description || "";
      const cust = t.customer_name ? ` - ${t.customer_name}` : "";

      const card = document.createElement("div");
      card.className = `task-card ${priClass}`;
      card.dataset.status = t.status;

      card.innerHTML = `
        <div class="task-header">
          <span class="task-id">#${escapeHtml(t.task_reference || "")}</span>
          <span class="status-badge ${escapeHtml(st.badge)}">${escapeHtml(st.label)}</span>
        </div>

        <h3 style="color: white; font-size: 1.1rem; margin-bottom: 5px;">
          ${escapeHtml(t.title)}
        </h3>

        <p style="color: var(--secondary); font-size: 0.9rem; margin-bottom: 15px;">
          <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i>
          ${escapeHtml(loc)}${escapeHtml(cust)}
        </p>

        <p style="color: var(--text-dim); font-size: 0.85rem;">
          ${escapeHtml(desc)}
        </p>

        <div class="task-meta">
          <span>${due ? `<i class="fa-regular fa-clock"></i> Due: ${escapeHtml(due)}` : ""}</span>
          <button class="btn-icon btn-edit btn-update-task" title="Update Status">
            <i class="fa-solid fa-pen-to-square"></i>
          </button>
        </div>
      `;

      card
        .querySelector(".btn-update-task")
        .addEventListener("click", () => openModal(t));
      taskGrid.appendChild(card);
    });
  }

  async function loadTasks() {
    const data = await apiGet("list_tasks", { filter: currentFilter });
    renderTasks(data.items || []);
  }

  // Tabs (your HTML uses data-filter="progress" etc.)
  tabBtns.forEach((btn) => {
    btn.addEventListener("click", async () => {
      tabBtns.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      currentFilter = btn.dataset.filter || "all";
      await loadTasks();
    });
  });

  // Availability
  async function loadAvailability() {
    const data = await apiGet("get_availability");
    const is = Number(data.is_available || 0) === 1;
    statusText.textContent = is ? "Available" : "Busy";
    statusText.style.color = is ? "#2ecc71" : "#e74c3c";
  }

  availBtn?.addEventListener("click", async () => {
    const isNowAvailable =
      statusText.textContent.trim().toLowerCase() !== "available";
    try {
      await apiPostForm(
        "set_availability",
        new URLSearchParams({ is_available: isNowAvailable ? "1" : "0" }),
      );
      await loadAvailability();
    } catch (e) {
      alert(e.message || "Availability update failed");
    }
  });

  // Proof preview
  proofUpload?.addEventListener("change", () => {
    const f = proofUpload.files?.[0];
    proofPreview.textContent = f ? `Selected: ${f.name}` : "";
  });

  // Modal events
  closeModalBtn?.addEventListener("click", closeModal);
  cancelBtn?.addEventListener("click", closeModal);
  modal?.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });

  // Submit update
  form?.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!activeTask) return;

    // ✅ Send EXACT DB status text
    const label = selStatus.value;
    const statusToSend = STATUS_MAP[label] ? label : "Pending";

    const fd = new FormData();
    fd.append("task_id", String(activeTask.task_id));
    fd.append("status", statusToSend);
    fd.append("notes", txtNotes.value || "");

    const file = proofUpload.files?.[0];
    if (file) fd.append("proof", file);

    try {
      await apiPostForm("update_task", fd);
      closeModal();
      await loadTasks();
    } catch (err) {
      alert(err.message || "Update failed");
    }
  });

  // Init
  (async () => {
    try {
      await loadAvailability();
      await loadTasks();
    } catch (e) {
      console.error(e);
      if (taskGrid) {
        taskGrid.innerHTML = `<div style="color:#e74c3c; padding:10px;">${escapeHtml(e.message || "Failed")}</div>`;
      }
    }
  })();
})();
