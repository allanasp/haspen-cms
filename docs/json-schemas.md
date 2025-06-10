# JSON Schema Examples for Headless CMS

This document provides examples of JSON structures used throughout the headless CMS system, following Storyblok-style patterns.

## Story Content Structure

Stories contain structured content using components. Here's an example story content:

```json
{
  "component": "page",
  "body": [
    {
      "_uid": "c1b2a3d4-e5f6-7890-abcd-ef1234567890",
      "component": "hero_section",
      "title": "Welcome to Our Website",
      "subtitle": "Creating amazing digital experiences",
      "background_image": {
        "id": 12345,
        "alt": "Hero background",
        "name": "hero-bg.jpg",
        "focus": "center",
        "title": "Hero Background Image",
        "filename": "https://assets.example.com/hero-bg.jpg"
      },
      "cta_button": {
        "text": "Get Started",
        "url": "/contact",
        "style": "primary"
      }
    },
    {
      "_uid": "d2c3b4a5-f6e7-8901-bcde-f23456789012",
      "component": "content_section",
      "headline": "About Our Services",
      "content": "We provide comprehensive digital solutions...",
      "layout": "two-column",
      "features": [
        {
          "icon": "check",
          "title": "Quality Assured",
          "description": "High-quality deliverables"
        },
        {
          "icon": "clock",
          "title": "Fast Delivery",
          "description": "Quick turnaround times"
        }
      ]
    },
    {
      "_uid": "e3d4c5b6-a7f8-9012-cdef-345678901234",
      "component": "image_gallery",
      "title": "Our Work",
      "images": [
        {
          "id": 12346,
          "alt": "Project 1",
          "name": "project-1.jpg",
          "filename": "https://assets.example.com/project-1.jpg",
          "caption": "E-commerce Platform"
        },
        {
          "id": 12347,
          "alt": "Project 2", 
          "name": "project-2.jpg",
          "filename": "https://assets.example.com/project-2.jpg",
          "caption": "Mobile Application"
        }
      ],
      "layout": "grid",
      "columns": 3
    }
  ]
}
```

## Component Schema Definition

Components define the structure and validation rules for content blocks:

```json
{
  "display_name": "Hero Section",
  "schema": {
    "title": {
      "type": "text",
      "pos": 0,
      "display_name": "Title",
      "required": true,
      "max_length": 100,
      "description": "Main headline for the hero section"
    },
    "subtitle": {
      "type": "textarea",
      "pos": 1,
      "display_name": "Subtitle",
      "required": false,
      "max_length": 200,
      "description": "Supporting text under the title"
    },
    "background_image": {
      "type": "asset",
      "pos": 2,
      "display_name": "Background Image",
      "required": true,
      "filetypes": ["images"],
      "description": "Hero section background image"
    },
    "cta_button": {
      "type": "bloks",
      "pos": 3,
      "display_name": "Call to Action",
      "required": false,
      "restrict_components": true,
      "component_whitelist": ["button"],
      "maximum": 1,
      "description": "Primary call-to-action button"
    },
    "overlay_opacity": {
      "type": "number",
      "pos": 4,
      "display_name": "Overlay Opacity",
      "required": false,
      "default_value": 0.3,
      "min_value": 0,
      "max_value": 1,
      "step": 0.1,
      "description": "Background overlay opacity (0-1)"
    },
    "text_color": {
      "type": "option",
      "pos": 5,
      "display_name": "Text Color",
      "required": false,
      "default_value": "white",
      "options": [
        {"name": "White", "value": "white"},
        {"name": "Dark", "value": "dark"},
        {"name": "Primary", "value": "primary"}
      ],
      "description": "Text color theme"
    }
  },
  "image": null,
  "preview_field": "title",
  "is_root": true,
  "preview_tmpl": "<div class=\"hero-preview\">\n  <h1>{{ title }}</h1>\n  <p>{{ subtitle }}</p>\n</div>",
  "is_nestable": false,
  "all_presets": [],
  "preset_id": null,
  "real_name": "hero_section",
  "component_group_uuid": null
}
```

## Asset Metadata Structure

Assets contain rich metadata for file management:

```json
{
  "id": 12345,
  "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "filename": "hero-background.jpg",
  "name": "Hero Background Image",
  "description": "Main hero section background for homepage",
  "alt_text": "Modern office building with glass facade",
  "content_type": "image/jpeg",
  "file_size": 2458640,
  "file_hash": "sha256:a1b2c3d4e5f6...",
  "extension": "jpg",
  "storage_disk": "s3",
  "storage_path": "spaces/space-uuid/assets/2024/06/hero-background.jpg",
  "public_url": "https://assets.example.com/hero-background.jpg",
  "cdn_url": "https://cdn.example.com/hero-background.jpg",
  "width": 1920,
  "height": 1080,
  "aspect_ratio": 1.7778,
  "dominant_color": "#2c3e50",
  "has_transparency": false,
  "processing_data": {
    "optimized": true,
    "webp_generated": true,
    "avif_generated": true,
    "quality": 85,
    "compression_ratio": 0.75
  },
  "variants": {
    "thumbnail": {
      "url": "https://cdn.example.com/hero-background-thumb.jpg",
      "width": 150,
      "height": 84,
      "file_size": 8456
    },
    "medium": {
      "url": "https://cdn.example.com/hero-background-med.jpg", 
      "width": 800,
      "height": 450,
      "file_size": 125678
    },
    "webp": {
      "url": "https://cdn.example.com/hero-background.webp",
      "width": 1920,
      "height": 1080,
      "file_size": 1234567
    }
  },
  "tags": ["hero", "background", "homepage", "blue"],
  "custom_fields": {
    "photographer": "John Doe",
    "license": "CC BY 4.0",
    "location": "New York City"
  },
  "usage_stats": {
    "stories_count": 5,
    "last_30_days_views": 1250,
    "total_downloads": 42
  },
  "is_public": true,
  "expires_at": null,
  "uploaded_by": 1,
  "created_at": "2024-06-10T09:30:00Z",
  "updated_at": "2024-06-10T09:35:00Z"
}
```

## Datasource Multi-dimensional Data

Datasources can contain complex, multi-dimensional data structures:

```json
{
  "name": "Product Catalog",
  "slug": "product-catalog",
  "data": {
    "categories": [
      {
        "id": "electronics",
        "name": "Electronics",
        "subcategories": [
          {
            "id": "smartphones",
            "name": "Smartphones",
            "products": [
              {
                "id": "iphone-15",
                "name": "iPhone 15",
                "price": 999,
                "currency": "USD",
                "specifications": {
                  "display": "6.1-inch Super Retina XDR",
                  "storage": ["128GB", "256GB", "512GB"],
                  "colors": ["Black", "Blue", "Green", "Yellow", "Pink"]
                },
                "availability": {
                  "in_stock": true,
                  "quantity": 150,
                  "regions": ["US", "EU", "APAC"]
                },
                "metadata": {
                  "featured": true,
                  "new_arrival": true,
                  "rating": 4.7,
                  "reviews_count": 2847
                }
              }
            ]
          }
        ]
      }
    ],
    "filters": {
      "price_ranges": [
        {"min": 0, "max": 500, "label": "Under $500"},
        {"min": 500, "max": 1000, "label": "$500 - $1000"},
        {"min": 1000, "max": null, "label": "Over $1000"}
      ],
      "brands": ["Apple", "Samsung", "Google", "OnePlus"],
      "features": ["5G", "Wireless Charging", "Face ID", "Fingerprint"]
    }
  },
  "dimensions": {
    "geography": ["US", "EU", "APAC"],
    "category": ["electronics", "clothing", "home"],
    "target_audience": ["consumer", "business", "enterprise"]
  },
  "computed_fields": {
    "total_products": 1247,
    "average_price": 645.50,
    "popular_categories": ["smartphones", "laptops", "headphones"],
    "price_distribution": {
      "under_500": 45,
      "500_to_1000": 35,
      "over_1000": 20
    }
  }
}
```

## Space Configuration Example

Spaces contain environment-specific settings and configurations:

```json
{
  "uuid": "space-12345-abcde",
  "name": "My Website",
  "slug": "my-website",
  "domain": "mywebsite.com",
  "settings": {
    "timezone": "America/New_York",
    "date_format": "MM/DD/YYYY",
    "currency": "USD",
    "image_optimization": {
      "enabled": true,
      "formats": ["webp", "avif"],
      "quality": 85,
      "auto_compress": true
    },
    "seo": {
      "default_meta_title": "My Website - Digital Solutions",
      "default_meta_description": "We provide innovative digital solutions...",
      "robots_default": "index,follow",
      "sitemap_enabled": true
    },
    "api": {
      "rate_limit": 1000,
      "cache_ttl": 3600,
      "webhook_secret": "wh_secret_key"
    }
  },
  "environments": {
    "development": {
      "api_url": "https://dev-api.mywebsite.com",
      "cdn_url": "https://dev-cdn.mywebsite.com",
      "cache_ttl": 60,
      "debug_mode": true
    },
    "staging": {
      "api_url": "https://staging-api.mywebsite.com", 
      "cdn_url": "https://staging-cdn.mywebsite.com",
      "cache_ttl": 300,
      "debug_mode": false
    },
    "production": {
      "api_url": "https://api.mywebsite.com",
      "cdn_url": "https://cdn.mywebsite.com", 
      "cache_ttl": 3600,
      "debug_mode": false
    }
  },
  "languages": ["en", "es", "fr"],
  "default_language": "en"
}
```

## User Permissions Structure

Role-based permissions with granular control:

```json
{
  "name": "Editor",
  "permissions": {
    "stories": {
      "create": true,
      "read": true,
      "update": true,
      "delete": false,
      "publish": false,
      "restrictions": {
        "own_content_only": true,
        "allowed_folders": ["content", "blog"],
        "forbidden_folders": ["system", "admin"]
      }
    },
    "assets": {
      "create": true,
      "read": true,
      "update": true,
      "delete": false,
      "restrictions": {
        "max_file_size": "10MB",
        "allowed_types": ["images", "videos", "documents"],
        "forbidden_types": ["executables", "archives"]
      }
    },
    "components": {
      "create": false,
      "read": true,
      "update": false,
      "delete": false
    },
    "datasources": {
      "create": false,
      "read": true,
      "update": false,
      "delete": false
    },
    "users": {
      "create": false,
      "read": false,
      "update": false,
      "delete": false
    },
    "space_settings": {
      "read": false,
      "update": false
    }
  },
  "workflow": {
    "can_submit_for_review": true,
    "can_approve_content": false,
    "can_schedule_publishing": false,
    "requires_approval": true
  }
}
```

These JSON structures provide a comprehensive foundation for building a Storyblok-like headless CMS with rich content management, asset handling, and flexible data source integration.