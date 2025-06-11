# API Documentation

This document provides comprehensive API documentation for the headless CMS, including endpoint specifications, authentication, and usage examples.

## Table of Contents

- [Authentication](#authentication)
- [Multi-Tenant API Structure](#multi-tenant-api-structure)
- [Core Endpoints](#core-endpoints)
- [Response Formats](#response-formats)
- [Error Handling](#error-handling)
- [Rate Limiting](#rate-limiting)
- [SDKs and Examples](#sdks-and-examples)

---

## Authentication

The headless CMS uses Laravel Sanctum for API authentication with token-based access control.

### Getting an API Token

```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}
```

**Response:**
```json
{
    "data": {
        "user": {
            "id": "123e4567-e89b-12d3-a456-426614174000",
            "name": "John Doe",
            "email": "user@example.com"
        },
        "token": "1|abc123def456...",
        "expires_at": "2024-12-01T00:00:00Z"
    }
}
```

### Using API Tokens

Include the token in the Authorization header:

```http
Authorization: Bearer 1|abc123def456...
```

### Space-Based Authentication

Users can have different permissions in different spaces. The API automatically scopes data based on the space context.

```http
GET /api/spaces/my-blog/stories
Authorization: Bearer 1|abc123def456...
```

---

## Multi-Tenant API Structure

All content endpoints are scoped to a specific space for multi-tenant isolation.

### URL Structure

```
/api/spaces/{space}/[endpoint]
```

Where `{space}` can be:
- Space slug: `/api/spaces/my-blog/stories`
- Space UUID: `/api/spaces/123e4567-e89b-12d3-a456-426614174000/stories`

### Space Context

The API automatically sets the space context based on the URL, ensuring all queries are properly scoped.

```http
# All stories in 'my-blog' space
GET /api/spaces/my-blog/stories

# All components in 'company-website' space
GET /api/spaces/company-website/components
```

---

## Core Endpoints

### Spaces

#### List User Spaces
```http
GET /api/spaces
```

**Response:**
```json
{
    "data": [
        {
            "id": "123e4567-e89b-12d3-a456-426614174000",
            "name": "My Blog",
            "slug": "my-blog",
            "domain": "blog.example.com",
            "plan": "pro",
            "status": "active",
            "languages": ["en", "fr"],
            "created_at": "2024-01-01T00:00:00Z"
        }
    ]
}
```

#### Get Space Details
```http
GET /api/spaces/{space}
```

#### Update Space
```http
PUT /api/spaces/{space}
Content-Type: application/json

{
    "name": "Updated Blog Name",
    "description": "A blog about technology and innovation",
    "settings": {
        "theme": "dark",
        "cache_ttl": 7200
    }
}
```

### Stories

#### List Stories
```http
GET /api/spaces/{space}/stories
```

**Query Parameters:**
- `status`: Filter by status (`published`, `draft`, `review`)
- `language`: Filter by language code
- `parent_id`: Filter by parent story
- `is_folder`: Filter folders (`true`/`false`)
- `page`: Pagination page number
- `per_page`: Items per page (max 100)

```http
GET /api/spaces/my-blog/stories?status=published&language=en&page=1&per_page=20
```

**Response:**
```json
{
    "data": [
        {
            "id": "456e7890-e89b-12d3-a456-426614174001",
            "name": "Getting Started with Laravel",
            "slug": "getting-started-with-laravel",
            "status": "published",
            "language": "en",
            "is_folder": false,
            "path": "/blog/getting-started-with-laravel",
            "content": {
                "component": "page",
                "body": [...]
            },
            "meta_title": "Getting Started with Laravel - My Blog",
            "meta_description": "Learn the basics of Laravel framework",
            "published_at": "2024-01-15T10:00:00Z",
            "created_at": "2024-01-14T15:30:00Z",
            "updated_at": "2024-01-15T09:45:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 87
    }
}
```

#### Get Single Story
```http
GET /api/spaces/{space}/stories/{story}
```

#### Create Story
```http
POST /api/spaces/{space}/stories
Content-Type: application/json

{
    "name": "New Blog Post",
    "slug": "new-blog-post",
    "status": "draft",
    "language": "en",
    "content": {
        "component": "page",
        "body": [
            {
                "_uid": "123e4567-e89b-12d3-a456-426614174000",
                "component": "hero_section",
                "title": "Welcome to My New Post",
                "subtitle": "This is an exciting announcement"
            }
        ]
    },
    "meta_title": "New Blog Post",
    "meta_description": "An exciting new blog post about...",
    "parent_id": "parent-story-uuid"
}
```

#### Update Story
```http
PUT /api/spaces/{space}/stories/{story}
Content-Type: application/json

{
    "name": "Updated Post Title",
    "content": {
        "component": "page",
        "body": [...]
    },
    "status": "published"
}
```

#### Delete Story
```http
DELETE /api/spaces/{space}/stories/{story}
```

#### Publish Story
```http
POST /api/spaces/{space}/stories/{story}/publish

{
    "scheduled_at": "2024-12-01T10:00:00Z" // Optional: schedule for later
}
```

#### Unpublish Story
```http
POST /api/spaces/{space}/stories/{story}/unpublish
```

### Components

#### List Components
```http
GET /api/spaces/{space}/components
```

**Query Parameters:**
- `status`: Filter by status (`active`, `inactive`, `deprecated`)
- `is_nestable`: Filter nestable components
- `is_root`: Filter root-level components
- `search`: Search by name or technical name

**Response:**
```json
{
    "data": [
        {
            "id": "789e0123-e89b-12d3-a456-426614174002",
            "name": "Hero Section",
            "technical_name": "hero_section",
            "description": "Full-width hero section with image and CTA",
            "icon": "hero",
            "color": "#FF6B6B",
            "is_nestable": false,
            "is_root": true,
            "status": "active",
            "version": 2,
            "schema": [
                {
                    "key": "title",
                    "type": "text",
                    "display_name": "Hero Title",
                    "required": true,
                    "max_length": 100
                },
                {
                    "key": "subtitle",
                    "type": "textarea",
                    "display_name": "Subtitle",
                    "required": false,
                    "max_length": 250
                }
            ],
            "created_at": "2024-01-01T00:00:00Z",
            "updated_at": "2024-01-10T12:00:00Z"
        }
    ]
}
```

#### Get Single Component
```http
GET /api/spaces/{space}/components/{component}
```

#### Create Component
```http
POST /api/spaces/{space}/components
Content-Type: application/json

{
    "name": "Text Block",
    "technical_name": "text_block",
    "description": "Simple text content with formatting options",
    "icon": "text",
    "color": "#4A90E2",
    "is_nestable": true,
    "is_root": false,
    "schema": [
        {
            "key": "content",
            "type": "textarea",
            "display_name": "Content",
            "required": true,
            "max_length": 5000
        },
        {
            "key": "alignment",
            "type": "select",
            "display_name": "Text Alignment",
            "required": false,
            "default_value": "left",
            "options": [
                {"label": "Left", "value": "left"},
                {"label": "Center", "value": "center"},
                {"label": "Right", "value": "right"}
            ]
        }
    ]
}
```

#### Update Component
```http
PUT /api/spaces/{space}/components/{component}
```

#### Delete Component
```http
DELETE /api/spaces/{space}/components/{component}
```

### Assets

#### List Assets
```http
GET /api/spaces/{space}/assets
```

**Query Parameters:**
- `type`: Filter by content type (`image`, `video`, `document`)
- `search`: Search by filename
- `folder`: Filter by folder path

**Response:**
```json
{
    "data": [
        {
            "id": "abc12345-e89b-12d3-a456-426614174003",
            "filename": "hero-image.jpg",
            "original_filename": "my-hero-image.jpg",
            "content_type": "image/jpeg",
            "file_size": 1048576,
            "storage_path": "/spaces/my-blog/assets/2024/01/hero-image.jpg",
            "public_url": "https://cdn.example.com/spaces/my-blog/assets/2024/01/hero-image.jpg",
            "alt_text": "Hero image for blog",
            "metadata": {
                "width": 1920,
                "height": 1080,
                "exif": {...}
            },
            "variants": {
                "thumbnail": {
                    "url": "https://cdn.example.com/.../hero-image-thumb.jpg",
                    "width": 300,
                    "height": 200
                },
                "medium": {
                    "url": "https://cdn.example.com/.../hero-image-medium.jpg",
                    "width": 800,
                    "height": 600
                }
            },
            "created_at": "2024-01-15T14:30:00Z"
        }
    ]
}
```

#### Upload Asset
```http
POST /api/spaces/{space}/assets
Content-Type: multipart/form-data

file: [binary file data]
alt_text: "Description of the image"
folder: "blog/images"
```

#### Get Single Asset
```http
GET /api/spaces/{space}/assets/{asset}
```

#### Update Asset
```http
PUT /api/spaces/{space}/assets/{asset}
Content-Type: application/json

{
    "alt_text": "Updated description",
    "metadata": {
        "caption": "A beautiful sunset over the mountains"
    }
}
```

#### Delete Asset
```http
DELETE /api/spaces/{space}/assets/{asset}
```

### Users and Roles

#### List Space Users
```http
GET /api/spaces/{space}/users
```

#### Invite User to Space
```http
POST /api/spaces/{space}/users/invite
Content-Type: application/json

{
    "email": "newuser@example.com",
    "role": "editor",
    "custom_permissions": {
        "publish_stories": false
    }
}
```

#### Update User Role
```http
PUT /api/spaces/{space}/users/{user}
Content-Type: application/json

{
    "role": "admin",
    "custom_permissions": {
        "manage_components": true,
        "manage_assets": true
    }
}
```

#### Remove User from Space
```http
DELETE /api/spaces/{space}/users/{user}
```

---

## Response Formats

### Success Response
```json
{
    "data": {
        // Resource data or array of resources
    },
    "meta": {
        // Pagination or additional metadata (when applicable)
        "current_page": 1,
        "last_page": 10,
        "per_page": 20,
        "total": 187
    }
}
```

### Error Response
```json
{
    "error": {
        "message": "The given data was invalid.",
        "type": "validation_error",
        "code": 422,
        "details": {
            "name": ["The name field is required."],
            "email": ["The email must be a valid email address."]
        }
    }
}
```

### Resource Relationships

Include related resources using the `include` parameter:

```http
GET /api/spaces/{space}/stories?include=creator,translations,parent
```

**Response:**
```json
{
    "data": [
        {
            "id": "...",
            "name": "My Story",
            "creator": {
                "id": "...",
                "name": "John Doe",
                "email": "john@example.com"
            },
            "translations": [
                {
                    "id": "...",
                    "language": "fr",
                    "name": "Mon Histoire"
                }
            ],
            "parent": {
                "id": "...",
                "name": "Parent Folder",
                "is_folder": true
            }
        }
    ]
}
```

---

## Error Handling

### HTTP Status Codes

- `200 OK`: Successful GET, PUT requests
- `201 Created`: Successful POST requests
- `204 No Content`: Successful DELETE requests
- `400 Bad Request`: Invalid request format
- `401 Unauthorized`: Missing or invalid authentication
- `403 Forbidden`: Insufficient permissions
- `404 Not Found`: Resource not found
- `409 Conflict`: Resource conflict (e.g., duplicate slug)
- `422 Unprocessable Entity`: Validation errors
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error

### Error Types

#### Validation Errors
```json
{
    "error": {
        "message": "The given data was invalid.",
        "type": "validation_error",
        "code": 422,
        "details": {
            "name": ["The name field is required."],
            "schema": ["Invalid field type: invalid_type"]
        }
    }
}
```

#### Authentication Errors
```json
{
    "error": {
        "message": "Unauthenticated.",
        "type": "authentication_error",
        "code": 401
    }
}
```

#### Permission Errors
```json
{
    "error": {
        "message": "Insufficient permissions to perform this action.",
        "type": "permission_error",
        "code": 403,
        "details": {
            "required_permission": "publish_stories",
            "user_role": "author"
        }
    }
}
```

#### Resource Not Found
```json
{
    "error": {
        "message": "Story not found.",
        "type": "not_found_error",
        "code": 404,
        "details": {
            "resource_type": "story",
            "resource_id": "invalid-uuid"
        }
    }
}
```

---

## Rate Limiting

The API implements rate limiting to prevent abuse and ensure fair usage.

### Rate Limit Headers

All API responses include rate limit headers:

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1640995200
```

### Rate Limit Tiers

- **Free Plan**: 1,000 requests/hour
- **Pro Plan**: 10,000 requests/hour  
- **Enterprise Plan**: 100,000 requests/hour

### Rate Limit Exceeded

```json
{
    "error": {
        "message": "Rate limit exceeded. Try again in 3600 seconds.",
        "type": "rate_limit_error",
        "code": 429,
        "details": {
            "retry_after": 3600,
            "limit": 1000,
            "reset_time": "2024-01-01T15:00:00Z"
        }
    }
}
```

---

## SDKs and Examples

### JavaScript/TypeScript SDK

```javascript
import { HeadlessCMS } from '@headless-cms/js-sdk';

const cms = new HeadlessCMS({
    apiUrl: 'https://api.your-cms.com',
    token: 'your-api-token'
});

// Get all published stories
const stories = await cms.stories.list('my-blog', {
    status: 'published',
    language: 'en'
});

// Get single story
const story = await cms.stories.get('my-blog', 'story-uuid');

// Create new story
const newStory = await cms.stories.create('my-blog', {
    name: 'New Post',
    status: 'draft',
    content: {
        component: 'page',
        body: [...]
    }
});

// Update story
await cms.stories.update('my-blog', 'story-uuid', {
    status: 'published'
});
```

### PHP SDK

```php
use HeadlessCMS\SDK\Client;

$cms = new Client([
    'api_url' => 'https://api.your-cms.com',
    'token' => 'your-api-token'
]);

// Get stories
$stories = $cms->stories()->list('my-blog', [
    'status' => 'published',
    'per_page' => 20
]);

// Create story
$story = $cms->stories()->create('my-blog', [
    'name' => 'New Post',
    'content' => [
        'component' => 'page',
        'body' => [...]
    ]
]);

// Upload asset
$asset = $cms->assets()->upload('my-blog', [
    'file' => fopen('/path/to/image.jpg', 'r'),
    'alt_text' => 'Description'
]);
```

### cURL Examples

#### Get Stories
```bash
curl -X GET "https://api.your-cms.com/api/spaces/my-blog/stories?status=published" \
  -H "Authorization: Bearer your-api-token" \
  -H "Accept: application/json"
```

#### Create Story
```bash
curl -X POST "https://api.your-cms.com/api/spaces/my-blog/stories" \
  -H "Authorization: Bearer your-api-token" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Story",
    "status": "draft",
    "content": {
      "component": "page",
      "body": []
    }
  }'
```

#### Upload Asset
```bash
curl -X POST "https://api.your-cms.com/api/spaces/my-blog/assets" \
  -H "Authorization: Bearer your-api-token" \
  -F "file=@/path/to/image.jpg" \
  -F "alt_text=Hero image"
```

### Webhook Integration

Subscribe to real-time events:

```http
POST /api/spaces/{space}/webhooks
Content-Type: application/json

{
    "url": "https://your-app.com/webhooks/cms",
    "events": ["story.published", "story.unpublished", "asset.uploaded"],
    "secret": "your-webhook-secret"
}
```

**Webhook Payload:**
```json
{
    "event": "story.published",
    "timestamp": "2024-01-15T10:00:00Z",
    "space": {
        "id": "space-uuid",
        "slug": "my-blog"
    },
    "data": {
        "story": {
            "id": "story-uuid",
            "name": "Published Story",
            "slug": "published-story",
            "published_at": "2024-01-15T10:00:00Z"
        }
    }
}
```

### GraphQL API (Optional)

For complex queries, a GraphQL endpoint is available:

```graphql
query GetSpaceContent($spaceSlug: String!) {
    space(slug: $spaceSlug) {
        id
        name
        stories(status: PUBLISHED, first: 10) {
            edges {
                node {
                    id
                    name
                    slug
                    content
                    publishedAt
                    creator {
                        name
                        email
                    }
                }
            }
        }
        components {
            id
            name
            technicalName
            schema
        }
    }
}
```

This comprehensive API documentation provides everything needed to integrate with the headless CMS, from basic CRUD operations to advanced features like webhooks and GraphQL queries.