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
  "name": "Content Editor",
  "slug": "content-editor", 
  "description": "Can create and edit content but not publish",
  "permissions": [
    "stories.create",
    "stories.read",
    "stories.update", 
    "assets.create",
    "assets.read",
    "assets.update",
    "components.read"
  ],
  "legacy_permissions": {
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

## Advanced Story Content with Locking

Story with content locking information:

```json
{
  "uuid": "story-12345-abcde",
  "name": "Product Launch Article",
  "slug": "product-launch-2024",
  "status": "draft",
  "language": "en",
  "content": {
    "body": [
      {
        "_uid": "component-uuid-1",
        "component": "hero",
        "title": "Revolutionary Product Launch",
        "description": "Introducing our latest innovation"
      }
    ]
  },
  "translation_info": {
    "translation_group_id": 123,
    "translated_languages": ["en", "es", "fr"],
    "translation_status": {
      "en": {
        "completion_percentage": 100,
        "last_updated": "2024-06-15T10:30:00Z",
        "needs_sync": false
      },
      "es": {
        "completion_percentage": 75,
        "last_updated": "2024-06-10T14:20:00Z", 
        "needs_sync": true
      }
    }
  },
  "lock_info": {
    "is_locked": true,
    "locked_by": "user-uuid-456",
    "locked_at": "2024-06-15T15:30:00Z",
    "lock_expires_at": "2024-06-15T16:00:00Z",
    "session_id": "session-uuid-789",
    "locker": {
      "name": "John Doe",
      "email": "john@example.com"
    },
    "time_remaining": 25
  },
  "version_info": {
    "current_version": 5,
    "total_versions": 12,
    "last_version_reason": "Updated hero section with new product images"
  },
  "meta_data": {
    "is_template": false,
    "template_category": null,
    "created_from_template": "product-launch-template",
    "seo": {
      "meta_title": "Revolutionary Product Launch - Company Name",
      "meta_description": "Discover our latest innovation that's changing the industry",
      "canonical_url": "https://example.com/product-launch-2024",
      "robots": {
        "index": true,
        "follow": true,
        "noarchive": false
      }
    }
  },
  "created_at": "2024-06-10T09:00:00Z",
  "updated_at": "2024-06-15T15:30:00Z"
}
```

## Component Schema with Advanced Validation

Enhanced component schema with 20+ field types:

```json
{
  "name": "Advanced Product Card",
  "technical_name": "advanced_product_card",
  "description": "Comprehensive product display component with pricing and variants",
  "version": 2,
  "schema": [
    {
      "key": "product_name",
      "type": "text",
      "display_name": "Product Name",
      "required": true,
      "max_length": 100,
      "min_length": 3,
      "placeholder": "Enter product name",
      "description": "The name of the product"
    },
    {
      "key": "description",
      "type": "richtext",
      "display_name": "Product Description", 
      "required": true,
      "max_length": 2000,
      "toolbar": ["bold", "italic", "link", "bullet-list"],
      "description": "Detailed product description with formatting"
    },
    {
      "key": "price",
      "type": "number",
      "display_name": "Price",
      "required": true,
      "min": 0,
      "max": 999999.99,
      "step": 0.01,
      "prefix": "$",
      "suffix": " USD",
      "description": "Product price in USD"
    },
    {
      "key": "featured_image",
      "type": "asset",
      "display_name": "Featured Image",
      "required": true,
      "allowed_types": ["image/jpeg", "image/png", "image/webp"],
      "max_file_size": 5242880,
      "dimensions": {
        "min_width": 400,
        "min_height": 300,
        "max_width": 1920,
        "max_height": 1080,
        "aspect_ratio": "16:9"
      },
      "description": "Main product image"
    },
    {
      "key": "gallery",
      "type": "json",
      "display_name": "Product Gallery",
      "required": false,
      "description": "Additional product images",
      "validation": {
        "schema": {
          "type": "array",
          "items": {
            "type": "object",
            "properties": {
              "image": {"type": "object"},
              "caption": {"type": "string", "maxLength": 200}
            }
          },
          "maxItems": 10
        }
      }
    },
    {
      "key": "category",
      "type": "select",
      "display_name": "Product Category",
      "required": true,
      "options": [
        {"label": "Electronics", "value": "electronics"},
        {"label": "Clothing", "value": "clothing"},
        {"label": "Home & Garden", "value": "home-garden"},
        {"label": "Sports & Outdoors", "value": "sports-outdoors"}
      ],
      "default_value": "electronics"
    },
    {
      "key": "tags",
      "type": "multiselect",
      "display_name": "Product Tags",
      "required": false,
      "options": [
        {"label": "Featured", "value": "featured"},
        {"label": "On Sale", "value": "on-sale"},
        {"label": "New Arrival", "value": "new-arrival"},
        {"label": "Limited Edition", "value": "limited-edition"}
      ],
      "max_selections": 5
    },
    {
      "key": "availability",
      "type": "boolean",
      "display_name": "In Stock",
      "required": false,
      "default_value": true,
      "description": "Whether the product is currently available"
    },
    {
      "key": "launch_date",
      "type": "datetime",
      "display_name": "Launch Date",
      "required": false,
      "min_date": "2024-01-01",
      "max_date": "2025-12-31",
      "description": "When the product was or will be launched"
    },
    {
      "key": "brand_color",
      "type": "color",
      "display_name": "Brand Color",
      "required": false,
      "default_value": "#3b82f6",
      "description": "Primary brand color for this product"
    },
    {
      "key": "specifications",
      "type": "table",
      "display_name": "Product Specifications",
      "required": false,
      "columns": [
        {"key": "property", "label": "Property", "type": "text"},
        {"key": "value", "label": "Value", "type": "text"},
        {"key": "unit", "label": "Unit", "type": "text"}
      ],
      "max_rows": 20,
      "description": "Technical specifications and features"
    },
    {
      "key": "related_products",
      "type": "blocks",
      "display_name": "Related Products",
      "required": false,
      "restrict_components": true,
      "component_whitelist": ["product_reference", "product_bundle"],
      "maximum": 5,
      "description": "Related or complementary products"
    }
  ],
  "preview_field": {
    "template": "{product_name} - ${price}",
    "fields": ["product_name", "price"]
  },
  "tabs": [
    {
      "name": "Content",
      "fields": ["product_name", "description", "featured_image", "gallery"]
    },
    {
      "name": "Details", 
      "fields": ["price", "category", "tags", "availability", "launch_date"]
    },
    {
      "name": "Design",
      "fields": ["brand_color", "specifications"]
    },
    {
      "name": "Relations",
      "fields": ["related_products"]
    }
  ],
  "is_root": false,
  "is_nestable": true,
  "icon": "shopping-bag",
  "color": "#10b981",
  "created_at": "2024-06-15T10:00:00Z",
  "updated_at": "2024-06-15T15:30:00Z"
}
```

## Datasource Entry with Multi-Dimensional Data

Complex datasource entry with advanced filtering capabilities:

```json
{
  "uuid": "entry-12345-abcde",
  "name": "iPhone 15 Pro Max",
  "slug": "iphone-15-pro-max",
  "value": {
    "id": "iphone-15-pro-max",
    "name": "iPhone 15 Pro Max",
    "brand": "Apple",
    "model": "iPhone 15 Pro Max",
    "price": {
      "base": 1199,
      "currency": "USD",
      "variations": {
        "128gb": 1199,
        "256gb": 1299,
        "512gb": 1499,
        "1tb": 1699
      }
    },
    "specifications": {
      "display": {
        "size": "6.7 inches",
        "resolution": "2796 x 1290",
        "technology": "Super Retina XDR",
        "refresh_rate": "120Hz"
      },
      "camera": {
        "main": "48MP",
        "ultra_wide": "12MP", 
        "telephoto": "12MP",
        "front": "12MP",
        "features": ["Night mode", "Portrait mode", "4K video"]
      },
      "performance": {
        "chip": "A17 Pro",
        "cpu": "6-core",
        "gpu": "6-core",
        "neural_engine": "16-core"
      },
      "storage_options": ["128GB", "256GB", "512GB", "1TB"],
      "colors": ["Natural Titanium", "Blue Titanium", "White Titanium", "Black Titanium"]
    },
    "availability": {
      "in_stock": true,
      "regions": ["US", "EU", "APAC"],
      "release_date": "2023-09-22",
      "estimated_shipping": "1-2 business days"
    }
  },
  "dimensions": {
    "category": "electronics",
    "subcategory": "smartphones",
    "brand": "apple",
    "price_range": "premium",
    "target_audience": "consumer",
    "geography": ["US", "EU", "APAC"],
    "features": ["5g", "wireless_charging", "face_id", "magsafe"]
  },
  "computed_fields": {
    "price_score": 4.2,
    "feature_score": 4.8,
    "availability_score": 4.9,
    "overall_rating": 4.6,
    "review_count": 15420,
    "popularity_rank": 3,
    "search_keywords": [
      "iphone", "apple", "smartphone", "premium", "pro max", 
      "titanium", "48mp camera", "a17 pro", "120hz"
    ]
  },
  "metadata": {
    "last_synced": "2024-06-15T10:30:00Z",
    "sync_source": "apple_api",
    "data_quality_score": 0.95,
    "verification_status": "verified",
    "content_flags": []
  },
  "created_at": "2024-06-10T12:00:00Z",
  "updated_at": "2024-06-15T10:30:00Z"
}
```

These JSON structures provide a comprehensive foundation for building a Storyblok-like headless CMS with rich content management, advanced component validation, content locking, translation workflows, and flexible data source integration.