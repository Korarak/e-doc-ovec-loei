# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Project Is

LoeiTech E-Sign — a multi-role electronic document signing and routing system for Thai educational institutions. Users upload PDFs, which flow through a 3-stage approval workflow: **Sarabun → Co-Director → Director**, with each role attaching a digital signature/stamp at a specific position on the PDF.

## Running the Application

```bash
# Start all services (PHP/Apache app, MariaDB, phpMyAdmin)
docker-compose up -d --build

# Import the database schema (first run only)
docker exec -i mariadb_edoc mysql -u esign -pesignpwd e-sign < database/schema.sql
```

- App: http://localhost:8080 (or `APP_PORT` from `.env`)
- phpMyAdmin: http://localhost:8081 (or `PMA_PORT`)

Copy `.env.example` to `.env` and fill in passwords before first run.

**Default credentials** (seeded via schema.sql):
- `admin1` / `admin1234` — SuperAdmin, Institution 1
- `admin2` / `admin1234` — SuperAdmin, Institution 2

There are no native test or lint commands — changes are tested by reloading the app in the browser.

## Architecture

### Backend (PHP 8.1 + Apache)

- **`edoc-db.php`** — MySQLi connection (reads env vars) + shared helpers (`formatThaiDate()`)
- **`auth_check.php`** — Session auth guard + CSRF token validation; included at top of every protected page
- **`base.php`** / **`base_sidebarmenu.php`** — Shared HTML layout/sidebar included by each page
- Each page is a self-contained PHP file handling both display and POST logic

### Role System

Five roles control access (`role_id` in session):

| Role | `role_id` | Responsibilities |
|------|-----------|-----------------|
| SuperAdmin | 99 | Manages institutions, all users |
| Admin | 1 | Uploads documents, manages users within institution |
| Sarabun | 2 | First-stage review, stamps documents |
| Co-Director | 3 | Second-stage signing |
| Director | 4 | Final approval/signing |

Helper functions: `is_director()`, `is_codirector()`, `is_sarabun()`, `require_role()`, `require_superadmin()` — all defined in `edoc-db.php`.

### Document Workflow

1. Admin uploads PDF(s) → row created in `documents` + `document_files`
2. `sign_doc` table tracks per-document workflow state (`sign_sarabun`, `sign_codirector`, `sign_director`, `doc_status`)
3. Each signing role uses their `*_sign.php` page: the user positions a signature/stamp on the PDF via PDF.js canvas, then submits coordinates
4. Coordinates + signature image path saved to `sign_detail` (x_pos, y_pos, page_num)
5. `*_generate_*.php` files use **mPDF** to stamp/embed signatures and produce a final signed PDF

### Database Schema

Key tables:
- `documents` + `document_files` — document metadata and file paths
- `sign_doc` — one row per document tracking overall workflow status
- `sign_detail` — one row per signing event (who, when, where on page, which image)
- `user_signatures` — stored signature images per user
- `institution` — multi-tenant support; `inst_id` scopes all queries

### Session Variables

Every page relies on: `$_SESSION['user_id']`, `$_SESSION['role_id']`, `$_SESSION['inst_id']`, `$_SESSION['fullname']`, `$_SESSION['inst_name']`.

### Frontend

- TailwindCSS + Bootstrap Icons (both via CDN)
- DataTables (jQuery) for document lists with Thai localization
- SweetAlert2 for confirmations/alerts
- PDF.js (v2.5) for in-browser PDF viewing and signature placement
- No build step — all assets served from CDN or `assets/`

## Key Technical Notes

- **Passwords are MD5-hashed** — do not expand this pattern; migrate to `password_hash()` when touching auth
- **File uploads** go to `uploads/` (documents) and `uploads/signatures/` (signature images); 64 MB limit set in Dockerfile
- **CSRF** tokens are validated on every POST via `auth_check.php` — always include `csrf_token` in forms
- **Thai locale**: charset is utf8mb4 throughout; timezone is Asia/Bangkok; fonts are Kanit/Sarabun (Google Fonts)
- `api/` directory contains AJAX endpoints called by the frontend JS (e.g., `extract_pdf_info.php`, `save_signature.php`)
- `tmp/` is used for mPDF intermediate files
