<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\Json;
use App\Traits\HasUuid;
use App\Traits\MultiTenant;
use App\Traits\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Datasource model for external data integration and management.
 *
 * @property int $id
 * @property int $space_id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $type
 * @property array $config
 * @property array|null $schema
 * @property array|null $mapping
 * @property array|null $auth_config
 * @property array|null $headers
 * @property string|null $cache_key
 * @property int $cache_duration
 * @property bool $auto_sync
 * @property string|null $sync_frequency
 * @property \Carbon\Carbon|null $last_synced_at
 * @property array|null $sync_status
 * @property array|null $filters
 * @property array|null $transformations
 * @property int|null $max_entries
 * @property string $status
 * @property array|null $health_check
 * @property \Carbon\Carbon|null $last_health_check_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property-read Space $space
 * @property-read User $creator
 * @property-read User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<DatasourceEntry> $entries
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Datasource whereSpaceId(int $spaceId)
 * @method static \Illuminate\Database\Eloquent\Builder|Datasource byType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder|Datasource active()
 * @method static \Illuminate\Database\Eloquent\Builder|Datasource inactive()
 * @method static \Illuminate\Database\Eloquent\Builder|Datasource hasError()
 * @method static \Illuminate\Database\Eloquent\Builder|Datasource autoSync()
 * @method static \Illuminate\Database\Eloquent\Builder|Datasource needsSync()
 * @method static \Illuminate\Database\Eloquent\Builder|Datasource healthCheckDue()
 *
 * @mixin \Illuminate\Database\Eloquent\Builder
 * @psalm-suppress UnusedClass
 */
final class Datasource extends Model
{
    use HasFactory;
    use HasUuid;
    use MultiTenant;
    use Sluggable;
    use SoftDeletes;

    public const TYPE_JSON = 'json';
    public const TYPE_CSV = 'csv';
    public const TYPE_API = 'api';
    public const TYPE_DATABASE = 'database';
    public const TYPE_CUSTOM = 'custom';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ERROR = 'error';

    /**
     * The table associated with the model.
     */
    protected string $table = 'datasources';

    /**
     * Configuration for the Sluggable trait.
     *
     * @var array<string, mixed>
     */
    protected array $sluggable = [
        'source_field' => 'name',
        'target_field' => 'slug',
        'auto_update' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected array $fillable = [
        'name',
        'description',
        'type',
        'config',
        'schema',
        'mapping',
        'auth_config',
        'headers',
        'cache_key',
        'cache_duration',
        'auto_sync',
        'sync_frequency',
        'filters',
        'transformations',
        'max_entries',
        'status',
        'health_check',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected array $casts = [
        'config' => Json::class,
        'schema' => Json::class,
        'mapping' => Json::class,
        'auth_config' => Json::class,
        'headers' => Json::class,
        'cache_duration' => 'integer',
        'auto_sync' => 'boolean',
        'last_synced_at' => 'datetime',
        'sync_status' => Json::class,
        'filters' => Json::class,
        'transformations' => Json::class,
        'max_entries' => 'integer',
        'health_check' => Json::class,
        'last_health_check_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected array $hidden = [
        'auth_config',
        'headers',
        'sync_status',
    ];

    /**
     * The space this datasource belongs to.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * The user who created this datasource.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who last updated this datasource.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The entries in this datasource.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(DatasourceEntry::class);
    }

    /**
     * Get datasources by type.
     */
    public function scopeByType(\Illuminate\Database\Eloquent\Builder $query, string $type): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Get only active datasources.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Get only inactive datasources.
     */
    public function scopeInactive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Get datasources with errors.
     */
    public function scopeHasError(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_ERROR);
    }

    /**
     * Get datasources with auto-sync enabled.
     */
    public function scopeAutoSync(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('auto_sync', true);
    }

    /**
     * Get datasources that need syncing.
     */
    public function scopeNeedsSync(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('auto_sync', true)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('last_synced_at')
                    ->orWhereRaw('last_synced_at < NOW() - INTERVAL cache_duration SECOND');
            });
    }

    /**
     * Get datasources that need health check.
     */
    public function scopeHealthCheckDue(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('health_check')
            ->where(function ($query) {
                $query->whereNull('last_health_check_at')
                    ->orWhereRaw('last_health_check_at < NOW() - INTERVAL 1 HOUR');
            });
    }

    /**
     * Fetch data from the datasource.
     *
     * @return array<string, mixed>
     */
    public function fetchData(bool $useCache = true): array
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            throw new \RuntimeException("Datasource '{$this->name}' is not active");
        }

        $cacheKey = $this->getCacheKey();

        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey, []);
        }

        try {
            $data = $this->performDataFetch();
            $processedData = $this->processData($data);

            // Cache the processed data
            Cache::put($cacheKey, $processedData, $this->cache_duration);

            // Update sync status
            $this->updateSyncStatus(true);

            return $processedData;
        } catch (\Exception $e) {
            $this->updateSyncStatus(false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform the actual data fetch based on datasource type.
     *
     * @return array<string, mixed>
     */
    protected function performDataFetch(): array
    {
        return match ($this->type) {
            self::TYPE_JSON => $this->fetchJsonData(),
            self::TYPE_CSV => $this->fetchCsvData(),
            self::TYPE_API => $this->fetchApiData(),
            self::TYPE_DATABASE => $this->fetchDatabaseData(),
            self::TYPE_CUSTOM => $this->fetchCustomData(),
            default => throw new \InvalidArgumentException("Unknown datasource type: {$this->type}"),
        };
    }

    /**
     * Fetch data from JSON source.
     *
     * @return array<string, mixed>
     */
    protected function fetchJsonData(): array
    {
        $config = $this->config;
        
        if (isset($config['url'])) {
            $response = Http::withHeaders($this->headers ?? [])
                ->timeout(30)
                ->get($config['url']);
            
            if (!$response->successful()) {
                throw new \RuntimeException("Failed to fetch JSON data: {$response->status()}");
            }
            
            return $response->json() ?? [];
        }
        
        if (isset($config['data'])) {
            return $config['data'];
        }
        
        throw new \InvalidArgumentException('JSON datasource requires either url or data configuration');
    }

    /**
     * Fetch data from CSV source.
     *
     * @return array<string, mixed>
     */
    protected function fetchCsvData(): array
    {
        $config = $this->config;
        
        if (!isset($config['url'])) {
            throw new \InvalidArgumentException('CSV datasource requires url configuration');
        }
        
        $response = Http::withHeaders($this->headers ?? [])
            ->timeout(30)
            ->get($config['url']);
        
        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch CSV data: {$response->status()}");
        }
        
        $csvData = str_getcsv($response->body(), "\n");
        $headers = str_getcsv($csvData[0]);
        $data = [];
        
        for ($i = 1; $i < count($csvData); $i++) {
            $row = str_getcsv($csvData[$i]);
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }
        
        return $data;
    }

    /**
     * Fetch data from API source.
     *
     * @return array<string, mixed>
     */
    protected function fetchApiData(): array
    {
        $config = $this->config;
        
        if (!isset($config['url'])) {
            throw new \InvalidArgumentException('API datasource requires url configuration');
        }
        
        $http = Http::withHeaders($this->headers ?? [])
            ->timeout($config['timeout'] ?? 30);
        
        // Add authentication if configured
        if ($this->auth_config) {
            $http = $this->addAuthentication($http);
        }
        
        $method = strtoupper($config['method'] ?? 'GET');
        $url = $config['url'];
        $params = $config['params'] ?? [];
        
        $response = match ($method) {
            'GET' => $http->get($url, $params),
            'POST' => $http->post($url, $params),
            'PUT' => $http->put($url, $params),
            'DELETE' => $http->delete($url, $params),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
        
        if (!$response->successful()) {
            throw new \RuntimeException("API request failed: {$response->status()}");
        }
        
        $data = $response->json();
        
        // Extract data from nested response if path is specified
        if (isset($config['data_path'])) {
            $data = data_get($data, $config['data_path'], []);
        }
        
        return is_array($data) ? $data : [$data];
    }

    /**
     * Fetch data from database source.
     *
     * @return array<string, mixed>
     */
    protected function fetchDatabaseData(): array
    {
        $config = $this->config;
        
        if (!isset($config['query'])) {
            throw new \InvalidArgumentException('Database datasource requires query configuration');
        }
        
        // This would need proper database connection handling
        // For now, throwing an exception as this requires additional setup
        throw new \RuntimeException('Database datasource not yet implemented');
    }

    /**
     * Fetch data from custom source.
     *
     * @return array<string, mixed>
     */
    protected function fetchCustomData(): array
    {
        $config = $this->config;
        
        if (!isset($config['handler'])) {
            throw new \InvalidArgumentException('Custom datasource requires handler configuration');
        }
        
        // This would invoke a custom handler class
        // For now, returning empty array
        return [];
    }

    /**
     * Add authentication to HTTP client.
     */
    protected function addAuthentication(\Illuminate\Http\Client\PendingRequest $http): \Illuminate\Http\Client\PendingRequest
    {
        $auth = $this->auth_config;
        
        if (!$auth) {
            return $http;
        }
        
        return match ($auth['type'] ?? '') {
            'bearer' => $http->withToken($auth['token']),
            'basic' => $http->withBasicAuth($auth['username'], $auth['password']),
            'api_key' => $http->withHeaders([$auth['header'] => $auth['key']]),
            default => $http,
        };
    }

    /**
     * Process and transform fetched data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function processData(array $data): array
    {
        // Apply filters
        if ($this->filters) {
            $data = $this->applyFilters($data);
        }
        
        // Apply transformations
        if ($this->transformations) {
            $data = $this->applyTransformations($data);
        }
        
        // Apply mapping
        if ($this->mapping) {
            $data = $this->applyMapping($data);
        }
        
        // Limit entries
        if ($this->max_entries && count($data) > $this->max_entries) {
            $data = array_slice($data, 0, $this->max_entries);
        }
        
        return $data;
    }

    /**
     * Apply filters to data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function applyFilters(array $data): array
    {
        foreach ($this->filters as $filter) {
            $field = $filter['field'] ?? '';
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? '';
            
            $data = array_filter($data, function ($item) use ($field, $operator, $value) {
                $itemValue = data_get($item, $field);
                
                return match ($operator) {
                    '=' => $itemValue == $value,
                    '!=' => $itemValue != $value,
                    '>' => $itemValue > $value,
                    '<' => $itemValue < $value,
                    '>=' => $itemValue >= $value,
                    '<=' => $itemValue <= $value,
                    'contains' => str_contains((string) $itemValue, (string) $value),
                    'starts_with' => str_starts_with((string) $itemValue, (string) $value),
                    'ends_with' => str_ends_with((string) $itemValue, (string) $value),
                    default => true,
                };
            });
        }
        
        return array_values($data);
    }

    /**
     * Apply transformations to data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function applyTransformations(array $data): array
    {
        foreach ($this->transformations as $transformation) {
            $field = $transformation['field'] ?? '';
            $type = $transformation['type'] ?? '';
            
            $data = array_map(function ($item) use ($field, $type, $transformation) {
                $value = data_get($item, $field);
                
                $transformedValue = match ($type) {
                    'uppercase' => strtoupper((string) $value),
                    'lowercase' => strtolower((string) $value),
                    'trim' => trim((string) $value),
                    'date_format' => $this->formatDate($value, $transformation['format'] ?? 'Y-m-d'),
                    'number_format' => number_format((float) $value, $transformation['decimals'] ?? 2),
                    default => $value,
                };
                
                data_set($item, $field, $transformedValue);
                return $item;
            }, $data);
        }
        
        return $data;
    }

    /**
     * Apply field mapping to data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function applyMapping(array $data): array
    {
        return array_map(function ($item) {
            $mappedItem = [];
            
            foreach ($this->mapping as $targetField => $sourceField) {
                $mappedItem[$targetField] = data_get($item, $sourceField);
            }
            
            return $mappedItem;
        }, $data);
    }

    /**
     * Format a date value.
     */
    protected function formatDate(mixed $value, string $format): string
    {
        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Exception) {
            return (string) $value;
        }
    }

    /**
     * Sync data to datasource entries.
     */
    public function syncData(): int
    {
        try {
            $data = $this->fetchData(false);
            
            // Clear existing entries
            $this->entries()->delete();
            
            $synced = 0;
            foreach ($data as $index => $item) {
                DatasourceEntry::create([
                    'datasource_id' => $this->id,
                    'external_id' => $item['id'] ?? (string) $index,
                    'data' => $item,
                    'position' => $index,
                ]);
                $synced++;
            }
            
            $this->updateSyncStatus(true, "Synced {$synced} entries");
            
            return $synced;
        } catch (\Exception $e) {
            $this->updateSyncStatus(false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Perform health check on the datasource.
     */
    public function performHealthCheck(): bool
    {
        try {
            $this->fetchData(false);
            $this->update(['last_health_check_at' => now()]);
            
            if ($this->status === self::STATUS_ERROR) {
                $this->update(['status' => self::STATUS_ACTIVE]);
            }
            
            return true;
        } catch (\Exception $e) {
            $this->update([
                'status' => self::STATUS_ERROR,
                'last_health_check_at' => now(),
                'sync_status' => ['error' => $e->getMessage()],
            ]);
            
            Log::error("Datasource health check failed for {$this->name}: {$e->getMessage()}");
            
            return false;
        }
    }

    /**
     * Update sync status.
     */
    protected function updateSyncStatus(bool $success, ?string $message = null): void
    {
        $status = [
            'success' => $success,
            'timestamp' => now()->toISOString(),
        ];
        
        if ($message) {
            $status['message'] = $message;
        }
        
        $this->update([
            'sync_status' => $status,
            'last_synced_at' => $success ? now() : $this->last_synced_at,
        ]);
    }

    /**
     * Get the cache key for this datasource.
     */
    public function getCacheKey(): string
    {
        return $this->cache_key ?? "datasource:{$this->uuid}:data";
    }

    /**
     * Clear cached data.
     */
    public function clearCache(): void
    {
        Cache::forget($this->getCacheKey());
    }

    /**
     * Check if the datasource is healthy.
     */
    public function isHealthy(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if sync is due.
     */
    public function isSyncDue(): bool
    {
        if (!$this->auto_sync || $this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        
        if (!$this->last_synced_at) {
            return true;
        }
        
        return $this->last_synced_at->addSeconds($this->cache_duration)->isPast();
    }

    /**
     * Get validation rules for datasource creation/update.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function rules(): array
    {
        return [
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', 'in:' . self::TYPE_JSON . ',' . self::TYPE_CSV . ',' . self::TYPE_API . ',' . self::TYPE_DATABASE . ',' . self::TYPE_CUSTOM],
            'config' => ['required', 'array'],
            'schema' => ['nullable', 'array'],
            'mapping' => ['nullable', 'array'],
            'auth_config' => ['nullable', 'array'],
            'headers' => ['nullable', 'array'],
            'cache_key' => ['nullable', 'string', 'max:255'],
            'cache_duration' => ['required', 'integer', 'min:60', 'max:86400'], // 1 minute to 24 hours
            'auto_sync' => ['boolean'],
            'sync_frequency' => ['nullable', 'string', 'max:100'],
            'filters' => ['nullable', 'array'],
            'transformations' => ['nullable', 'array'],
            'max_entries' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', 'string', 'in:' . self::STATUS_ACTIVE . ',' . self::STATUS_INACTIVE . ',' . self::STATUS_ERROR],
            'health_check' => ['nullable', 'array'],
            'created_by' => ['required', 'integer', 'exists:users,id'],
            'updated_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get validation rules for datasource creation.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function createRules(): array
    {
        return array_merge(self::rules(), [
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:' . self::TYPE_JSON . ',' . self::TYPE_CSV . ',' . self::TYPE_API . ',' . self::TYPE_DATABASE . ',' . self::TYPE_CUSTOM],
            'config' => ['required', 'array'],
            'created_by' => ['required', 'integer', 'exists:users,id'],
        ]);
    }

    /**
     * Get validation rules for datasource update.
     *
     * @param int|null $datasourceId
     * @return array<string, array<int, string>|string>
     */
    public static function updateRules(?int $datasourceId = null): array
    {
        $rules = self::rules();
        
        // Make created_by optional for updates
        $rules['created_by'] = ['nullable', 'integer', 'exists:users,id'];
        
        // Update slug unique rule to exclude current datasource
        if ($datasourceId) {
            $rules['slug'] = ['nullable', 'string', 'max:255', "unique:datasources,slug,{$datasourceId}", 'regex:/^[a-z0-9-]+$/'];
        } else {
            $rules['slug'] = ['nullable', 'string', 'max:255', 'unique:datasources,slug', 'regex:/^[a-z0-9-]+$/'];
        }
        
        return $rules;
    }

    /**
     * Get validation rules for JSON type datasource config.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function jsonConfigRules(): array
    {
        return [
            'config.url' => ['nullable', 'string', 'url', 'max:500'],
            'config.data' => ['nullable', 'array'],
        ];
    }

    /**
     * Get validation rules for CSV type datasource config.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function csvConfigRules(): array
    {
        return [
            'config.url' => ['required', 'string', 'url', 'max:500'],
            'config.delimiter' => ['nullable', 'string', 'max:1'],
            'config.enclosure' => ['nullable', 'string', 'max:1'],
            'config.escape' => ['nullable', 'string', 'max:1'],
        ];
    }

    /**
     * Get validation rules for API type datasource config.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function apiConfigRules(): array
    {
        return [
            'config.url' => ['required', 'string', 'url', 'max:500'],
            'config.method' => ['nullable', 'string', 'in:GET,POST,PUT,DELETE'],
            'config.params' => ['nullable', 'array'],
            'config.timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            'config.data_path' => ['nullable', 'string', 'max:255'],
            'auth_config.type' => ['nullable', 'string', 'in:bearer,basic,api_key'],
            'auth_config.token' => ['nullable', 'string', 'max:500'],
            'auth_config.username' => ['nullable', 'string', 'max:255'],
            'auth_config.password' => ['nullable', 'string', 'max:255'],
            'auth_config.header' => ['nullable', 'string', 'max:100'],
            'auth_config.key' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get validation rules for database type datasource config.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function databaseConfigRules(): array
    {
        return [
            'config.connection' => ['required', 'string', 'max:100'],
            'config.query' => ['required', 'string', 'max:2000'],
            'config.bindings' => ['nullable', 'array'],
        ];
    }

    /**
     * Get validation rules for datasource sync configuration.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function syncConfigRules(): array
    {
        return [
            'auto_sync' => ['boolean'],
            'sync_frequency' => ['nullable', 'string', 'in:hourly,daily,weekly'],
            'cache_duration' => ['required', 'integer', 'min:60', 'max:86400'],
            'max_entries' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * Get validation rules for datasource filters.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function filtersRules(): array
    {
        return [
            'filters' => ['nullable', 'array'],
            'filters.*.field' => ['required', 'string', 'max:100'],
            'filters.*.operator' => ['required', 'string', 'in:=,!=,>,<,>=,<=,contains,starts_with,ends_with'],
            'filters.*.value' => ['required'],
        ];
    }

    /**
     * Get validation rules for datasource transformations.
     *
     * @return array<string, array<int, string>|string>
     */
    public static function transformationsRules(): array
    {
        return [
            'transformations' => ['nullable', 'array'],
            'transformations.*.field' => ['required', 'string', 'max:100'],
            'transformations.*.type' => ['required', 'string', 'in:uppercase,lowercase,trim,date_format,number_format'],
            'transformations.*.format' => ['nullable', 'string', 'max:100'],
            'transformations.*.decimals' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Datasource $datasource) {
            if (!$datasource->cache_key) {
                $datasource->cache_key = "datasource:{$datasource->uuid}:data";
            }
        });
    }
}