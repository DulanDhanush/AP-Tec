# 🏢 AP-Tec Enterprise Control System

![PHP](https://img.shields.io/badge/PHP-Backend-blue)
![MySQL](https://img.shields.io/badge/MySQL-Database-orange)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-yellow)
![License](https://img.shields.io/badge/License-Apache%202.0-green)

## 📌 Project Overview

AP-Tec Enterprise Control System is a web-based enterprise workflow management platform developed using **PHP, MySQL, HTML, CSS, and JavaScript**.

The system centralizes and digitizes internal organizational processes such as:

- Leave Requests
- Purchase Orders
- Contract Renewals
- Administrative Approval Workflows
- Report Generation

🌐 **Live Demo:**  
https://aptecenterprisecontrol.great-site.net/?i=1  

---

## 🎯 Objectives

- Digitize enterprise approval workflows
- Provide centralized monitoring for administrators
- Improve efficiency in request handling
- Maintain structured enterprise records
- Generate reports for operational insights

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
- Monitor enterprise operations in real time
- Centralized workflow control

---

### 📑 Report Generation Module

The system includes a dynamic reporting feature that allows:

- Generating approval reports
- Filtering reports by request type
- Viewing status-based summaries
- Export-ready structured data (based on implementation)

This enhances decision-making and enterprise monitoring capabilities.

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
- Apache (XAMPP compatible)
- Shared hosting compatible

---

## 🗄 Database Schema

**Database Name:** `aptec_db`

### Main Table: `approvals`

| Column        | Description |
|--------------|-------------|
| approval_id  | Primary Key |
| requester_id | Employee/User ID |
| type         | Leave / Purchase Order / Contract Renewal |
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


---

## ⚙️ Installation & Setup

### 1️⃣ Clone Repository

```bash
git clone https://github.com/DulanDhanush/AP-Tec.git
```

Move the project folder into:

2️⃣ Database Setup

Open phpMyAdmin

Create database:aptec_db

Import:aptec_db.sql

3️⃣ Configure Database Connection

Update your database configuration file:

$host = "localhost";
$username = "root";
$password = "";
$database = "aptec_db";

4️⃣ Run the Application

Start Apache and MySQL, then open:
http://localhost/AP-Tec/

🔒 Security Considerations

Current Implementation:

Session-based authentication

Role-based workflow control

Structured ENUM-based status integrity

Recommended Improvements:

Password hashing using password_hash()

Prepared statements (PDO / MySQLi)

CSRF protection

Input validation & sanitization

Activity logging system

🚀 Future Enhancements

Email notifications for approvals

PDF/Excel report export

Dashboard analytics charts

REST API support

MVC architecture implementation

UI modernization (Bootstrap / Tailwind)

📈 Learning Outcomes

This project demonstrates:

Full-stack web development

Enterprise workflow system design

Database schema planning

Role-based access implementation

Report generation logic

Real-world business process modeling

👨‍💻 Developer

Dulan Dhanush Kandeepan
Diploma in Software Engineering – NIBM
Aspiring Junior Software Engineer

GitHub: https://github.com/DulanDhanush

📜 License

This project is licensed under the Apache License 2.0.

You may use, modify, and distribute this software under the terms of the Apache License 2.0.




## 📂 Project Structure

