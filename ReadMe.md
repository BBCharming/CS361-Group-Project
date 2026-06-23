# Zatcher 🛡️
### Zambian Cyber Incident Analysis & Fraud Detection Platform

> A web-based cybersecurity platform designed to collect, analyse, and expose mobile money scam patterns across Zamtel, Airtel, and MTN networks in Zambia.

---

## 📌 Overview

Zatcher is a full-stack web application built for **CS361 — Introduction to Internet Web Technologies** at Copperbelt University. It enables victims of cyber fraud to report incidents, empowers technical analysts to investigate and upload evidence, and gives regulatory and law enforcement bodies (ZICTA & Zambian Police Service) structured, exportable intelligence to act on.

The platform integrates AI-assisted scam pattern recognition via **LADINA** (an intelligent analysis engine) to extract structured threat intelligence from uploaded screenshots and documents.

---

## 🎯 Core Features

| Feature | Description |
|---|---|
| Incident Reporting | Victims submit mobile money scam reports via validated web forms |
| Live Case Tracking | AJAX-powered 30-second polling shows real-time case status |
| AI Evidence Analysis | LADINA extracts threat data from screenshots and PDFs via OCR |
| Analyst Dashboard | Technical analysts log findings, IPs, and upload evidence dossiers |
| ZICTA Audit Panel | Regulators verify analysts and audit all system activity |
| Police XML Export | Law enforcement exports verified evidence as `evidence_report.xml` |
| Scam Analytics | Chart.js dashboard visualises the most prevalent scam types in Zambia |

---

## 🏗️ System Architecture

```
Client Browser (HTML5 / CSS3 / JavaScript)
        │
        │  HTTP / AJAX
        ▼
PHP Application Server
  ├── Auth & Sessions (RBAC)
  ├── Incident CRUD Controller
  ├── Investigation Controller
  ├── Evidence Upload Handler
  └── XML Report Generator
        │
        │  PDO Prepared Statements
        ▼
MySQL Database (InnoDB · utf8mb4)
  ├── users
  ├── incidents
  ├── investigations
  └── evidence_files
```

---

## 🗄️ Database Schema

Four normalized, related MySQL tables:

- **`users`** — Authentication and role management (Victim / Analyst / Police / ZICTA)
- **`incidents`** — Submitted fraud reports with status tracking
- **`investigations`** — Analyst-assigned case files with technical findings
- **`evidence_files`** — Uploaded evidence metadata with checksum integrity verification

---

## 👥 User Roles

| Role | Capabilities |
|---|---|
| **Victim** | Submit reports, track case status |
| **Zatcher Analyst** | Analyse incidents, upload evidence, log findings |
| **ZICTA** | Authorize analysts, audit system logs, view analytics |
| **Zambian Police Service** | Access verified evidence, export XML case files |

---

## 🛠️ Tech Stack

**Frontend:** HTML5, CSS3 (Grid/Flexbox, SOC dark theme), JavaScript (ES6+), Chart.js  
**Backend:** PHP 8+ with PDO prepared statements  
**Database:** MySQL 8 (InnoDB, utf8mb4)  
**AI Engine:** Python · Ollama (phi4-mini) · LADINA persona · Tesseract OCR  
**Data Exchange:** XML (Police interoperability export)  
**Security:** `password_hash()` · PDO parameterized queries · `$_SESSION` RBAC

---

## 🔐 Security Measures

- All passwords hashed with PHP `password_hash()` (bcrypt)
- Every database query uses **PDO Prepared Statements** — zero raw SQL
- **Role-Based Access Control** via `$_SESSION['role']` — victims cannot access police pages
- File uploads restricted to `.jpg`, `.pdf`, `.txt` only (validated client + server side)
- Evidence integrity verified via SHA-256 checksum on every uploaded file

---

## 📂 Project Structure

```
CS361-Group-Project/
├── index.php                  # Landing page + login
├── config/
│   └── db.php                 # PDO connection
├── auth/
│   ├── login.php
│   └── logout.php
├── victim/
│   ├── report.php             # Incident submission form
│   └── dashboard.php          # AJAX case tracker
├── analyst/
│   ├── dashboard.php          # Investigation panel
│   └── upload_evidence.php
├── police/
│   ├── evidence.php           # Verified reports viewer
│   └── export_xml.php         # XML generator
├── zicta/
│   ├── audit.php
│   └── analytics.php          # Chart.js scam stats
├── ai/
│   └── ZatcherAnalyzer.py     # LADINA AI engine
├── assets/
│   ├── css/style.css
│   └── js/
│       ├── validate.js        # Phone/file regex validation
│       ├── ajax-poll.js       # 30s status polling
│       └── charts.js          # Chart.js dashboard
├── schema/
│   └── zatcher_schema.sql     # Full DB schema
└── ReadMe.md
```

---

## 👨‍💻 Team

| Member | Module |
|---|---|
| Member 1 | Database schema · PHP Auth · RBAC · ZICTA verification |
| Member 2 | Victim portal · JS validation · AJAX polling · Victim dashboard CSS |
| Member 3 | Analyst dashboard · Evidence upload · PHP CRUD · Chart.js analytics |
| Member 4 | Police portal · XML generator · SOC theme · Technical report |

---

## 📋 Course Information

**Course:** CS361 — Introduction to Internet Web Technologies  
**Institution:** Copperbelt University, Kitwe, Zambia  
**Project:** Group Assignment — Full-Stack Web Application  

---

*Zatcher — Built to expose scammers. Powered by LADINA.*
