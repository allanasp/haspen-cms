# 🚀 Quick Start Guide

This guide will get your Headless CMS up and running in just a few minutes.

## Prerequisites

- **Docker** and **Docker Compose** (recommended) OR
- **PHP 8.3+**, **PostgreSQL 16+**, **Redis 7+**, **Composer 2.x**

## Option 1: Docker Setup (Recommended - 5 minutes)

### 1. Clone and Start
```bash
git clone <repository-url>
cd headless-cms
docker-compose up -d
```

### 2. Install Dependencies
```bash
docker-compose exec app composer install
docker-compose exec app cp .env.example .env
docker-compose exec app php artisan key:generate
```

### 3. Setup Database
```bash
docker-compose exec app php artisan migrate --seed
```

### 4. Test Your Installation
```bash
# Test API is working
curl http://localhost/api/v1/cdn/stories

# Register a test user
curl -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

**🎉 You're done!** Your CMS is running at http://localhost

## Option 2: Local Development Setup

### 1. Install Dependencies
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### 2. Configure Environment
Edit `.env` with your database settings:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=headless_cms
DB_USERNAME=your_username
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 3. Setup Database
```bash
# Create database first in PostgreSQL
createdb headless_cms

# Run migrations and seed data
php artisan migrate --seed

# Start the development server
php artisan serve
```

## What's Included After Setup

### 🎯 Demo Data
- **Admin User**: admin@demo.space (password: password)
- **Demo Space**: "Demo Space" with sample content
- **Sample Components**: Hero, Text, Gallery components
- **Sample Stories**: Blog posts and pages
- **Sample Assets**: Images and documents

### 🔗 Available Endpoints
- **API Base**: http://localhost (Docker) or http://localhost:8000 (local)
- **CDN API**: `/api/v1/cdn/` - Public content delivery
- **Management API**: `/api/v1/spaces/{space_id}/` - Authenticated content management
- **Auth API**: `/api/v1/auth/` - User authentication

### 📧 Development Tools
- **MailHog**: http://localhost:8025 (email testing)
- **Database**: localhost:5432 (PostgreSQL)
- **Redis**: localhost:6379 (caching/sessions)

## Next Steps

### 1. Explore the API
```bash
# Get a JWT token
TOKEN=$(curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@demo.space","password":"password"}' \
  | jq -r '.access_token')

# List all spaces
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost/api/v1/spaces

# Get stories in demo space
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost/api/v1/spaces/{space_id}/stories
```

### 2. Run Tests
```bash
# PHPUnit tests
composer test

# Pest tests (parallel)
composer test:pest-parallel

# All tests
composer test:all

# Code quality
composer quality
```

### 3. Development Workflow
```bash
# Start all development services
composer dev

# Watch for file changes
npm run dev

# Check logs
docker-compose logs -f app  # Docker
php artisan pail           # Local
```

## Common Issues & Solutions

### Docker Issues
```bash
# Reset everything
docker-compose down -v
docker-compose up -d --build

# Check container logs
docker-compose logs app
```

### Database Issues
```bash
# Reset database
php artisan migrate:fresh --seed

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

### Permission Issues (Linux/Mac)
```bash
sudo chown -R $USER:$USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## 📚 Further Reading

- **[API Documentation](docs/API.md)** - Complete API reference
- **[Architecture](docs/ARCHITECTURE.md)** - System design
- **[Development Guide](docs/DEVELOPMENT.md)** - Advanced workflows
- **[Testing Guide](TESTING.md)** - Testing strategies

## 🆘 Need Help?

1. Check the [Documentation](docs/)
2. Review [Common Issues](docs/DEVELOPMENT.md#troubleshooting)
3. Open an issue on GitHub

---

**✨ Happy building with your Headless CMS!**