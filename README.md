# Headless CMS

A modern, API-first headless CMS built with Laravel 11.x, designed for scalability, performance, and developer experience.

## Features

- **API-First Architecture**: Built with Laravel Sanctum for authentication
- **Modern PHP**: PHP 8.3+ with strict typing and modern standards
- **PostgreSQL 16+**: Robust database with advanced features
- **Redis Integration**: Caching and session management
- **Docker Support**: Full containerization with multi-stage builds
- **Code Quality**: PHPStan (level 8), Psalm, and PHP CS Fixer
- **Testing**: PHPUnit with comprehensive test suite
- **CI/CD**: GitHub Actions workflow
- **JWT Authentication**: Secure token-based authentication
- **Rate Limiting**: API protection and abuse prevention
- **Multi-tenant Ready**: Configurable tenant identification
- **CORS Support**: Frontend integration ready

## Requirements

- PHP 8.3+
- PostgreSQL 16+
- Redis 7+
- Composer 2.x
- Docker & Docker Compose (for development)

## Quick Start

### Using Docker (Recommended)

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd headless-cms
   ```

2. **Start the development environment**
   ```bash
   docker-compose up -d
   ```

3. **Install dependencies**
   ```bash
   docker-compose exec app composer install
   ```

4. **Setup environment**
   ```bash
   docker-compose exec app cp .env.example .env
   docker-compose exec app php artisan key:generate
   docker-compose exec app php artisan migrate
   ```

5. **Access the application**
   - API: http://localhost
   - MailHog: http://localhost:8025

### Local Development

1. **Install dependencies**
   ```bash
   composer install
   ```

2. **Setup environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure database and Redis**
   Update `.env` with your PostgreSQL and Redis credentials.

4. **Run migrations**
   ```bash
   php artisan migrate
   ```

5. **Start development server**
   ```bash
   php artisan serve
   ```

## Development Tools

### Code Quality

- **PHP CS Fixer**: PSR-12 compliance with Laravel-specific rules
- **PHPStan**: Static analysis at level 8
- **Psalm**: Additional static analysis with Laravel plugin

```bash
# Run code formatting
./vendor/bin/php-cs-fixer fix

# Run static analysis
./vendor/bin/phpstan analyse
./vendor/bin/psalm
```

### Testing

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

### Pre-commit Hooks

Install pre-commit hooks for automated quality checks:

```bash
# Install pre-commit (requires Python)
pip install pre-commit

# Install hooks
pre-commit install

# Run hooks manually
pre-commit run --all-files
```

## API Documentation

### Authentication

The API uses JWT tokens for authentication:

```bash
# Login
POST /api/auth/login
{
    "email": "user@example.com",
    "password": "password"
}

# Response
{
    "success": true,
    "data": {
        "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "Bearer",
        "expires_in": 3600
    }
}

# Use token in requests
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### Rate Limiting

API endpoints are rate-limited:

- General API: 60 requests per minute
- Authentication: 5 requests per minute

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

## License

This project is licensed under the MIT License.
