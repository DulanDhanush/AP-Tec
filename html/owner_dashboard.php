<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Owner Dashboard | AP Tec BI</title>

    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../css/style.css" />
  </head>
  <body>
    <div class="dashboard-container">
      <aside class="sidebar">
        <div class="sidebar-brand">
          <i class="fa-solid fa-layer-group"></i> AP TEC.
        </div>

        <ul class="nav-menu">
          <li class="nav-item">
            <a href="owner_dashboard.php" class="nav-link active">
              <i class="fa-solid fa-chart-line"></i> BI Reports
            </a>
          </li>
          <li class="nav-item">
            <a href="inventory_suppliers.html" class="nav-link">
              <i class="fa-solid fa-boxes-stacked"></i> Inventory & Suppliers
            </a>
          </li>
          <li class="nav-item">
            <a href="approvals.html" class="nav-link">
              <i class="fa-solid fa-file-signature"></i> Approvals
            </a>
          </li>
          <li class="nav-item">
            <a href="employee_performance.html" class="nav-link">
              <i class="fa-solid fa-users-gear"></i> Employee Performance
            </a>
          </li>
          <li class="nav-item" style="margin-top: auto">
            <a
              href="../index.html"
              class="nav-link logout-btn"
              style="color: #e74c3c"
            >
              <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
          </li>
        </ul>
      </aside>

      <main class="main-content">
        <header class="top-bar">
          <div class="page-title">
            <h1>Business Intelligence</h1>
            <p style="color: var(--secondary)">
              Financial overview and operational metrics.
            </p>
          </div>
          <div class="user-profile">
            <span>Mr. Director (Owner)</span>
            <div class="user-avatar" style="background: #f1c40f; color: #000">
              MD
            </div>
          </div>
        </header>

        <div class="filter-bar">
          <div style="display: flex; gap: 10px; align-items: center">
            <button
              class="btn-glass active"
              style="border-color: var(--primary); color: var(--primary)"
            >
              This Month
            </button>
            <button class="btn-glass">Last Quarter</button>
            <button class="btn-glass">Year to Date</button>

            <div
              style="
                width: 1px;
                height: 30px;
                background: var(--border-glass);
                margin: 0 10px;
              "
            ></div>

            <!-- ✅ Real range label -->
            <div
              id="rangeLabel"
              style="color: var(--secondary); font-size: 0.9rem"
            >
              <i class="fa-regular fa-calendar"></i> Loading...
            </div>
          </div>

          <div class="action-buttons">
            <button id="btnReport" class="btn btn-primary">
              <i class="fa-solid fa-file-pdf"></i> Generate Report
            </button>
          </div>
        </div>

        <div class="dashboard-grid">
          <div class="stat-card">
            <div class="stat-info">
              <!-- ✅ Real Revenue -->
              <h3 id="kpiRevenue" style="color: #64ffda">0.00</h3>
              <p>Total Revenue (YTD)</p>
              <span style="font-size: 0.8rem; color: #2ecc71">
                <i class="fa-solid fa-arrow-trend-up"></i> Live from database
              </span>
            </div>
            <div class="stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <!-- ✅ Real Profit -->
              <h3 id="kpiProfit" style="color: #f1c40f">0.00</h3>
              <p>Net Profit</p>
              <span style="font-size: 0.8rem; color: #2ecc71">
                <i class="fa-solid fa-arrow-trend-up"></i> Live from database
              </span>
            </div>
            <div class="stat-icon"><i class="fa-solid fa-chart-pie"></i></div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <!-- ✅ Real Outstanding -->
              <h3 id="kpiOutstanding" style="color: #e74c3c">0.00</h3>
              <p>Outstanding Invoices</p>
              <!-- ✅ Real overdue count -->
              <span
                id="kpiOverdueText"
                style="font-size: 0.8rem; color: #e74c3c"
                >0 Invoices Overdue</span
              >
            </div>
            <div class="stat-icon">
              <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-info">
              <!-- ✅ Real contracts -->
              <h3 id="kpiContracts">0</h3>
              <p>Active Supplier Contracts</p>
            </div>
            <div class="stat-icon"><i class="fa-solid fa-handshake"></i></div>
          </div>
        </div>

        <div class="dashboard-grid">
          <div
            class="wide-widget"
            style="grid-column: span 3; min-height: 400px"
          >
            <div
              style="
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
              "
            >
              <h3 style="color: white">Revenue Analytics</h3>
              <button class="btn-icon btn-view" type="button">
                <i class="fa-solid fa-expand"></i>
              </button>
            </div>
            <div style="height: 300px; width: 100%">
              <canvas id="revenueChart"></canvas>
            </div>
          </div>

          <div
            class="wide-widget"
            style="grid-column: span 1; min-height: 400px"
          >
            <h3 style="color: white; margin-bottom: 20px">Service Mix</h3>
            <div style="height: 250px; position: relative">
              <canvas id="serviceChart"></canvas>
            </div>
            <div style="margin-top: 20px; text-align: center">
              <p style="color: var(--secondary); font-size: 0.85rem">
                Most profitable: <b id="mostProfitable">Loading...</b>
              </p>
            </div>
          </div>
        </div>

        <div class="wide-widget" style="height: auto; grid-column: span 4">
          <h3 style="color: white; margin-bottom: 15px">
            Recent High-Value Transactions
          </h3>
          <table class="table-responsive">
            <thead>
              <tr>
                <th>Transaction ID</th>
                <th>Date</th>
                <th>Client / Supplier</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>

            <!-- ✅ Real rows inserted here -->
            <tbody id="trxBody"></tbody>
          </table>
        </div>
      </main>
    </div>

    <script src="../js/owner_charts.js"></script>
    <script src="../js/dashboard.js"></script>
  </body>
</html>