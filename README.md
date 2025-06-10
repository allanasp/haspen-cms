# Headless CMS

A modern, API-first headless CMS built with Laravel 11.x, designed for scalability, performance, and developer experience. Inspired by Storyblok's architecture with multi-tenant support and advanced content management capabilities.

## Features

### 🏗️ **Core Architecture**
- **API-First Design**: Built with Laravel Sanctum and JWT authentication
- **Multi-Tenant Support**: Complete space-based isolation with role-based access control
- **Modern PHP**: PHP 8.3+ with strict typing and modern standards
- **PostgreSQL 16+**: Advanced JSONB fields with GIN indexes for optimal performance
- **Redis Integration**: Caching, sessions, and queue management

### 🎨 **Content Management (Storyblok-Style)**
- **Component System**: Flexible, reusable content blocks with JSON schema validation
- **Visual Editor Ready**: Component schemas with field types, validation, and UI configuration
- **Story Hierarchy**: Nested content structure with breadcrumbs and path management
- **Multi-Language**: Built-in localization with translation groups
- **Publishing Workflow**: Draft → Review → Published states with scheduling

### 📁 **Asset Management**
- **Advanced File Handling**: Image processing, optimization, and variant generation
- **CDN Integration**: Built-in support for content delivery networks
- **Smart Organization**: Folders, tags, and custom metadata fields
- **Deduplication**: SHA-256 hashing prevents duplicate uploads
- **Usage Analytics**: Track downloads, views, and content relationships

### 🔗 **Data Sources**
- **External Integration**: JSON, CSV, API, and database connectors
- **Auto-Synchronization**: Scheduled data fetching with health monitoring
- **Multi-Dimensional Data**: Complex categorization and filtering
- **Data Transformation**: Built-in processing and computed fields

### 🛡️ **Security & Performance**
- **Row Level Security**: PostgreSQL RLS-ready for tenant isolation
- **JWT Authentication**: Stateless, secure token-based authentication
- **Rate Limiting**: Configurable API protection with Redis backend
- **CORS Support**: Frontend integration ready with flexible policies
- **Input Validation**: Comprehensive request validation and sanitization

### 🚀 **Developer Experience**
- **Docker Support**: Full containerization with multi-stage builds
- **Code Quality**: PHPStan (level 8), Psalm, and PHP CS Fixer
- **Testing**: PHPUnit with model factories and comprehensive seeders
- **CI/CD**: GitHub Actions workflow with automated testing and deployment
- **API Documentation**: JSON schema examples and comprehensive guides

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

4. **Run migrations and seed data**
   ```bash
   php artisan migrate --seed
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

## Database Schema

The CMS features a comprehensive database schema designed for scalability and performance:

### Core Tables
- **`spaces`** - Multi-tenant isolation with environment configurations
- **`users`** & **`roles`** - Advanced user management with role-based permissions
- **`space_user`** - Pivot table linking users to spaces with custom permissions
- **`components`** - Storyblok-style content block definitions with JSON schemas
- **`stories`** - Hierarchical content pages with multi-language support
- **`assets`** - Advanced file management with processing metadata
- **`datasources`** & **`datasource_entries`** - External data integration

### Key Features
- **PostgreSQL 16+ Features**: JSONB columns with GIN indexes for optimal JSON querying
- **Multi-Tenant Architecture**: Complete data isolation with space-based scoping
- **Soft Deletes**: Comprehensive audit trails for important entities
- **UUID Exposure**: Public API uses UUIDs instead of internal IDs
- **Rich Indexing**: Performance-optimized indexes for common query patterns

For detailed schema documentation and JSON examples, see [`docs/json-schemas.md`](docs/json-schemas.md).

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

### Storyblok-Style Content API

Content is structured using components, similar to Storyblok:

```bash
# Get a story
GET /api/v1/spaces/{space_uuid}/stories/{story_uuid}

# Response
{
    "story": {
        "uuid": "story-uuid-here",
        "name": "Homepage",
        "slug": "homepage",
        "content": {
            "component": "page",
            "body": [
                {
                    "_uid": "component-uuid",
                    "component": "hero_section", 
                    "title": "Welcome to Our Site",
                    "background_image": {
                        "id": 12345,
                        "filename": "https://cdn.example.com/hero.jpg",
                        "alt": "Hero background"
                    }
                }
            ]
        },
        "published_at": "2024-06-10T09:30:00Z"
    }
}
```

### Multi-Tenant API Structure

All API endpoints are scoped to spaces for multi-tenant isolation:

```bash
# Space-scoped endpoints
GET    /api/v1/spaces/{space_uuid}/stories
POST   /api/v1/spaces/{space_uuid}/stories
GET    /api/v1/spaces/{space_uuid}/assets
POST   /api/v1/spaces/{space_uuid}/assets
GET    /api/v1/spaces/{space_uuid}/components
POST   /api/v1/spaces/{space_uuid}/components
```

### Rate Limiting

API endpoints are rate-limited:

- General API: 60 requests per minute
- Authentication: 5 requests per minute  
- Asset uploads: 10 requests per minute

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
```

## Project Structure

```
headless-cms/
├── app/
│   ├── Http/
│   │   ├── Controllers/         # API controllers
│   │   ├── Middleware/          # Custom middleware (rate limiting, etc.)
│   │   └── Resources/           # API response resources
│   ├── Models/                  # Eloquent models with relationships
│   ├── Repositories/            # Data access layer
│   ├── Services/                # Business logic (JWT, content management)
│   └── Traits/                  # Reusable functionality (API responses)
├── database/
│   ├── migrations/              # Database schema migrations
│   ├── seeders/                 # Database seeders (roles, sample data)
│   └── factories/               # Model factories for testing
├── docker/                      # Docker configuration
│   ├── nginx/                   # Nginx configuration
│   ├── php/                     # PHP configuration  
│   └── supervisor/              # Process management
├── docs/                        # Documentation
│   └── json-schemas.md          # JSON schema examples
├── routes/
│   └── api.php                  # API routes
└── tests/                       # Test suite
```

## Configuration Examples

### Environment Variables

```env
# Multi-tenant configuration
TENANT_IDENTIFICATION=domain
TENANT_MODEL=App\\Models\\Tenant
TENANT_DATABASE_PREFIX=tenant_

# JWT Authentication
JWT_SECRET=your-secret-key
JWT_TTL=3600
JWT_REFRESH_TTL=1209600

# Content Management
DEFAULT_LANGUAGE=en
SUPPORTED_LANGUAGES=en,es,fr,de

# Asset Management  
ASSET_STORAGE_DISK=s3
CDN_URL=https://cdn.yourdomain.com
IMAGE_OPTIMIZATION_ENABLED=true
MAX_UPLOAD_SIZE=50MB

# API Configuration
API_RATE_LIMIT=60
AUTH_RATE_LIMIT=5
UPLOAD_RATE_LIMIT=10
```

### Docker Environment

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f app

# Run artisan commands
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed

# Access container shell
docker-compose exec app bash

# Stop services
docker-compose down
```

## Use Cases

### 🌐 **Multi-Channel Publishing**
Perfect for organizations that need to publish content across:
- Websites and web applications
- Mobile applications (iOS/Android)  
- Progressive Web Apps (PWA)
- Digital signage and IoT devices
- Third-party integrations and syndication

### 🏢 **Enterprise Content Management**
Ideal for businesses requiring:
- Multi-brand content management
- Team collaboration with role-based permissions
- Workflow management (draft → review → publish)
- Scalable content delivery with CDN integration
- API-driven integrations with existing systems

### 🚀 **Modern Web Development**
Excellent choice for:
- JAMstack applications (Gatsby, Next.js, Nuxt.js)
- Single Page Applications (SPA)
- Server-Side Rendering (SSR) with frameworks
- Static site generation
- Headless e-commerce platforms

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Configure PostgreSQL with connection pooling
- [ ] Setup Redis cluster for high availability
- [ ] Configure CDN for asset delivery
- [ ] Enable SSL/TLS with Let's Encrypt or commercial certificates
- [ ] Setup monitoring (Laravel Telescope, New Relic, etc.)
- [ ] Configure automated backups
- [ ] Setup log aggregation (ELK stack, Papertrail, etc.)
- [ ] Configure queue workers with Supervisor
- [ ] Enable OPcache and Redis for optimal performance

### Container Orchestration

```yaml
# docker-compose.prod.yml example
version: '3.8'
services:
  app:
    image: headless-cms:latest
    environment:
      - APP_ENV=production
      - DB_HOST=postgres-cluster
      - REDIS_HOST=redis-cluster
    deploy:
      replicas: 3
      
  nginx:
    image: nginx:alpine
    ports:
      - "443:443"
    volumes:
      - ./ssl:/etc/ssl/certs
```

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes with tests
4. Run quality checks: `pre-commit run --all-files`
5. Commit your changes: `git commit -am 'Add amazing feature'`
6. Push to the branch: `git push origin feature/amazing-feature`
7. Open a Pull Request

### Development Guidelines

- Follow PSR-12 coding standards
- Maintain PHPStan level 8 compliance
- Write comprehensive tests for new features
- Update documentation for API changes
- Use conventional commit messages

## Support & Community

- **Issues**: [GitHub Issues](https://github.com/your-org/headless-cms/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-org/headless-cms/discussions)
- **Documentation**: [Full Documentation](https://docs.yourdomain.com)
- **API Reference**: [API Docs](https://api-docs.yourdomain.com)

## Roadmap

### v1.0 (Current)
- ✅ Multi-tenant architecture
- ✅ Storyblok-style component system
- ✅ Advanced asset management
- ✅ External data source integration

### v1.1 (Next)
- 🔄 GraphQL API support
- 🔄 Real-time collaboration
- 🔄 Advanced workflow management
- 🔄 Plugin system

### v1.2 (Future)
- 📋 Visual content editor
- 📋 Advanced analytics dashboard
- 📋 AI-powered content suggestions
- 📋 Advanced caching strategies

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Inspired by [Storyblok](https://www.storyblok.com/) for content management architecture
- Built with [Laravel](https://laravel.com/) for robust backend framework
- Powered by [PostgreSQL](https://www.postgresql.org/) for advanced database features
- Containerized with [Docker](https://www.docker.com/) for consistent deployment
