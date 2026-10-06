# SaaS Keuangan Keluarga — Production & Operations Guide

A multi-tenant SaaS application for family financial management built with Laravel 12 (REST API) and React (SPA).

---

## 1. System Overview & Architecture

### Backend Stack
- **Framework**: Laravel 12 (PHP 8.2+)
- **Authentication**: Laravel Sanctum (Stateful session / Bearer Token)
- **Database**: MySQL / MariaDB (UTF8MB4)
- **Multi-Tenancy**: Household-based data isolation via `household_id` scoping
- **Authorization**: Global `super_admin` role vs Household `owner` / `member` roles

### Frontend Stack
- **Framework**: React 18 + Vite
- **Styling**: Tailwind CSS / Vanilla CSS
- **HTTP Client**: Axios with automatic Bearer Token injection & 401 handling
- **Routing**: React Router DOM (SPA)

---

## 2. Environment Configuration

### Backend Environment (`.env`)
Required variables for production deployment:

```env
APP_NAME="SaaS Keuangan Keluarga"
APP_ENV=production
APP_KEY=base64:... # Generate via `php artisan key:generate`
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saas_keuangan_prod
DB_USERNAME=saas_user
DB_PASSWORD=your_secure_db_password

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.yourdomain.com

SANCTUM_STATEFUL_DOMAINS=app.yourdomain.com,yourdomain.com
CORS_ALLOWED_ORIGINS=https://app.yourdomain.com,https://yourdomain.com

FILESYSTEM_DISK=local
CACHE_STORE=database
QUEUE_CONNECTION=database
```

### Frontend Environment (`frontend/.env`)
```env
VITE_API_URL=https://api.yourdomain.com/api/v1
```

---

## 3. Deployment Procedure

### Backend Deployment Steps
1. Clone repository to web root (e.g. `/var/www/saas-keuangan`).
2. Run production Composer installation:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Set file permissions:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```
4. Copy `.env.example` to `.env` and fill in production secrets.
5. Generate application key (if not set):
   ```bash
   php artisan key:generate --force
   ```
6. Run database migrations and seeds safely:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=PlanSeeder --force
   ```
7. Cache configuration, routes, and views:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
8. Set up Supervisor for background queues (if applicable):
   ```ini
   [program:saas-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/saas-keuangan/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/var/www/saas-keuangan/storage/logs/worker.log
   ```

### Frontend Deployment Steps
1. Navigate to `frontend/`:
   ```bash
   cd frontend
   npm ci
   ```
2. Build production bundle:
   ```bash
   VITE_API_URL=https://api.yourdomain.com/api/v1 npm run build
   ```
3. Serve the output `dist/` directory via web server (Nginx / Apache).
4. Configure SPA route fallbacks in web server:
   - **Nginx**:
     ```nginx
     location / {
         try_files $uri $uri/ /index.html;
     }
     ```
   - **Apache (`.htaccess` in web root)**:
     ```apache
     <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteBase /
       RewriteRule ^index\.html$ - [L]
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteCond %{REQUEST_FILENAME} !-d
       RewriteRule . /index.html [L]
     </IfModule>
     ```

---

## 4. Database & Seeding Safety

- **Production Migration Rule**: NEVER run `migrate:fresh` or `migrate:reset` on production database.
- **Seeder Safety**: `PlanSeeder` uses `updateOrCreate()` to safely initialize subscription tiers without deleting existing subscription or plan data.
- **Pre-migration Backup**: Always run a full database export prior to executing schema updates:
  ```bash
  mysqldump -u saas_user -p saas_keuangan_prod > backup_pre_migration_$(date +%Y%m%m_%H%M%S).sql
  ```

---

## 5. Production Backup & Disaster Recovery Strategy

### Automated Daily Backup Schedule (Cron Job)
```cron
# Daily DB Backup at 02:00 AM
0 2 * * * mysqldump -u saas_user -p'password' saas_keuangan_prod | gzip > /backups/db/saas_db_$(date +\%Y\%m\%d).sql.gz

# Retention Policy Clean Up (Keep 30 days)
0 3 * * * find /backups/db/ -type f -name "*.sql.gz" -mtime +30 -delete
```

### Restore Procedure
1. Put application into maintenance mode:
   ```bash
   php artisan down --secret="maintenance-bypass-key"
   ```
2. Restore database backup:
   ```bash
   gunzip -c /backups/db/saas_db_YYYYMMDD.sql.gz | mysql -u saas_user -p saas_keuangan_prod
   ```
3. Clear application cache:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan optimize
   ```
4. Bring application back online:
   ```bash
   php artisan up
   ```

---

## 6. Super Admin & System Operations

### Initial Super Admin Creation
To promote a user to `super_admin`, run Tinker command in production environment:
```bash
php artisan tinker
```
```php
$user = User::where('email', 'admin@yourdomain.com')->first();
$user->role = 'super_admin';
$user->save();
```

### Super Admin Operations (`/admin`)
- **User Management**: View users, reset passwords, audit roles.
- **Plan Management**: Create, update, or deprecate subscription packages.
- **Subscription Control**: Override tenant subscriptions, adjust billing dates.
- **Activity Audit Logs**: Inspect system-wide audit logs.

---

## 7. Production Smoke Test Verification Checklist

When deploying to live infrastructure, run the following verification steps:

- [ ] **Authentication**: Register new user, log in, verify token generation, retrieve `/api/v1/me`, test logout.
- [ ] **Tenant Isolation**: Create 2 distinct households; verify User A cannot access User B's accounts, transactions, or budgets (returns 403/404).
- [ ] **Transactions & Accounts**: Create account, log income transaction, log expense transaction, create transfer between accounts; check balance accuracy.
- [ ] **Reports & Analytics**: Generate monthly cashflow, category breakdown, and savings reports.
- [ ] **Subscriptions**: Verify plan quotas enforced (e.g. member limit, account limit).
- [ ] **Admin Security**: Attempt accessing `/api/v1/admin/*` endpoints as regular member (must return 403 Forbidden). Access as `super_admin` (must return 200 OK).

---

## 8. Known Limitations & Specifications

1. **No External Payment Gateway Integration**: Subscriptions are handled via internal system logic and Super Admin control (no active Stripe/Midtrans dependency).
2. **File Storage**: System uses standard database storage and local file logging; image upload features are not required or configured.
3. **Infrastructure Prerequisite**: Deployment requires external server (Linux VPS / Cloud), domain name, SSL certificate (Let's Encrypt / Cloudflare), and production MySQL instance.

---

## 9. Handover Sign-Off Checklist

- [x] Backend unit & integration test suite passing (420 tests / 1226 assertions).
- [x] Frontend production asset compilation verified (`dist/` directory generated cleanly).
- [x] All 22 database migrations validated and tested for zero-downtime execution.
- [x] `.env.example` scrubbed of all secrets and populated with production-safe defaults.
- [x] Multi-tenancy household isolation enforced at controller and database query layers.
- [x] Super Admin global authorization isolated from household scoping.
- [x] System documentation, deployment guide, and backup strategies completed.

