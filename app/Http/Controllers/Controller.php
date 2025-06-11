<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Traits\HasApiResponses;
use OpenApi\Attributes as OA;

/**
 * Base Controller with OpenAPI configuration.
 * @psalm-suppress UnusedClass
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Headless CMS API',
    description: 'A modern headless CMS API built with Laravel. Provides content delivery and management capabilities with multi-tenant support.',
    contact: new OA\Contact(
        name: 'API Support',
        email: 'support@example.com'
    ),
    license: new OA\License(
        name: 'MIT',
        url: 'https://opensource.org/licenses/MIT'
    )
)]
#[OA\Server(
    url: 'http://localhost:8000/api',
    description: 'Local development server'
)]
#[OA\Server(
    url: 'https://api.yourdomain.com/api',
    description: 'Production server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    name: 'Authorization',
    in: 'header',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'User authentication and token management'
)]
#[OA\Tag(
    name: 'CDN - Stories',
    description: 'Public story content delivery'
)]
#[OA\Tag(
    name: 'CDN - Datasources',
    description: 'Public datasource content delivery'
)]
#[OA\Tag(
    name: 'CDN - Assets',
    description: 'Public asset delivery with transformations'
)]
#[OA\Tag(
    name: 'Management - Stories',
    description: 'Story management operations'
)]
#[OA\Tag(
    name: 'Management - Components',
    description: 'Component management operations'
)]
#[OA\Tag(
    name: 'Management - Assets',
    description: 'Asset management operations'
)]
abstract class Controller
{
    use HasApiResponses;
}
