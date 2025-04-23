# rmap-middleware

A lightweight PHP middleware repository for the RMAP application. It centralizes all API endpoints, configuration, and shared logic (authentication, CORS, logging) in a secure, organized structure.

## 🔧 Features

- **JWT Authentication & Authorization** via `middleware.php`
- **Centralized CORS handling** for all API endpoints
- **Structured directory layout** separating public scripts from application logic
- **Environment-based configuration** using `.env`

## 📁 Directory Structure

```
rmap-middleware/
├── composer.json             # PHP dependencies
├── .env.example              # Template for environment variables
├── README.md
│
├── src/                      # Application code (non-public)
│   ├── config.php            # DB connection & env loading
│   ├── middleware.php        # CORS, JWT auth, logging
│   └── helpers/              # Utility functions (e.g. logger.php)
│
└── public/                   # Document root (served by Apache/Nginx)
    ├── api/                  # API endpoint scripts
    │   ├── login.php
    │   ├── signup.php
    │   ├── inference-api.php
    │   ├── get-reports.php
    │   └── ... etc.
    └── .htaccess             # Apache configuration for public folder
```

## ⚙️ Environment Variables

Copy `.env.example` to `.env` and fill in your credentials:

```ini
DB_HOST=your_db_host
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASS=your_db_pass
JWT_SECRET_KEY=your_jwt_secret
```

## 🚀 Installation

1. Clone this repo:
   ```bash
git clone https://github.com/your-org/rmap-middleware.git
cd rmap-middleware
```  
2. Install PHP dependencies:
   ```bash
composer install
```  
3. Copy `.env.example` ➔ `.env` and configure.
4. Point your web server’s document root to the `public/` folder.

## 🔐 .htaccess (public/.htaccess)

Place this file in `public/` to harden security under Apache:

```apache
# Disable directory listing
Options -Indexes

# Prevent access to files starting with a dot
<FilesMatch "^\.">
  Require all denied
</FilesMatch>

# Rewrite rules (if using front-controller)
# RewriteEngine On
# RewriteCond %{REQUEST_FILENAME} !-f
# RewriteRule ^ index.php [L,QSA]
```

## ☁️ Deployment

- **Apache**: Ensure `AllowOverride All` for the `public/` folder to enable `.htaccess`.
- **Nginx**: Point `root` to `public/` and translate the above rules into `location` blocks.

## 🤝 Contributing

1. Fork this repo
2. Create a feature branch: `git checkout -b feat/your-feature`
3. Commit changes: `git commit -m "feat: description"
4. Push & open a PR

---

Prepared with love by the RMAP development team.
_© 2024 AC Tesla Pte Ltd. All Rights Reserved._

