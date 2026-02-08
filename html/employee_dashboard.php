<?php
declare(strict_types=1);
require_once __DIR__ . "/../php/auth.php";
$u = require_login(["Employee"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Workspace | AP Tec</title>
    
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
                    <a href="employee_dashboard.php" class="nav-link active">
                        <i class="fa-solid fa-clipboard-list"></i> My Tasks
                    </a>
                </li>
                <li class="nav-item">
                    <a href="invoice_generator.html" class="nav-link">
                        <i class="fa-solid fa-file-invoice"></i> Generate Invoice
                    </a>
                </li>
                <li class="nav-item">
                    <a href="schedule.php" class="nav-link">
                        <i class="fa-solid fa-calendar-check"></i> Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a href="messages.php" class="nav-link">
                        <i class="fa-solid fa-comments"></i> Messages <span class="tab-badge" style="background:var(--primary); color:black;">2</span>
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
                    <h1>Technician Workspace</h1>
                    <p style="color: var(--secondary);">Manage your assignments and update field status.</p>
                </div>
                
                <div style="display: flex; gap: 20px; align-items: center;">
                    <button id="availBtn" class="availability-toggle" style="cursor: pointer;">
                        <div class="status-dot"></div>
                        <span id="statusText" style="font-size: 0.85rem; color: #2ecc71; font-weight: 600;">Available</span>
                    </button>

               <span><?= htmlspecialchars($u["full_name"] ?: $u["username"]) ?></span>
                </div>
            </header>

            <div class="dashboard-grid">
                
                <div style="grid-column: span 3;">
                    
                    <div class="tab-container">
                        <button class="tab-btn active" data-filter="all">All Tasks</button>
                        <button class="tab-btn" data-filter="pending">Pending</button>
                        <button class="tab-btn" data-filter="progress">In Progress</button>
                        <button class="tab-btn" data-filter="completed">Completed</button>
                    </div>

                    <div id="taskGrid" class="task-grid">
                        
                        <div class="task-card priority-high" data-status="pending">
                            <div class="task-header">
                                <span class="task-id">#TSK-1024</span>
                                <span class="status-badge" style="background: rgba(231,76,60,0.1); color: #e74c3c;">Urgent</span>
                            </div>
                            <h3 style="color: white; font-size: 1.1rem; margin-bottom: 5px;">Server Overheating</h3>
                            <p style="color: var(--secondary); font-size: 0.9rem; margin-bottom: 15px;">
                                <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> Alpha Corp HQ, Room 3B
                            </p>
                            <p style="color: var(--text-dim); font-size: 0.85rem;">
                                Client reported random shutdowns. Bring thermal paste and diagnostic kit.
                            </p>
                            <div class="task-meta">
                                <span><i class="fa-regular fa-clock"></i> Due: Today, 2 PM</span>
                                <button class="btn-icon btn-edit btn-update-task" title="Update Status"><i class="fa-solid fa-pen-to-square"></i></button>
                            </div>
                        </div>

                        <div class="task-card priority-med" data-status="progress">
                            <div class="task-header">
                                <span class="task-id">#TSK-1025</span>
                                <span class="status-badge status-pending">In Progress</span>
                            </div>
                            <h3 style="color: white; font-size: 1.1rem; margin-bottom: 5px;">Printer Maintenance</h3>
                            <p style="color: var(--secondary); font-size: 0.9rem; margin-bottom: 15px;">
                                <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> Beta Industries
                            </p>
                            <div class="task-meta">
                                <span><i class="fa-regular fa-clock"></i> Due: Tomorrow</span>
                                <button class="btn-icon btn-edit btn-update-task"><i class="fa-solid fa-pen-to-square"></i></button>
                            </div>
                        </div>

                        <div class="task-card priority-low" data-status="completed">
                            <div class="task-header">
                                <span class="task-id">#TSK-1020</span>
                                <span class="status-badge status-active">Done</span>
                            </div>
                            <h3 style="color: white; font-size: 1.1rem; margin-bottom: 5px;">Toner Replacement</h3>
                            <p style="color: var(--secondary); font-size: 0.9rem; margin-bottom: 15px;">
                                <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> City Library
                            </p>
                            <div class="task-meta">
                                <span><i class="fa-solid fa-check"></i> Finished 10:00 AM</span>
                                <button class="btn-icon btn-view"><i class="fa-solid fa-file-invoice"></i></button>
                            </div>
                        </div>

                    </div>
                </div>

                <div style="grid-column: span 1;">
                    
                    <div class="wide-widget" style="height: auto; margin-bottom: 20px;">
                        <h3 style="color: white; margin-bottom: 20px; font-size: 1.1rem;">Today's Route</h3>
                        
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-dot active"></div>
                                <div class="timeline-time">09:00 AM</div>
                                <div class="timeline-content">
                                    <div style="color: white; font-weight: 600;">Check-in</div>
                                    <div style="color: var(--secondary); font-size: 0.8rem;">Head Office</div>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-time">10:30 AM</div>
                                <div class="timeline-content">
                                    <div style="color: white; font-weight: 600;">Alpha Corp</div>
                                    <div style="color: var(--secondary); font-size: 0.8rem;">Repair Job</div>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-time">02:00 PM</div>
                                <div class="timeline-content">
                                    <div style="color: white; font-weight: 600;">Beta Ind.</div>
                                    <div style="color: var(--secondary); font-size: 0.8rem;">Maintenance</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    

                </div>

            </div>
        </main>
    </div>

    <div class="modal-overlay" id="taskModal">
        <div class="modal-content">
            <i class="fa-solid fa-xmark close-modal"></i>
            
            <div class="modal-header">
                <h2 id="modalTaskTitle">Server Overheating</h2>
                <p style="color: var(--secondary);">Updating Task ID: <span id="modalTaskId" style="color: var(--primary);">#TSK-1024</span></p>
            </div>
            
            <form>
                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Status</label>
                    <select class="modal-input">
                        <option>Pending</option>
                        <option>In Progress</option>
                        <option>Completed</option>
                        <option>Waiting for Parts</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Technician Notes</label>
                    <textarea class="modal-input" rows="4" placeholder="Describe work done or issues found..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" style="color: var(--secondary);">Proof of Work</label>
                    <div style="border: 2px dashed var(--border-glass); padding: 15px; text-align: center; border-radius: 6px; cursor: pointer; background: rgba(0,0,0,0.2);" onclick="document.getElementById('proofUpload').click()">
                        <i class="fa-solid fa-camera" style="color: var(--secondary); font-size: 1.5rem; margin-bottom: 5px;"></i>
                        <p style="font-size: 0.8rem; color: var(--secondary);">Click to upload photo</p>
                        <div id="proofPreview" style="margin-top: 5px; font-size: 0.8rem;"></div>
                    </div>
                    <input type="file" id="proofUpload" style="display: none;" accept="image/*">
                </div>

                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="button" class="btn btn-cancel" style="flex: 1;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Update Task</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/employee.js"></script>
    <script src="../js/dashboard.js"></script>
</body>
</html>