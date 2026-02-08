// js/user_mgr.js

document.addEventListener("DOMContentLoaded", () => {
  const apiList = "../php/users_api.php";
  const apiGet = "../php/user_get.php";
  const apiSave = "../php/user_save.php";
  const apiStatus = "../php/user_status.php";

  const tbody = document.getElementById("usersTbody");
  const searchInput = document.getElementById("searchInput");
  const roleFilter = document.getElementById("roleFilter");

  const btnAddUser = document.getElementById("btnAddUser");
  const userModal = document.getElementById("userModal");
  const closeModalBtn = document.querySelector(".close-modal");
  const cancelBtn = document.querySelector(".btn-cancel");

  const form = document.getElementById("userForm");
  const inpID = document.getElementById("inpID");
  const inpName = document.getElementById("inpName");
  const inpEmail = document.getElementById("inpEmail");
  const inpRole = document.getElementById("inpRole");
  const inpPassword = document.getElementById("inpPassword");
  const btnSave = document.getElementById("btnSave");
  const modalTitle = document.getElementById("modalTitle");
  const modalSub = document.getElementById("modalSub");

  // --- safety check ---
  if (!tbody || !searchInput || !roleFilter) {
    console.error(
      "Missing required elements. Check IDs: usersTbody, searchInput, roleFilter",
    );
    return;
  }

  function escapeHtml(str) {
    return String(str ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function initialsFromName(name) {
    const parts = (name || "").trim().split(/\s+/);
    const a = (parts[0] || "U").charAt(0);
    const b = (parts[1] || parts[0] || "S").charAt(0);
    return (a + b).toUpperCase();
  }

  function badge(status) {
    if (status === "Active")
      return `<span class="status-badge status-active">Active</span>`;
    if (status === "Banned")
      return `<span class="status-badge status-pending">Banned</span>`;
    return `<span class="status-badge status-pending">Inactive</span>`;
  }

  function render(users) {
    tbody.innerHTML = "";

    if (!users || users.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" style="color:#ccc;">No users found.</td></tr>`;
      return;
    }

    users.forEach((u) => {
      const name = u.full_name || u.username || "User";
      const init = initialsFromName(name);

      const row = document.createElement("tr");
      row.innerHTML = `
        <td>#${escapeHtml(u.user_id)}</td>
        <td style="display:flex; align-items:center; gap:10px; border:none;">
          <div class="user-avatar" style="width:32px; height:32px; font-size:0.8rem;">
            ${escapeHtml(init)}
          </div>
          ${escapeHtml(name)}
        </td>
        <td><span style="color: var(--text-white)">${escapeHtml(u.role)}</span></td>
        <td>${escapeHtml(u.email || "")}</td>
        <td>${badge(u.status)}</td>
        <td>
          <div class="action-buttons" style="gap: 8px">
            <button class="btn-icon btn-edit" title="Edit User" data-edit="${escapeHtml(u.user_id)}">
              <i class="fa-solid fa-pen"></i>
            </button>
            <button class="btn-icon btn-delete" title="${u.status === "Active" ? "Deactivate User" : "Activate User"}"
              data-toggle="${escapeHtml(u.user_id)}" data-status="${escapeHtml(u.status)}">
              <i class="fa-solid ${u.status === "Active" ? "fa-trash" : "fa-check"}"></i>
            </button>
          </div>
        </td>
      `;
      tbody.appendChild(row);
    });
  }

  async function loadUsers() {
    const q = searchInput.value.trim();
    const role = roleFilter.value;

    const url = `${apiList}?q=${encodeURIComponent(q)}&role=${encodeURIComponent(role)}&_=${Date.now()}`;

    try {
      const res = await fetch(url, { credentials: "same-origin" });

      // If your API redirects to login, this catches it
      const text = await res.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch {
        tbody.innerHTML = `<tr><td colspan="6" style="color:#ccc;">
          API not returning JSON. You may be redirected to login or wrong path.<br>
          Check: ${escapeHtml(url)}
        </td></tr>`;
        console.error("Non-JSON API response:", text);
        return;
      }

      if (!json.ok) {
        tbody.innerHTML = `<tr><td colspan="6" style="color:#ccc;">${escapeHtml(json.error || "Failed to load users")}</td></tr>`;
        return;
      }

      render(json.data);
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="6" style="color:#ccc;">Network error loading users.</td></tr>`;
      console.error(err);
    }
  }

  function openModal(mode) {
    userModal.classList.add("active");

    if (mode === "add") {
      modalTitle.textContent = "Add New User";
      modalSub.textContent = "Create a new account for system access.";
      btnSave.textContent = "Create Account";

      inpID.value = "";
      inpName.value = "";
      inpEmail.value = "";
      inpRole.value = "Employee";
      inpPassword.value = "Welcome123";
    }
  }

  function closeModal() {
    userModal.classList.remove("active");
  }

  // UI events
  btnAddUser?.addEventListener("click", () => openModal("add"));
  closeModalBtn?.addEventListener("click", closeModal);
  cancelBtn?.addEventListener("click", closeModal);
  userModal?.addEventListener("click", (e) => {
    if (e.target === userModal) closeModal();
  });

  // Search debounce
  let t = null;
  searchInput.addEventListener("input", () => {
    clearTimeout(t);
    t = setTimeout(loadUsers, 200);
  });

  roleFilter.addEventListener("change", loadUsers);

  // Actions: edit / toggle status
  tbody.addEventListener("click", async (e) => {
    const editBtn = e.target.closest("[data-edit]");
    const toggleBtn = e.target.closest("[data-toggle]");

    if (editBtn) {
      const id = editBtn.getAttribute("data-edit");
      const res = await fetch(
        `${apiGet}?id=${encodeURIComponent(id)}&_=${Date.now()}`,
        { credentials: "same-origin" },
      );
      const json = await res.json();
      if (!json.ok) return alert(json.error || "Failed to load user");

      const u = json.data;

      userModal.classList.add("active");
      modalTitle.textContent = "Edit User";
      modalSub.textContent = "Update user details and access role.";
      btnSave.textContent = "Save Changes";

      inpID.value = u.user_id;
      inpName.value = u.full_name || "";
      inpEmail.value = u.email || "";
      inpRole.value = u.role || "Employee";
      inpPassword.value = ""; // blank = don't change
      return;
    }

    if (toggleBtn) {
      const id = toggleBtn.getAttribute("data-toggle");
      const current = toggleBtn.getAttribute("data-status");
      const newStatus = current === "Active" ? "Inactive" : "Active";

      if (!confirm(`Set this user to ${newStatus}?`)) return;

      const res = await fetch(apiStatus, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ id: Number(id), status: newStatus }),
      });

      const json = await res.json();
      if (!json.ok) return alert(json.error || "Failed to update status");
      await loadUsers();
    }
  });

  // Save user
  form?.addEventListener("submit", async (e) => {
    e.preventDefault();

    const payload = {
      id: inpID.value ? Number(inpID.value) : 0,
      full_name: inpName.value.trim(),
      email: inpEmail.value.trim(),
      role: inpRole.value,
      password: inpPassword.value,
    };

    const res = await fetch(apiSave, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(payload),
    });

    const json = await res.json();
    if (!json.ok) return alert(json.error || "Failed to save user");

    closeModal();
    await loadUsers();
  });

  // First load
  loadUsers();
});
