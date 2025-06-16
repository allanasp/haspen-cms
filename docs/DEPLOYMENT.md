# Deployment Guide

Production deployment instructions for the Headless CMS, covering infrastructure setup, configuration, and deployment strategies.

## Table of Contents

- [Infrastructure Requirements](#infrastructure-requirements)
- [Environment Setup](#environment-setup)
- [Database Configuration](#database-configuration)
- [Application Deployment](#application-deployment)
- [Security Configuration](#security-configuration)
- [Performance Optimization](#performance-optimization)
- [Monitoring & Logging](#monitoring--logging)
- [Backup & Recovery](#backup--recovery)

## Infrastructure Requirements

### Minimum Requirements

- **CPU**: 2 vCPUs
- **Memory**: 4 GB RAM
- **Storage**: 20 GB SSD (excluding media storage)
- **Network**: 1 Gbps connection

### Recommended Production Setup

- **Application Servers**: 2+ instances (for high availability)
- **Database**: PostgreSQL 16+ (managed service recommended)
- **Cache**: Redis 7+ (managed service recommended)
- **Load Balancer**: Nginx or cloud load balancer
- **CDN**: CloudFlare, AWS CloudFront, or similar
- **File Storage**: AWS S3, Google Cloud Storage, or equivalent

### Technology Stack

```bash
# Application Stack
- PHP 8.3+ with strict typing and advanced features
- Laravel 11.x with latest features
- PostgreSQL 16+ (primary database with JSONB, GIN indexes, full-text search)
- Redis 7+ (caching, sessions, queues)
- Nginx 1.20+ (reverse proxy and static file serving)

# Testing & Quality
- PHPUnit & Pest testing frameworks for comprehensive testing
- PHPStan Level 8 static analysis for type safety
- Psalm for additional type checking and code quality
- Automated testing pipeline with parallel execution

# Infrastructure
- Docker and Docker Compose for development and deployment
- Kubernetes (optional, for large scale deployments)
- CI/CD pipeline (GitHub Actions, GitLab CI, etc.)
- Monitoring and logging infrastructure
```

## Environment Setup

### Production Environment Variables

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_KEY=your-32-character-secret-key
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=your-postgres-host
DB_PORT=5432
DB_DATABASE=headless_cms_prod
DB_USERNAME=headless_cms_user
DB_PASSWORD=your-secure-password

# Redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379
REDIS_CLIENT=predis

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# JWT
JWT_SECRET=your-jwt-secret-key
JWT_TTL=60
JWT_REFRESH_TTL=20160

# File Storage
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-s3-bucket
AWS_USE_PATH_STYLE_ENDPOINT=false

# CDN
CDN_URL=https://cdn.your-domain.com

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="Headless CMS"

# Monitoring
LOG_CHANNEL=stack
LOG_LEVEL=info

# Rate Limiting
API_RATE_LIMIT_CDN=60
API_RATE_LIMIT_MANAGEMENT=120
API_RATE_LIMIT_AUTH=10

# Security
CORS_ALLOWED_ORIGINS=https://your-frontend-domain.com
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,OPTIONS
```

### SSL/TLS Configuration

```nginx
# Nginx SSL configuration
server {
    listen 443 ssl http2;
    server_name api.your-domain.com;

    ssl_certificate /path/to/ssl/certificate.crt;
    ssl_certificate_key /path/to/ssl/private.key;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_session_tickets off;

    # Modern configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header Strict-Transport-Security "max-age=63072000" always;
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";

    # Laravel application
    root /var/www/headless-cms/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name api.your-domain.com;
    return 301 https://$server_name$request_uri;
}
```

## Database Configuration

### PostgreSQL 16+ Setup

PostgreSQL 16+ is the recommended and default database for all environments.

```sql
-- Create database and user
CREATE DATABASE headless_cms_prod WITH ENCODING 'UTF8' LC_COLLATE 'en_US.UTF-8' LC_CTYPE 'en_US.UTF-8';
CREATE USER headless_cms_user WITH PASSWORD 'your-secure-password';

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE headless_cms_prod TO headless_cms_user;
GRANT ALL ON SCHEMA public TO headless_cms_user;

-- Enable required extensions for advanced features
\c headless_cms_prod;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";      -- UUID generation
CREATE EXTENSION IF NOT EXISTS "pg_trgm";        -- Trigram matching for fuzzy search
CREATE EXTENSION IF NOT EXISTS "btree_gin";      -- GIN indexes for JSONB
CREATE EXTENSION IF NOT EXISTS "btree_gist";     -- Additional indexing capabilities
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements"; -- Query performance monitoring (optional)
```

### Database Optimization

```sql
-- PostgreSQL configuration for production
-- /etc/postgresql/16/main/postgresql.conf

# Memory settings
shared_buffers = 1GB
effective_cache_size = 3GB
work_mem = 16MB
maintenance_work_mem = 256MB

# Checkpoints
checkpoint_completion_target = 0.9
wal_buffers = 16MB

# Query optimization
random_page_cost = 1.1
effective_io_concurrency = 200

# Logging
log_min_duration_statement = 1000
log_statement = 'mod'
log_line_prefix = '%t [%p]: [%l-1] user=%u,db=%d,app=%a,client=%h '
```

### Database Migrations

```bash
# Run migrations in production
php artisan migrate --force

# Run seeders (only for initial setup)
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=SpaceSeeder --force
```

## Application Deployment

### Manual Deployment

```bash
# 1. Clone repository
git clone https://github.com/your-org/headless-cms.git
cd headless-cms

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Configure environment
cp .env.production .env
php artisan key:generate

# 4. Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Run migrations
php artisan migrate --force

# 6. Set permissions
chown -R www-data:www-data /var/www/headless-cms
chmod -R 755 /var/www/headless-cms/storage
chmod -R 755 /var/www/headless-cms/bootstrap/cache
```

### Docker Deployment

```dockerfile
# Production Dockerfile
FROM php:8.3-fpm-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    redis \
    nginx \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql redis opcache

# Configure PHP for production
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini

# Copy application
COPY . /var/www/html
WORKDIR /var/www/html

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Optimize Laravel
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan event:cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

### CI/CD Pipeline (GitHub Actions)

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.3
          extensions: pdo_pgsql, redis
          
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist
        
      - name: Run tests
        run: php artisan test
        
      - name: Run static analysis
        run: composer analyse

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v3
      
      - name: Deploy to production
        uses: appleboy/ssh-action@v0.1.5
        with:
          host: ${{ secrets.HOST }}
          username: ${{ secrets.USERNAME }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            cd /var/www/headless-cms
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php artisan migrate --force
            php artisan config:cache
            php artisan route:cache
            php artisan view:cache
            php artisan queue:restart
            sudo systemctl reload php8.3-fpm
            sudo systemctl reload nginx
```

## Security Configuration

### Firewall Setup

```bash
# UFW configuration
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### Application Security

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [
        'https://your-frontend-domain.com',
        'https://admin.your-domain.com'
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

### Rate Limiting

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
];

// Apply rate limiting in routes
Route::middleware(['throttle:api'])->group(function () {
    // API routes
});
```

### Security Headers

```nginx
# Additional security headers in Nginx
add_header Referrer-Policy "strict-origin-when-cross-origin";
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';";
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()";
```

## Performance Optimization

### PHP-FPM Configuration

```ini
; /etc/php/8.3/fpm/pool.d/www.conf
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 1000

; Resource limits
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 30
php_admin_value[max_input_time] = 30
```

### OPcache Configuration

```ini
; /etc/php/8.3/mods-available/opcache.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=20000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
opcache.save_comments=1
```

### Redis Configuration

```conf
# /etc/redis/redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
tcp-keepalive 300
timeout 0
```

### Application Caching

```php
// Optimize Laravel caching
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

// Clear caches when needed
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Monitoring & Logging

### Log Configuration

```php
// config/logging.php
'channels' => [
    'production' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
    ],
    
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'info',
        'days' => 14,
    ],
    
    'api' => [
        'driver' => 'daily',
        'path' => storage_path('logs/api.log'),
        'level' => 'info',
        'days' => 30,
    ],
    
    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Laravel Log',
        'emoji' => ':boom:',
        'level' => 'critical',
    ],
],
```

### Health Checks

```php
// routes/api.php
Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version'),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'redis' => Redis::ping() ? 'connected' : 'disconnected'
    ]);
});
```

### Application Monitoring

```bash
# Install Laravel Telescope for debugging (development only)
composer require laravel/telescope --dev

# Install Laravel Horizon for queue monitoring
composer require laravel/horizon
php artisan horizon:install
```

### System Monitoring

```yaml
# docker-compose.monitoring.yml
version: '3.8'
services:
  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./monitoring/prometheus.yml:/etc/prometheus/prometheus.yml

  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin

  node-exporter:
    image: prom/node-exporter
    ports:
      - "9100:9100"
```

## Backup & Recovery

### Database Backup

```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/database"
DB_NAME="headless_cms_prod"
DB_USER="headless_cms_user"

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
pg_dump -h localhost -U $DB_USER -d $DB_NAME | gzip > $BACKUP_DIR/backup_$DATE.sql.gz

# Keep only last 30 days
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete

# Upload to S3 (optional)
aws s3 cp $BACKUP_DIR/backup_$DATE.sql.gz s3://your-backup-bucket/database/
```

### File System Backup

```bash
#!/bin/bash
# backup-files.sh

DATE=$(date +%Y%m%d_%H%M%S)
APP_DIR="/var/www/headless-cms"
BACKUP_DIR="/backups/files"

# Create backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
    --exclude="$APP_DIR/vendor" \
    --exclude="$APP_DIR/node_modules" \
    --exclude="$APP_DIR/storage/logs" \
    $APP_DIR

# Upload to S3
aws s3 cp $BACKUP_DIR/files_$DATE.tar.gz s3://your-backup-bucket/files/
```

### Automated Backup with Cron

```bash
# crontab -e
# Daily database backup at 2 AM
0 2 * * * /usr/local/bin/backup.sh

# Weekly file backup on Sundays at 3 AM
0 3 * * 0 /usr/local/bin/backup-files.sh
```

### Recovery Procedures

```bash
# Database recovery
gunzip -c backup_YYYYMMDD_HHMMSS.sql.gz | psql -h localhost -U headless_cms_user -d headless_cms_prod

# File recovery
tar -xzf files_YYYYMMDD_HHMMSS.tar.gz -C /

# Application recovery steps
cd /var/www/headless-cms
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart php8.3-fpm nginx
```

### Zero-Downtime Deployment

```bash
#!/bin/bash
# deploy.sh - Zero downtime deployment script

APP_DIR="/var/www/headless-cms"
RELEASES_DIR="/var/www/releases"
DATE=$(date +%Y%m%d_%H%M%S)
RELEASE_DIR="$RELEASES_DIR/$DATE"

# Create release directory
mkdir -p $RELEASE_DIR

# Clone latest code
git clone https://github.com/your-org/headless-cms.git $RELEASE_DIR

# Install dependencies
cd $RELEASE_DIR
composer install --no-dev --optimize-autoloader

# Copy environment file
cp $APP_DIR/.env $RELEASE_DIR/.env

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Update symlink (atomic operation)
ln -nfs $RELEASE_DIR $APP_DIR

# Restart services
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx

# Cleanup old releases (keep last 5)
cd $RELEASES_DIR && ls -t | tail -n +6 | xargs rm -rf

echo "Deployment completed successfully"
```

---

For advanced development workflows, see the [Development Guide](DEVELOPMENT.md).