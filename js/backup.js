// js/backup.js
(function () {
  const btnCreate = document.getElementById("btnCreateBackup");
  const btnRestore = document.getElementById("btnRestore");
  const fileRestore = document.getElementById("fileRestore");
  const uploadZone = document.querySelector(".upload-zone");

  const lastBackupText = document.getElementById("lastBackupText");
  const backupStatus = document.getElementById("backupStatus");
  const tableBody = document.querySelector("table.table-responsive tbody");

  // --- show selected filename in the square ---
  function updateUploadZoneLabel(file) {
    if (!uploadZone) return;

    const title = uploadZone.querySelector("h3");
    const sub = uploadZone.querySelector("p");

    if (file) {
      if (title) title.textContent = "Selected File";
      if (sub) sub.textContent = file.name;
    } else {
      if (title) title.textContent = "Click to Upload";
      if (sub) sub.textContent = "or drag and drop .sql files here";
    }
  }

  // Upload zone logic (single clean block)
  if (uploadZone && fileRestore) {
    uploadZone.addEventListener("click", () => fileRestore.click());

    fileRestore.addEventListener("change", () => {
      const file = fileRestore.files && fileRestore.files[0];
      updateUploadZoneLabel(file);
    });

    uploadZone.addEventListener("dragover", (e) => {
      e.preventDefault();
    });

    uploadZone.addEventListener("drop", (e) => {
      e.preventDefault();
      const file = e.dataTransfer.files && e.dataTransfer.files[0];
      if (file) {
        fileRestore.files = e.dataTransfer.files;
        updateUploadZoneLabel(file);
      }
    });
  }

  function fmtBytes(bytes) {
    const b = Number(bytes || 0);
    if (b < 1024) return `${b} B`;
    const kb = b / 1024;
    if (kb < 1024) return `${kb.toFixed(1)} KB`;
    const mb = kb / 1024;
    if (mb < 1024) return `${mb.toFixed(1)} MB`;
    const gb = mb / 1024;
    return `${gb.toFixed(2)} GB`;
  }

  function toUiDate(mysqlDateTime) {
    if (!mysqlDateTime) return "—";
    const d = new Date(mysqlDateTime.replace(" ", "T"));
    if (isNaN(d.getTime())) return mysqlDateTime;
    return d.toLocaleString(undefined, {
      year: "numeric",
      month: "short",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  async function apiGet(action) {
    return fetch(`../php/backup_api.php?action=${encodeURIComponent(action)}`, {
      credentials: "include",
    });
  }

  async function apiPost(action, formDataOrObject) {
    let body;
    let headers = undefined;

    if (formDataOrObject instanceof FormData) {
      body = formDataOrObject;
    } else {
      body = new URLSearchParams(formDataOrObject || {});
      headers = { "Content-Type": "application/x-www-form-urlencoded" };
    }

    return fetch(`../php/backup_api.php?action=${encodeURIComponent(action)}`, {
      method: "POST",
      credentials: "include",
      headers,
      body,
    });
  }

  function setStatus(text, ok = true) {
    if (!backupStatus) return;
    backupStatus.textContent = text;
    backupStatus.classList.remove("status-active", "status-danger");
    backupStatus.classList.add(ok ? "status-active" : "status-danger");
  }

  function renderRows(items) {
    if (!tableBody) return;
    tableBody.innerHTML = "";

    (items || []).forEach((it) => {
      const tr = document.createElement("tr");

      const backupId = it.backup_code || `BK-${it.id}`;
      const when = toUiDate(it.created_at);
      const size = fmtBytes(it.file_size_bytes);
      const typeColor =
        it.backup_type === "Manual" ? "#f1c40f" : "var(--primary)";
      const statusText = it.status || "Verified";

      tr.innerHTML = `
        <td class="font-mono">${backupId}</td>
        <td>${when}</td>
        <td>${size}</td>
        <td><span style="color:${typeColor}">${it.backup_type || "Manual"}</span></td>
        <td><span class="status-badge status-active">${statusText}</span></td>
        <td>
          <div class="action-buttons">
            <button class="btn-icon btn-view" title="Download" data-download="${it.id}">
              <i class="fa-solid fa-download"></i>
            </button>
            <button class="btn-icon btn-delete" title="Delete" data-delete="${it.id}">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        </td>
      `;

      tableBody.appendChild(tr);
    });

    // bind actions
    tableBody.querySelectorAll("[data-download]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = btn.getAttribute("data-download");
        window.location.href = `../php/backup_api.php?action=download&id=${encodeURIComponent(id)}`;
      });
    });

    tableBody.querySelectorAll("[data-delete]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = btn.getAttribute("data-delete");
        if (!confirm("Delete this backup?")) return;

        const res = await apiPost("delete", { id });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
          alert(data.message || "Delete failed");
          return;
        }
        await refresh();
      });
    });
  }

  async function refresh() {
    const res = await apiGet("list");
    const data = await res.json().catch(() => ({}));

    if (!res.ok || !data.ok) {
      setStatus("Error", false);
      return;
    }

    setStatus("Secure", true);

    if (lastBackupText) {
      lastBackupText.textContent = data.last_backup
        ? toUiDate(data.last_backup)
        : "—";
    }

    renderRows(data.items || []);
  }

  // Create backup
  if (btnCreate) {
    btnCreate.addEventListener("click", async () => {
      btnCreate.disabled = true;
      setStatus("Backing up...", true);

      const res = await apiGet("create");
      const data = await res.json().catch(() => ({}));

      btnCreate.disabled = false;

      if (!res.ok || !data.ok) {
        setStatus("Failed", false);
        alert(data.message || "Backup failed");
        return;
      }

      alert(data.message || "Backup completed");
      await refresh();
    });
  }

  // Restore
  if (btnRestore && fileRestore) {
    btnRestore.addEventListener("click", async () => {
      const f = fileRestore.files && fileRestore.files[0];
      if (!f) {
        alert("Please upload a .sql file first.");
        return;
      }
      if (!confirm("Restore will overwrite current database data. Continue?"))
        return;

      btnRestore.disabled = true;
      setStatus("Restoring...", true);

      const fd = new FormData();
      fd.append("file", f);

      const res = await apiPost("restore", fd);
      const data = await res.json().catch(() => ({}));

      btnRestore.disabled = false;

      if (!res.ok || !data.ok) {
        setStatus("Restore Failed", false);
        alert(data.message || "Restore failed");
        return;
      }

      alert(data.message || "Restored successfully");
      setStatus("Secure", true);
      await refresh();
    });
  }

  // init
  refresh();
})();
