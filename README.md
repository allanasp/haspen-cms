# Headless CMS

A modern, API-first headless CMS built with Laravel 11.x, designed for scalability, performance, and developer experience. Inspired by Storyblok's architecture with multi-tenant support and advanced content management capabilities.

## Features

- **🌐 Complete REST API**: Three-tier architecture (CDN, Management, Authentication)
- **🏢 Multi-Tenant**: Space-based isolation with role-based access control
- **🎨 Component System**: Storyblok-style content blocks with JSON schema validation
- **📁 Asset Management**: Advanced file handling with image transformations
- **🔒 Security**: JWT authentication, rate limiting, and comprehensive validation
- **⚡ Performance**: PostgreSQL 16+ with JSONB, Redis caching, Docker optimization
- **🧪 Testing**: PHPUnit & Pest frameworks with comprehensive test coverage
- **🔍 Code Quality**: PHPStan Level 8 static analysis and Psalm integration

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
   - API: http://localhost (via Nginx)
   - MailHog: http://localhost:8025
   - Database: localhost:5432 (PostgreSQL)
   - Redis: localhost:6379

### Option 2: Local Development

1. **Install dependencies**
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Configure environment** (PostgreSQL is the default database)
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
# Register a user
curl -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com", 
    "password": "password123",
    "password_confirmation": "password123"
  }'

# Login to get a token
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'

# Access CDN content (no auth required)
curl http://localhost/api/v1/cdn/stories
```

## Development Commands

```bash
# Run tests (both PHPUnit and Pest)
composer test                 # PHPUnit tests
./vendor/bin/pest            # Pest tests
./vendor/bin/pest --parallel # Parallel Pest tests

# Code quality
./vendor/bin/phpstan analyse # PHPStan Level 8 analysis
./vendor/bin/psalm          # Psalm static analysis  
./vendor/bin/pint          # Laravel Pint formatting

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
GET /api/v1/cdn/datasources/{slug}/entries # Get datasource entries
```

### Management API (Authenticated)
```bash
GET /api/v1/spaces/{id}/stories     # Manage stories
GET /api/v1/spaces/{id}/components  # Manage components
GET /api/v1/spaces/{id}/assets      # Manage assets
GET /api/v1/spaces/{id}/datasources # Manage datasources
GET /api/v1/spaces/{id}/users       # Manage users
GET /api/v1/spaces/{id}/roles       # Manage roles
GET /api/v1/spaces/{id}/settings    # Space settings
```

### Authentication API
```bash
POST /api/v1/auth/login    # User login
POST /api/v1/auth/register # User registration
GET  /api/v1/auth/me       # Current user
```

## Testing

The project includes comprehensive test coverage with both PHPUnit and Pest frameworks:

```bash
# Run all PHPUnit tests
composer test
./vendor/bin/phpunit

# Run all Pest tests  
./vendor/bin/pest
./vendor/bin/pest --parallel  # Run in parallel for speed

# Run specific test groups
./vendor/bin/pest --group=pest-demo
./vendor/bin/phpunit --group=story-management

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
./vendor/bin/pest --coverage

# Code quality checks
./vendor/bin/phpstan analyse  # PHPStan Level 8
./vendor/bin/psalm           # Psalm static analysis
./vendor/bin/pint            # Laravel Pint code formatting
```

### Test Structure
- **Unit Tests**: Model validation, service logic, component behavior
- **Feature Tests**: API endpoints, authentication, integration flows
- **Story Management Tests**: Content locking, templates, advanced search, translations

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Run tests and quality checks (`composer test && ./vendor/bin/pest && ./vendor/bin/phpstan analyse`)
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