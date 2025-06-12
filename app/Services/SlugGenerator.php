<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Space;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slug generation service for creating URL-friendly slugs.
 */
class SlugGenerator
{
    /**
     * Generate a slug from a title.
     *
     * @param string $title
     * @param int $maxLength
     * @return string
     */
    public function generateFromTitle(string $title, int $maxLength = 255): string
    {
        // Basic slug generation
        $slug = Str::slug($title);

        // Handle empty results
        if (empty($slug)) {
            $slug = 'item';
        }

        // Truncate if too long
        if (strlen($slug) > $maxLength) {
            $slug = substr($slug, 0, $maxLength);
            // Remove any trailing hyphens
            $slug = rtrim($slug, '-');
        }

        return $slug;
    }

    /**
     * Ensure slug is unique within a space and model.
     *
     * @param string $slug
     * @param Space $space
     * @param class-string<Model> $modelClass
     * @param int|null $excludeId
     * @return string
     */
    public function ensureUnique(string $slug, Space $space, string $modelClass, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $space, $modelClass, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;

            // Prevent infinite loops
            if ($counter > 1000) {
                $slug = $originalSlug . '-' . uniqid();
                break;
            }
        }

        return $slug;
    }

    /**
     * Check if a slug exists in the given space and model.
     *
     * @param string $slug
     * @param Space $space
     * @param class-string<Model> $modelClass
     * @param int|null $excludeId
     * @return bool
     */
    private function slugExists(string $slug, Space $space, string $modelClass, ?int $excludeId = null): bool
    {
        $query = $modelClass::where('slug', $slug)
            ->where('space_id', $space->id);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Generate a hierarchical slug based on parent path.
     *
     * @param string $title
     * @param Model|null $parent
     * @param string $separator
     * @return string
     */
    public function generateHierarchicalSlug(string $title, ?Model $parent = null, string $separator = '/'): string
    {
        $slug = $this->generateFromTitle($title);

        if (!$parent || !method_exists($parent, 'getSlugPath')) {
            return $slug;
        }

        $parentPath = $parent->getSlugPath();
        
        if ($parentPath) {
            return $parentPath . $separator . $slug;
        }

        return $slug;
    }

    /**
     * Validate slug format.
     *
     * @param string $slug
     * @return bool
     */
    public function isValidSlug(string $slug): bool
    {
        // Check basic format
        if (empty($slug) || strlen($slug) > 255) {
            return false;
        }

        // Check for valid characters (alphanumeric, hyphens, forward slashes)
        if (!preg_match('/^[a-z0-9\-\/]+$/', $slug)) {
            return false;
        }

        // Check for invalid patterns
        $invalidPatterns = [
            '/^-/',      // starts with hyphen
            '/-$/',      // ends with hyphen
            '/--+/',     // multiple consecutive hyphens
            '/\/\/+/',   // multiple consecutive slashes
            '/^\/{2,}/', // starts with multiple slashes
            '/\/{2,}$/', // ends with multiple slashes
        ];

        foreach ($invalidPatterns as $pattern) {
            if (preg_match($pattern, $slug)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize user-provided slug.
     *
     * @param string $slug
     * @return string
     */
    public function sanitizeSlug(string $slug): string
    {
        // Convert to lowercase
        $slug = strtolower($slug);

        // Replace spaces and underscores with hyphens
        $slug = preg_replace('/[\s_]+/', '-', $slug);

        // Remove any characters that aren't alphanumeric, hyphens, or forward slashes
        $slug = preg_replace('/[^a-z0-9\-\/]/', '', $slug);

        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Remove multiple consecutive slashes
        $slug = preg_replace('/\/+/', '/', $slug);

        // Remove leading/trailing hyphens and slashes
        $slug = trim($slug, '-/');

        // Ensure it's not empty
        if (empty($slug)) {
            $slug = 'item';
        }

        return $slug;
    }

    /**
     * Generate slug with date prefix.
     *
     * @param string $title
     * @param \DateTime|null $date
     * @param string $format
     * @return string
     */
    public function generateWithDatePrefix(string $title, ?\DateTime $date = null, string $format = 'Y/m/d'): string
    {
        $date = $date ?? new \DateTime();
        $datePrefix = $date->format($format);
        $slug = $this->generateFromTitle($title);

        return $datePrefix . '/' . $slug;
    }

    /**
     * Extract slug components from hierarchical slug.
     *
     * @param string $slug
     * @param string $separator
     * @return array<string>
     */
    public function extractSlugComponents(string $slug, string $separator = '/'): array
    {
        return array_filter(explode($separator, $slug), fn($part) => !empty($part));
    }

    /**
     * Suggest alternative slugs when the preferred one is taken.
     *
     * @param string $slug
     * @param Space $space
     * @param class-string<Model> $modelClass
     * @param int $count
     * @return array<string>
     */
    public function suggestAlternatives(string $slug, Space $space, string $modelClass, int $count = 5): array
    {
        $suggestions = [];
        $baseSlug = $slug;

        // Add numbered variants
        for ($i = 2; $i <= $count + 1; $i++) {
            $candidate = $baseSlug . '-' . $i;
            if (!$this->slugExists($candidate, $space, $modelClass)) {
                $suggestions[] = $candidate;
            }
        }

        // Add random variants if needed
        while (count($suggestions) < $count) {
            $candidate = $baseSlug . '-' . Str::random(4);
            if (!$this->slugExists($candidate, $space, $modelClass) && 
                !in_array($candidate, $suggestions)) {
                $suggestions[] = $candidate;
            }
        }

        return array_slice($suggestions, 0, $count);
    }

    /**
     * Generate slug from multiple fields.
     *
     * @param array<string> $fields
     * @param string $separator
     * @return string
     */
    public function generateFromMultipleFields(array $fields, string $separator = '-'): string
    {
        $parts = [];

        foreach ($fields as $field) {
            if (!empty($field)) {
                $parts[] = $this->generateFromTitle($field);
            }
        }

        return implode($separator, $parts) ?: 'item';
    }

    /**
     * Check if slug matches reserved words.
     *
     * @param string $slug
     * @return bool
     */
    public function isReservedSlug(string $slug): bool
    {
        $reserved = [
            'api', 'admin', 'www', 'mail', 'ftp', 'localhost', 'test', 'staging',
            'app', 'application', 'dashboard', 'panel', 'control', 'manage',
            'system', 'root', 'user', 'users', 'account', 'accounts',
            'login', 'logout', 'register', 'signup', 'signin', 'auth',
            'config', 'configuration', 'settings', 'preference', 'preferences',
            'about', 'contact', 'help', 'support', 'privacy', 'terms',
            'blog', 'news', 'article', 'articles', 'post', 'posts',
            'page', 'pages', 'content', 'home', 'index', 'search'
        ];

        // Check exact match
        if (in_array($slug, $reserved)) {
            return true;
        }

        // Check if slug starts with reserved word followed by hyphen
        foreach ($reserved as $word) {
            if (str_starts_with($slug, $word . '-')) {
                return true;
            }
        }

        return false;
    }
}