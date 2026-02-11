# 🏢 AP-Tec Enterprise Control System

![PHP](https://img.shields.io/badge/PHP-Backend-blue)
![MySQL](https://img.shields.io/badge/MySQL-Database-orange)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-yellow)
![License](https://img.shields.io/badge/License-Apache%202.0-green)

## 📌 Project Overview
**AP-Tec Enterprise Control System** is a web-based workflow management platform developed using **PHP, MySQL, HTML, CSS, and JavaScript**.

The system digitizes and centralizes internal enterprise processes such as leave requests, purchase approvals, assign techninician and contract renewals. It provides administrators with a unified dashboard to monitor and control organizational workflows.

🌐 **Live Demo:**  
https://aptecenterprisecontrol.great-site.net/?i=1  

---

## 🎥 Demo Video
Watch the system in action:  



https://github.com/user-attachments/assets/2ab9935d-d454-4fe3-9ef9-7f0c52385989


---

## 🎯 Project Objectives
- Digitize enterprise approval workflows
- Provide centralized administrative control
- Improve request handling efficiency
- Maintain structured organizational records
- Generate operational and analytical reports
- Overview the system
- Make a interconnected system

---

## ✨ Core Features

### 🔐 Authentication & Access Control
- Secure login system
- Role-based access (Admin / Staff)
- Session-based authentication

---

### 📄 Approval Workflow System
Supports multiple request types:

- Leave Requests
- Purchase Orders
- Contract Renewals
- Message with assigned technician with in the system

Each request includes:
- Requester ID
- Request Type
- Description
- Amount (if applicable)
- Status (Pending / Approved / Rejected)
- Reviewer Information
- Timestamp tracking

---

### 📊 Administrative Dashboard
- View all submitted requests
- Filter by status
- Real-time operational monitoring
- Centralized workflow control

---

### 📑 Report Generation & Export
- Generate approval reports
- Filter by request type
- Status-based summaries
- Export reports (PDF or structured output)

---

### 📈 Analytical Charts
- Visual representation of request data
- Status-based analytics
- Operational insights for administrators
- Dashboard-level performance metrics

---

### 📜 Activity Logging System
- Tracks important system actions
- Logs user activities and system events
- Helps with auditing and monitoring
- Improves accountability and traceability

---

## 🛠 Technology Stack

### Frontend
- HTML5
- CSS3
- JavaScript

### Backend
- Core PHP

### Database
- MySQL / MariaDB

### Server Compatibility
- Apache (XAMPP)
- Shared hosting environments
- Infintyfree

---

## 🗄 Database Schema

**Database Name:** `aptec_db`

### Main Table: `approvals`

| Column        | Description |
|--------------|-------------|
| approval_id  | Primary Key |
| requester_id | Employee/User ID |
| type         | Request type (Leave / Purchase / Contract) |
| details      | Request description |
| amount       | Monetary value (if applicable) |
| status       | Pending / Approved / Rejected |
| reviewed_by  | Admin reviewer |
| created_at   | Timestamp |

---

AP-Tec/
│ 

├── css/

├── js/

├── html/

├── php/

├── uploads/

├── backups/

├── index.html

├── style.css

└── aptec_db.sql


## 📁 Project Structure


---

## ⚙️ Installation & Setup

### 1️⃣ Clone Repository
```bash
git clone https://github.com/DulanDhanush/AP-Tec.git
```

### Move the folder into:

htdocs/   (XAMPP)

### 2️⃣ Database Setup

- Open phpMyAdmin

- Create database:
aptec_db

- Import:

aptec_db.sql
### 3️⃣ Configure Database Connection

- Update your database config file:

$host = "localhost";

$username = "root";

$password = "";

$database = "aptec_db";


### 4️⃣ Run the Application

- Start Apache and MySQL, then open:

http://localhost/AP-Tec/

## 🔒 Security Considerations

### Current Implementation

- Session-based authentication

- Role-based workflow control

- Structured status handling

- Activity logging system

### Recommended Improvements

- CSRF protection

- Input validation & sanitization



## 🚀 Future Enhancements

- Email notifications for approvals

- REST API support

- MVC architecture refactor

- UI modernization (Bootstrap / Tailwind)

- Mobile-responsive optimization

## 📈 Learning Outcomes

### This project demonstrates:

- Full-stack web development

- Enterprise workflow system design

- Database schema planning

- Role-based access implementation

- Report generation and export

- Data analytics visualization

- Activity logging and auditing

- Real-world business process modeling

## 👨‍💻 Developer

Dulan Dhanush Kandeepan

-- Portfolio:
https://dulandhanush.github.io/portfolio-website/

-- GitHub:
https://github.com/DulanDhanush

## 📜 License

This project is licensed under the Apache License 2.0.
