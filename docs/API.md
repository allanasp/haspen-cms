# API Documentation

Complete REST API reference for the Headless CMS. The API follows a three-tier architecture providing public content delivery, authenticated management operations, and user authentication.

## Table of Contents

- [API Architecture](#api-architecture)
- [Authentication](#authentication)
- [Rate Limiting](#rate-limiting)
- [Content Delivery API](#content-delivery-api)
- [Management API](#management-api)
- [Authentication API](#authentication-api)
- [Error Handling](#error-handling)
- [Examples](#examples)

## API Architecture

### Three-Tier Structure

The API is organized into three distinct tiers:

1. **Content Delivery API** (`/api/v1/cdn/`) - Public content access
2. **Management API** (`/api/v1/spaces/{space_id}/`) - Admin operations
3. **Authentication API** (`/api/v1/auth/`) - User authentication

### Base URLs

- **Development**: `http://localhost:8000/api`
- **Production**: `https://your-domain.com/api`

## Authentication

### JWT Tokens

The API uses JWT (JSON Web Tokens) for authentication. Tokens are obtained through the authentication endpoints and must be included in the `Authorization` header for protected endpoints.

```bash
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Multi-Tenant Access

JWT tokens include space access information. Users can belong to multiple spaces with different roles:

```json
{
  "user": {
    "id": "user-uuid",
    "name": "John Doe",
    "spaces": [
      {
        "id": "space-uuid-1",
        "name": "My Blog",
        "slug": "my-blog",
        "role": "admin"
      },
      {
        "id": "space-uuid-2", 
        "name": "Company Site",
        "slug": "company-site",
        "role": "editor"
      }
    ]
  }
}
```

## Rate Limiting

Different API tiers have different rate limits:

| API Tier | Rate Limit | Description |
|----------|------------|-------------|
| CDN API | 60 requests/minute | Public content delivery |
| Management API | 120 requests/minute | Authenticated operations |
| Authentication API | 10 requests/minute | Security protection |

Rate limit headers are included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1640995200
```

## Content Delivery API

Public API for accessing published content. No authentication required.

### Stories

#### List Published Stories

```http
GET /api/v1/cdn/stories
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Items per page (max: 100, default: 25) |
| `starts_with` | string | Filter by slug prefix |
| `by_slugs` | string | Comma-separated list of slugs |
| `excluding_slugs` | string | Comma-separated list of slugs to exclude |
| `sort_by` | string | Sort field: `created_at`, `published_at`, `name`, `position` |
| `sort_order` | string | Sort direction: `asc`, `desc` |

**Example Request:**

```bash
curl "http://localhost:8000/api/v1/cdn/stories?starts_with=blog/&sort_by=published_at&sort_order=desc"
```

**Example Response:**

```json
{
  "stories": [
    {
      "id": "story-uuid",
      "name": "My Blog Post",
      "slug": "blog/my-blog-post",
      "content": {
        "body": [
          {
            "_uid": "component-uuid",
            "component": "hero",
            "title": "Welcome to My Blog",
            "description": "This is a sample blog post."
          }
        ]
      },
      "published_at": "2024-01-15T10:30:00Z",
      "full_slug": "blog/my-blog-post"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 42,
    "last_page": 2
  }
}
```

#### Get Story by Slug

```http
GET /api/v1/cdn/stories/{slug}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `version` | string | Version to retrieve: `draft`, `published` (default: `published`) |
| `resolve_links` | boolean | Resolve story links in content |
| `resolve_relations` | string | Comma-separated relations to include |

**Example Request:**

```bash
curl "http://localhost:8000/api/v1/cdn/stories/homepage?resolve_relations=parent,children"
```

### Datasources

#### List Datasources

```http
GET /api/v1/cdn/datasources
```

**Example Response:**

```json
{
  "datasources": [
    {
      "id": "datasource-uuid",
      "name": "Products",
      "slug": "products",
      "type": "json",
      "entry_count": 150
    }
  ]
}
```

#### Get Datasource Entries

```http
GET /api/v1/cdn/datasources/{slug}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number |
| `per_page` | integer | Items per page (max: 100) |
| `dimension` | string | Filter by dimension |
| `dimension_value` | string | Filter by dimension value |
| `search` | string | Search entries by name or value |

**Example Request:**

```bash
curl "http://localhost:8000/api/v1/cdn/datasources/products?dimension=category&dimension_value=electronics"
```

### Assets

#### Get Asset with Transformations

```http
GET /api/v1/cdn/assets/{filename}
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `w` | integer | Width for resize (1-4000px) |
| `h` | integer | Height for resize (1-4000px) |
| `fit` | string | Resize fit mode: `crop`, `clip`, `scale` |
| `format` | string | Output format: `webp`, `jpg`, `png` |
| `quality` | integer | Image quality (1-100) |
| `focal` | string | Focal point for cropping (x,y coordinates 0-1) |

**Examples:**

```bash
# Resize and convert to WebP
curl "http://localhost:8000/api/v1/cdn/assets/hero.jpg?w=800&h=600&format=webp&quality=80"

# Crop with focal point
curl "http://localhost:8000/api/v1/cdn/assets/hero.jpg?w=400&h=400&fit=crop&focal=0.3,0.7"
```

#### Get Asset Metadata

```http
GET /api/v1/cdn/assets/{filename}/info
```

**Example Response:**

```json
{
  "asset": {
    "id": "asset-uuid",
    "filename": "hero.jpg",
    "title": "Hero Image",
    "alt": "Beautiful hero image",
    "content_type": "image/jpeg",
    "file_size": 245760,
    "metadata": {
      "width": 1920,
      "height": 1080
    },
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

## Management API

Authenticated API for content management operations. Requires JWT authentication and space access.

### Authentication Required

All management endpoints require authentication:

```bash
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
     "http://localhost:8000/api/v1/spaces/{space_id}/stories"
```

### Stories Management

#### List Stories

```http
GET /api/v1/spaces/{space_id}/stories
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number |
| `per_page` | integer | Items per page (max: 100) |
| `search` | string | Search in story name and slug |
| `status` | string | Filter by status: `draft`, `in_review`, `published`, `scheduled`, `archived` |
| `starts_with` | string | Filter by slug prefix |

#### Create Story

```http
POST /api/v1/spaces/{space_id}/stories
```

**Request Body:**

```json
{
  "story": {
    "name": "My New Story",
    "slug": "my-new-story",
    "content": {
      "body": [
        {
          "_uid": "unique-component-id",
          "component": "hero",
          "title": "Welcome",
          "description": "Hero section content"
        }
      ]
    },
    "status": "draft",
    "meta_title": "SEO Title",
    "meta_description": "SEO description"
  }
}
```

**Response:**

```json
{
  "story": {
    "id": "story-uuid",
    "name": "My New Story",
    "slug": "my-new-story",
    "status": "draft",
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Get Story

```http
GET /api/v1/spaces/{space_id}/stories/{story_id}
```

#### Update Story

```http
PUT /api/v1/spaces/{space_id}/stories/{story_id}
```

**Request Body:**

```json
{
  "story": {
    "name": "Updated Story Name",
    "status": "published",
    "publish_at": "2024-01-20T10:00:00Z"
  }
}
```

#### Delete Story

```http
DELETE /api/v1/spaces/{space_id}/stories/{story_id}
```

### Components Management

#### List Components

```http
GET /api/v1/spaces/{space_id}/components
```

#### Create Component

```http
POST /api/v1/spaces/{space_id}/components
```

**Request Body:**

```json
{
  "component": {
    "name": "Hero Section",
    "internal_name": "hero",
    "schema": {
      "title": {
        "type": "text",
        "required": true,
        "description": "Hero title"
      },
      "description": {
        "type": "textarea",
        "required": false,
        "description": "Hero description"
      },
      "background_image": {
        "type": "asset",
        "required": false,
        "description": "Background image"
      }
    },
    "is_root": true,
    "is_nestable": false,
    "preview_field": "title",
    "icon": "hero",
    "color": "#3b82f6"
  }
}
```

### Assets Management

#### List Assets

```http
GET /api/v1/spaces/{space_id}/assets
```

#### Upload Asset

```http
POST /api/v1/spaces/{space_id}/assets
```

**Request (multipart/form-data):**

```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -F "file=@/path/to/image.jpg" \
  -F "title=Hero Image" \
  -F "alt=Beautiful hero image" \
  -F "folder=images/heroes" \
  "http://localhost:8000/api/v1/spaces/{space_id}/assets"
```

## Authentication API

User authentication and token management.

### Register User

```http
POST /api/v1/auth/register
```

**Request Body:**

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response:**

```json
{
  "user": {
    "id": "user-uuid",
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2024-01-15T10:30:00Z"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "expires_at": "2024-01-15T22:30:00Z"
}
```

### Login User

```http
POST /api/v1/auth/login
```

**Request Body:**

```json
{
  "email": "john@example.com",
  "password": "password123",
  "remember": false
}
```

### Get Current User

```http
GET /api/v1/auth/me
```

**Headers:**
```
Authorization: Bearer YOUR_JWT_TOKEN
```

### Refresh Token

```http
POST /api/v1/auth/refresh
```

### Logout

```http
POST /api/v1/auth/logout
```

### Forgot Password

```http
POST /api/v1/auth/forgot-password
```

**Request Body:**

```json
{
  "email": "john@example.com"
}
```

### Reset Password

```http
POST /api/v1/auth/reset-password
```

**Request Body:**

```json
{
  "email": "john@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123",
  "token": "reset-token-here"
}
```

## Error Handling

The API uses standard HTTP status codes and returns consistent error responses:

### Error Response Format

```json
{
  "error": "Error Type",
  "message": "Human-readable error message",
  "errors": {
    "field": ["Specific field validation errors"]
  }
}
```

### Common Status Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 201 | Created |
| 204 | No Content |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Rate Limit Exceeded |
| 500 | Internal Server Error |

### Example Error Responses

**Validation Error (422):**

```json
{
  "error": "Validation Error",
  "message": "The given data was invalid",
  "errors": {
    "name": ["The name field is required"],
    "email": ["The email must be a valid email address"]
  }
}
```

**Rate Limit Exceeded (429):**

```json
{
  "error": "Rate limit exceeded",
  "message": "Too many requests. Limit: 60 per 60 seconds.",
  "retry_after": 45
}
```

## Examples

### Complete Workflow Example

This example demonstrates creating content from scratch:

```bash
# 1. Register a user
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Content Manager",
    "email": "manager@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'

# Response includes JWT token
export JWT_TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
export SPACE_ID="your-space-uuid"

# 2. Create a component
curl -X POST http://localhost:8000/api/v1/spaces/$SPACE_ID/components \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "component": {
      "name": "Article",
      "internal_name": "article",
      "schema": {
        "title": {"type": "text", "required": true},
        "content": {"type": "richtext", "required": true},
        "featured_image": {"type": "asset", "required": false}
      }
    }
  }'

# 3. Upload an image
curl -X POST http://localhost:8000/api/v1/spaces/$SPACE_ID/assets \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -F "file=@article-image.jpg" \
  -F "title=Article Featured Image" \
  -F "alt=Featured image for article"

# 4. Create a story using the component
curl -X POST http://localhost:8000/api/v1/spaces/$SPACE_ID/stories \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "story": {
      "name": "My First Article",
      "content": {
        "body": [
          {
            "_uid": "article-1",
            "component": "article",
            "title": "Welcome to Our Blog",
            "content": "<p>This is the content of our first article.</p>",
            "featured_image": "article-image.jpg"
          }
        ]
      },
      "status": "draft"
    }
  }'

# 5. Publish the story
curl -X PUT http://localhost:8000/api/v1/spaces/$SPACE_ID/stories/story-uuid \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "story": {
      "status": "published"
    }
  }'

# 6. Access the published content (no auth required)
curl http://localhost:8000/api/v1/cdn/stories/my-first-article
```

### Multi-Tenant Space Resolution

The API supports multiple methods for space resolution:

```bash
# Method 1: URL parameter (recommended for management API)
curl http://localhost:8000/api/v1/spaces/space-uuid/stories

# Method 2: Subdomain (recommended for CDN API)
curl http://my-site.localhost:8000/api/v1/cdn/stories

# Method 3: Header-based
curl -H "X-Space-ID: space-uuid" \
     http://localhost:8000/api/v1/cdn/stories
```

---

For more advanced usage and integration examples, see the [Development Guide](DEVELOPMENT.md).