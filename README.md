# LoeiTech E-Sign System (edoc67)
> **ระบบสารบรรณและลงนามเอกสารอิเล็กทรอนิกส์ วิทยาลัยเทคนิคเลย**

A web-based Electronic Signature and Document Management system designed for educational institutions, specifically Loei Technical College.

## 🌟 Features
- **Document Management**: Upload, track, and manage PDF documents.
- **E-Signing Workflow**: Multi-stage signing process (Sarabun -> Co-Director -> Director).
- **PDF Annotation**: Direct signing and stamping on PDF files using PDF.js and mPDF.
- **Multi-Tenant Support**: Support for multiple institutions within one system.
- **Role-Based Access**: Specialized interfaces for Admin, Sarabun, and Management.

## 🛠 Tech Stack
- **Backend**: PHP 8.1
- **Frontend**: HTML5, TailwindCSS, Bootstrap Icons, PDF.js
- **Database**: MariaDB (MySQL)
- **PDF Processing**: mPDF 8.1.3
- **Containerization**: Docker & Docker Compose

## 🚀 Quick Start (Docker)

### 1. Prerequisites
- Docker and Docker Compose installed on your machine.

### 2. Setup Environment
Copy `.env.example` to `.env` and adjust the database credentials if necessary.
```bash
cp .env.example .env
```

### 3. Run the Application
Start the containers using Docker Compose:
```bash
docker-compose up -d --build
```

### 4. Initialize Database
Import the schema and seed data:
```bash
docker exec -i mariadb_edoc mysql -u esign -pesignpwd e-sign < database/schema.sql
```

### 5. Access the System
- **Application**: [http://localhost:8080](http://localhost:8080)
- **phpMyAdmin**: [http://localhost:8081](http://localhost:8081)

## 🔑 Default Credentials (Seed Data)
| Username | Password | Role |
|---|---|---|
| `admin1` | `admin1234` | SuperAdmin (วิทยาลัยเทคนิคเลย) |
| `admin2` | `admin1234` | SuperAdmin (วิทยาลัยอาชีวศึกษาเลย) |

## 📁 Project Structure
- `assets/`: Frontend assets (Logos, JS helpers).
- `database/`: SQL schema and seed data.
- `docs/`: System analysis and workflow documentation.
- `fonts/`: Thai fonts (TH Sarabun New).
- `uploads/`: Storage for documents and signatures (ignored by Git).
- `vendor/`: Composer dependencies (ignored by Git).

## 📄 License
This project is for internal use at Loei Technical College. Please check the license terms before distributing.

---
*Developed by Loei Technical College Team*
