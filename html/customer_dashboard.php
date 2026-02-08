<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal | AP Tec</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div class="sidebar-brand">
                <i class="fa-solid fa-layer-group"></i> AP TEC.
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="customer_dashboard.html" class="nav-link active">
                        <i class="fa-solid fa-gauge-high"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customer_orders.html" class="nav-link">
                        <i class="fa-solid fa-box-open"></i> My Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customer_invoices.html" class="nav-link">
                        <i class="fa-solid fa-file-invoice"></i> Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customer_support.html" class="nav-link">
                        <i class="fa-solid fa-headset"></i> Support
                    </a>
                </li>
                 <li class="nav-item" style="margin-top: auto;">
                    <a href="../index.html" class="nav-link logout-btn" style="color: #e74c3c;">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            
            <header class="top-bar">
                <div class="page-title">
                    <h1>My Dashboard</h1>
                    <p style="color: var(--secondary);">Welcome back, Alpha Corp HQ.</p>
                </div>
                
                <div style="display: flex; gap: 15px; align-items: center;">
                    <button id="btnNewRequest" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i> New Request
                    </button>
                    <div class="user-profile">
                        <span>Alpha Corp</span>
                        <div class="user-avatar" style="background: #8e44ad;">AC</div>
                    </div>
                </div>
            </header>

            <div class="dashboard-grid">
                
                <div class="wide-widget" style="grid-column: span 4; height: auto; min-height: 250px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h3 style="color: white;">Active Repair: <span style="color: var(--primary);">Server Maintenance (Room 3B)</span></h3>
                        <span class="status-badge status-pending" style="font-size: 0.9rem;">Ticket #9921</span>
                    </div>
                    <p style="color: var(--secondary); font-size: 0.9rem; margin-bottom: 30px;">Technician Assigned: John Smith</p>

                    <div class="track-container">
                        <div class="track-progress-bar"></div> <div class="step-item completed">
                            <div class="step-circle"><i class="fa-solid fa-check"></i></div>
                            <div class="step-text">Request Received</div>
                        </div>
                        
                        <div class="step-item completed">
                            <div class="step-circle"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                            <div class="step-text">Diagnostics</div>
                        </div>
                        
                        <div class="step-item active">
                            <div class="step-circle"><i class="fa-solid fa-gears"></i></div>
                            <div class="step-text">Repairing</div>
                        </div>
                        
                        <div class="step-item">
                            <div class="step-circle"><i class="fa-solid fa-flag-checkered"></i></div>
                            <div class="step-text">Completed</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3 style="color: #64ffda;">$450.00</h3>
                        <p>Total Due</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                </div>

                <div class="stat-card">
                    <div class="stat-info">
                        <h3>2</h3>
                        <p>Active Orders</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
                </div>

                <div class="wide-widget" style="grid-column: span 2; height: auto;">
                    <h3 style="color: white; margin-bottom: 15px;">Recent Activity</h3>
                    <table class="table-responsive">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Service / Item</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-mono">ORD-001</td>
                                <td>HP Toner (x5)</td>
                                <td>Feb 05</td>
                                <td><span class="status-badge status-active">Delivered</span></td>
                                <td><button class="btn-glass" style="padding: 5px 10px; font-size: 0.8rem;">Invoice</button></td>
                            </tr>
                            <tr>
                                <td class="font-mono">REP-102</td>
                                <td>Printer Fix</td>
                                <td>Jan 28</td>
                                <td><span class="status-badge status-active">Completed</span></td>
                                <td><button class="btn-glass" style="padding: 5px 10px; font-size: 0.8rem;">Invoice</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

    <div class="modal-overlay" id="requestModal">
        <div class="modal-content">
            <i class="fa-solid fa-xmark close-modal"></i>
            
            <div class="modal-header">
                <h2>Request Service</h2>
                <p style="color: var(--secondary);">Describe the issue or select items to order.</p>
            </div>
            
            <form>
                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Request Type</label>
                    <select class="modal-input">
                        <option>Repair / Maintenance</option>
                        <option>Order Supplies</option>
                        <option>Software Support</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Device / Item Name</label>
                    <input type="text" class="modal-input" placeholder="e.g. Main Hall Printer">
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Description of Issue</label>
                    <textarea class="modal-input" rows="4" placeholder="It's making a loud noise when printing..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Preferred Date</label>
                    <input type="date" class="date-input" style="width: 100%; background: rgba(0,0,0,0.2); color: white;">
                </div>

                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="button" class="btn btn-cancel" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/customer.js"></script>
    <script src="../js/dashboard.js"></script>
    <script src="../js/customer_dashboard.js"></script>
</body>
</html>