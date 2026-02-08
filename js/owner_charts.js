/* Owner Dashboard - Chart.js Logic (REAL DATA) */

let revenueChart = null;
let serviceChart = null;

function money(n) {
  return Number(n || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

// Safe fetch that shows real PHP errors if returned HTML
async function fetchJSON(url) {
  const res = await fetch(url, { cache: "no-store", credentials: "include" });
  const text = await res.text();

  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error("API returned non-JSON:\n" + text.slice(0, 300));
  }

  if (!data.ok) {
    throw new Error(
      (data.error || "API error") + (data.where ? ` (${data.where})` : ""),
    );
  }
  return data;
}

function detectRangeFromText(text) {
  const t = (text || "").trim().toLowerCase();
  if (t.includes("quarter")) return "last_quarter";
  if (t.includes("year")) return "ytd";
  return "this_month";
}

function getActiveRange() {
  const active = document.querySelector(".filter-bar .btn-glass.active");
  return detectRangeFromText(active?.textContent || "This Month");
}

function setActiveButton(btn) {
  document.querySelectorAll(".filter-bar .btn-glass").forEach((b) => {
    b.classList.remove("active");
    b.style.borderColor = "";
    b.style.color = "";
  });
  btn.classList.add("active");
  btn.style.borderColor = "var(--primary)";
  btn.style.color = "var(--primary)";
}

function badgeForStatus(status) {
  const st = String(status || "").toLowerCase();
  if (st === "paid" || st === "approved")
    return { cls: "status-active", text: "Completed" };
  if (st === "pending" || st === "unpaid")
    return { cls: "status-pending", text: "Pending" };
  return { cls: "status-inactive", text: status || "Unknown" };
}

async function loadKpisAndTable(range) {
  // KPIs
  const s = await fetchJSON(
    `../php/owner_bi_api.php?action=summary&range=${encodeURIComponent(range)}`,
  );

  document.getElementById("kpiRevenue").textContent = `${money(s.revenue)}`;
  document.getElementById("kpiProfit").textContent = `${money(s.profit)}`;
  document.getElementById("kpiOutstanding").textContent =
    `${money(s.outstanding)}`;
  document.getElementById("kpiContracts").textContent = `${s.activeContracts}`;
  document.getElementById("kpiOverdueText").textContent =
    `${s.overdueCount} Invoices Overdue`;

  const label =
    range === "this_month"
      ? "This Month"
      : range === "last_quarter"
        ? "Last Quarter"
        : "Year to Date";

  document.getElementById("rangeLabel").innerHTML =
    `<i class="fa-regular fa-calendar"></i> ${label} (${s.start} → ${s.end})`;

  // Transactions
  const t = await fetchJSON(
    `../php/owner_bi_api.php?action=transactions&range=${encodeURIComponent(range)}&limit=10`,
  );

  const tbody = document.getElementById("trxBody");
  tbody.innerHTML = t.rows
    .map((r) => {
      const amt = Number(r.amount || 0);
      const amtText = `${amt >= 0 ? "+" : "-"}${money(Math.abs(amt))}`;
      const amtColor = amt >= 0 ? "#64ffda" : "#e74c3c";
      const b = badgeForStatus(r.status);

      return `
        <tr>
          <td class="font-mono">${r.trx_id ?? ""}</td>
          <td>${r.trx_date ?? ""}</td>
          <td>${r.party ?? "-"}</td>
          <td>${r.trx_type ?? ""}</td>
          <td style="color:${amtColor}">${amtText}</td>
          <td><span class="status-badge ${b.cls}">${b.text}</span></td>
        </tr>
      `;
    })
    .join("");
}

async function loadCharts(range) {
  // Global chart defaults
  Chart.defaults.color = "#8892b0";
  Chart.defaults.font.family = "'Poppins', sans-serif";

  /* ----------------- CHART 1: Revenue vs Expenses ----------------- */
  const ctxRevenue = document.getElementById("revenueChart").getContext("2d");

  const gradientRevenue = ctxRevenue.createLinearGradient(0, 0, 0, 400);
  gradientRevenue.addColorStop(0, "rgba(100, 255, 218, 0.5)");
  gradientRevenue.addColorStop(1, "rgba(100, 255, 218, 0.0)");

  const gradientExpense = ctxRevenue.createLinearGradient(0, 0, 0, 400);
  gradientExpense.addColorStop(0, "rgba(231, 76, 60, 0.5)");
  gradientExpense.addColorStop(1, "rgba(231, 76, 60, 0.0)");

  const ch1 = await fetchJSON(
    `../php/owner_bi_api.php?action=chart_revenue_expenses&range=${encodeURIComponent(range)}`,
  );

  if (revenueChart) revenueChart.destroy();
  revenueChart = new Chart(ctxRevenue, {
    type: "line",
    data: {
      labels: ch1.labels,
      datasets: [
        {
          label: "Revenue",
          data: ch1.revenue,
          borderColor: "#64ffda",
          backgroundColor: gradientRevenue,
          fill: true,
          tension: 0.4,
        },
        {
          label: "Expenses",
          data: ch1.expenses,
          borderColor: "#e74c3c",
          backgroundColor: gradientExpense,
          fill: true,
          tension: 0.4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: "top" },
        tooltip: {
          backgroundColor: "rgba(17, 34, 64, 0.9)",
          titleColor: "#fff",
          bodyColor: "#fff",
          borderColor: "rgba(100, 255, 218, 0.2)",
          borderWidth: 1,
        },
      },
      scales: {
        y: { grid: { color: "rgba(255, 255, 255, 0.05)" } },
        x: { grid: { display: false } },
      },
    },
  });

  /* ----------------- CHART 2: Service Mix ----------------- */
  const ctxService = document.getElementById("serviceChart").getContext("2d");

  const ch2 = await fetchJSON(
    `../php/owner_bi_api.php?action=chart_service_mix&range=${encodeURIComponent(range)}`,
  );

  // ✅ Most Profitable (ONLY here; ch2 exists here)
  const topIdx = ch2.data
    .map((v, i) => ({ v: Number(v || 0), i }))
    .sort((a, b) => b.v - a.v)[0]?.i;

  const mp = document.getElementById("mostProfitable");
  if (mp) mp.textContent = topIdx !== undefined ? ch2.labels[topIdx] : "N/A";

  if (serviceChart) serviceChart.destroy();
  serviceChart = new Chart(ctxService, {
    type: "doughnut",
    data: {
      labels: ch2.labels,
      datasets: [
        {
          data: ch2.data,
          backgroundColor: ["#64ffda", "#4b6cb7", "#f1c40f", "#e74c3c"],
          borderWidth: 0,
          hoverOffset: 10,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: "right" } },
      cutout: "70%",
    },
  });

  // ✅ KPIs + Table
  await loadKpisAndTable(range);
}

document.addEventListener("DOMContentLoaded", () => {
  // Filter button clicks
  document.querySelectorAll(".filter-bar .btn-glass").forEach((btn) => {
    btn.addEventListener("click", async () => {
      setActiveButton(btn);
      const range = detectRangeFromText(btn.textContent);
      try {
        await loadCharts(range);
      } catch (e) {
        alert(e.message || "Failed to load dashboard");
      }
    });
  });

  // Generate report
  const reportBtn = document.getElementById("btnReport");
  if (reportBtn) {
    reportBtn.addEventListener("click", () => {
      const range = getActiveRange();
      window.open(
        `../php/owner_report.php?range=${encodeURIComponent(range)}`,
        "_blank",
      );
    });
  }

  // Initial load
  loadCharts("this_month").catch((e) =>
    alert(e.message || "Failed to load dashboard"),
  );
});
