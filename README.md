# Headless CMS

A modern, API-first headless CMS built with Laravel 11.x, designed for scalability, performance, and developer experience. Inspired by Storyblok's architecture with multi-tenant support and advanced content management capabilities.

## Features

- **🌐 Complete REST API**: Three-tier architecture (CDN, Management, Authentication)
- **🏢 Multi-Tenant**: Space-based isolation with role-based access control
- **🎨 Component System**: Storyblok-style content blocks with JSON schema validation
- **📁 Asset Management**: Advanced file handling with image transformations
- **🔒 Security**: JWT authentication, rate limiting, and comprehensive validation
- **⚡ Performance**: PostgreSQL 16+ with JSONB, Redis caching, Docker optimization

## Requirements

- PHP 8.3+
- PostgreSQL 16+
- Redis 7+
- Composer 2.x
- Docker & Docker Compose (recommended)

## Quick Start

### Option 1: Docker (Recommended)

1. **Clone and start the environment**
   ```bash
   git clone <repository-url>
   cd headless-cms
   docker-compose up -d
   ```

2. **Install dependencies and setup**
   ```bash
   docker-compose exec app composer install
   docker-compose exec app cp .env.example .env
   docker-compose exec app php artisan key:generate
   docker-compose exec app php artisan migrate --seed
   ```

3. **Access the application**
   - API: http://localhost:8000
   - MailHog: http://localhost:8025
   - API Health: http://localhost:8000/api/health

### Option 2: Local Development

1. **Install dependencies**
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Configure environment**
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=headless_cms
   DB_USERNAME=your_username
   DB_PASSWORD=your_password

   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   ```

3. **Setup database**
   ```bash
   php artisan migrate --seed
   php artisan serve
   ```

## API Quick Test

Test the API with a simple request:

```bash
# Check API health
curl http://localhost:8000/api/health

# Register a user
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com", 
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

## Development Commands

```bash
# Run tests
composer test

# Code quality
composer analyse    # PHPStan analysis
composer psalm      # Psalm static analysis  
composer format     # PHP CS Fixer formatting

# Database operations
php artisan migrate:fresh --seed  # Reset with sample data
php artisan db:seed --class=SpaceSeeder  # Add sample spaces

# Docker operations
docker-compose up -d              # Start services
docker-compose exec app bash      # Access container
docker-compose logs app           # View logs
```

## Project Structure

```
headless-cms/
├── app/Http/Controllers/Api/V1/
│   ├── Auth/          # Authentication API
│   ├── Cdn/           # Content Delivery API  
│   └── Management/    # Management API
├── app/Models/        # Eloquent models
├── app/Http/Middleware/ # API middleware
├── database/migrations/ # Database schema
├── docs/             # Documentation
└── docker/           # Docker configuration
```

## Documentation

Comprehensive documentation is available in the [`docs/`](docs/) folder:

- **[API Documentation](docs/API.md)** - Complete REST API reference
- **[Architecture](docs/ARCHITECTURE.md)** - System design and patterns
- **[Database Models](docs/MODELS.md)** - Model relationships and usage
- **[Development Guide](docs/DEVELOPMENT.md)** - Advanced development workflows
- **[Deployment Guide](docs/DEPLOYMENT.md)** - Production deployment instructions

## API Overview

The CMS provides three main API endpoints:

### Content Delivery API (Public)
```bash
GET /api/v1/cdn/stories           # List published stories
GET /api/v1/cdn/stories/{slug}    # Get story by slug
GET /api/v1/cdn/assets/{filename} # Asset delivery with transformations
```

### Management API (Authenticated)
```bash
GET /api/v1/spaces/{id}/stories   # Manage stories
GET /api/v1/spaces/{id}/components # Manage components
GET /api/v1/spaces/{id}/assets    # Manage assets
```

### Authentication API
```bash
POST /api/v1/auth/login    # User login
POST /api/v1/auth/register # User registration
GET  /api/v1/auth/me       # Current user
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests and quality checks (`composer test && composer analyse`)
4. Commit your changes (`git commit -m 'Add amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## Support

- **Documentation**: [`docs/`](docs/) folder
- **Issues**: GitHub Issues
- **Discussions**: GitHub Discussions

---

**Next Steps**: Check out the [API Documentation](docs/API.md) to start building with the headless CMS!