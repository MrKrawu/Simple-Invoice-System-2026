<div align="center">

<img src="images/EGM.png" alt="Simple Invoice System" width="120">

# 🧾 Simple Invoice System

**Create, manage and print invoices as PDF — a lightweight PHP + MySQL web app with user roles.**

<p>
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL%2FMariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white">
  <img alt="Bootstrap" src="https://img.shields.io/badge/Bootstrap-4.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white">
  <img alt="jQuery" src="https://img.shields.io/badge/jQuery-3.4-0769AD?style=for-the-badge&logo=jquery&logoColor=white">
</p>
<p>
  <img alt="Security" src="https://img.shields.io/badge/security-hardened-2ea44f?style=flat-square&logo=shield&logoColor=white">
  <img alt="Passwords" src="https://img.shields.io/badge/passwords-bcrypt-2ea44f?style=flat-square">
  <img alt="SQL" src="https://img.shields.io/badge/SQL-prepared%20statements-2ea44f?style=flat-square">
  <img alt="CSRF" src="https://img.shields.io/badge/CSRF-protected-2ea44f?style=flat-square">
</p>

[✨ Features](#-features) · [🚀 Quick start](#-quick-start) · [⚙️ Configuration](#️-configuration) · [🔐 Security](#-security) · [🗂 Structure](#-project-structure)

</div>

---

## ✨ Features

| | |
|---|---|
| 📊 **Dashboard** | Totals for invoices, users, admins and income, plus the 5 latest invoices |
| 🧾 **Invoices** | Create, edit and delete invoices with multiple line items, tax and notes |
| 📄 **PDF export** | Print or download any invoice as a PDF (FPDF) |
| 🔎 **Smart tables** | Server-side search, sorting and paging via DataTables |
| 👥 **User management** | Admins add, edit, activate/deactivate users and reset passwords |
| 🔑 **Roles** | `Admin` and `User` — the user area is admin-only |
| 👤 **Account settings** | Everyone can edit their profile and change their password |

## 🚀 Quick start

**Requirements:** PHP 8.x (tested with 8.3) with `mysqli`, MySQL or MariaDB, any web server (Apache, nginx, or PHP's built-in server).

```bash
# 1. Get the code
git clone https://github.com/MrKrawu/Simple-Invoice-System-2026.git
cd Simple-Invoice-System-2026

# 2. Create the database and demo data
mysql -u root -p < database/egm_simple_invoice.sql

# 3. Point the app at your database (see Configuration below)
export SIS_DB_USER=invoice_app
export SIS_DB_PASS='a-strong-password'

# 4. Run it
php -S 127.0.0.1:8000
```

Open <http://127.0.0.1:8000/login.html> and sign in:

| Email | Password | Role |
|---|---|---|
| `egavilanmedia@gmail.com` | `password` | Admin |

> ⚠️ These are **demo credentials**. Change the password right after the first login and delete the sample users before going live.
> Old SHA-1 hashes from the demo data are upgraded to bcrypt automatically on first login.

## ⚙️ Configuration

Database settings come from environment variables — no code changes needed.

| Variable | Default | Meaning |
|---|---|---|
| `SIS_DB_HOST` | `localhost` | Database host |
| `SIS_DB_USER` | `root` | Database user |
| `SIS_DB_PASS` | *(empty)* | Database password |
| `SIS_DB_NAME` | `egm_simple_invoice` | Database name |

For production, create a dedicated database user with rights on this one database only instead of using `root`:

```sql
CREATE USER 'invoice_app'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON egm_simple_invoice.* TO 'invoice_app'@'localhost';
```

## 🔐 Security

This fork hardens the original project:

- 🛡️ **Prepared statements** for every database query (no SQL injection); sort columns are whitelisted
- 🔒 **bcrypt** password hashing with automatic migration of legacy SHA-1 hashes
- 🚪 **Authentication on every endpoint**, admin check on user management
- 🧷 **CSRF token** required on all state-changing requests
- 🍪 **Hardened session cookies** (`HttpOnly`, `SameSite=Lax`, `Secure` over HTTPS), session ID regenerated on login
- ⏱️ **Login throttling** after repeated failures
- 🧼 **Output escaping** against XSS, server-side input validation, minimum password length of 8
- 🧹 Removed an unauthenticated legacy endpoint (`profile_action.php`)
- 🐘 **PHP 8 compatible** (patched the bundled FPDF 1.81)

Still recommended: serve over HTTPS and keep `database/` away from the public web root.

## 🗂 Project structure

```
Simple-Invoice-System/
├── bootstrap.php          # session, auth, CSRF, password + DB helpers
├── connection.php         # database connection (env-configurable)
├── login.html             # sign-in page  →  check_login.php
├── index.php              # dashboard     →  admin_action.php
├── invoice.php            # invoice list  →  item_action.php
├── create_invoice.php     # new invoice
├── update_invoice.php     # edit invoice
├── print_invoice.php      # PDF output (FPDF)
├── user.php               # user management (admin)  →  user_action.php
├── account.php            # own profile & password   →  account_action.php
├── include/               # shared header and footer
├── database/              # SQL schema + demo data
├── css/  images/  vendor/ # assets and third-party libraries
```

## 🧰 Built with

[PHP](https://www.php.net/) · [MySQL](https://www.mysql.com/) · [Bootstrap 4.3](https://getbootstrap.com/) · [jQuery 3.4](https://jquery.com/) · [DataTables 1.10](https://datatables.net/) · [Font Awesome 5](https://fontawesome.com/) · [FPDF](http://www.fpdf.org/)

## 🙏 Credits

Original project by [EGavilan Media](http://www.egavilanmedia.com) — [EGavilan-Media/Simple-Invoice-System](https://github.com/EGavilan-Media/Simple-Invoice-System). This fork adds security hardening and PHP 8 support.

<div align="center">

⭐ If this is useful to you, give the repo a star.

</div>
