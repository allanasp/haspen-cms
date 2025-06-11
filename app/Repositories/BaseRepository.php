<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Base repository class providing common CRUD operations.
 */
abstract class BaseRepository
{
    /**
     * The model instance.
     */
    protected Model $model;

    /**
     * Create a new repository instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get all records.
     */
    /**
     * @param array<string> $columns
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->model->select($columns)->get();
    }

    /**
     * Find a record by ID.
     */
    /**
     * @param array<string> $columns
     */
    public function find(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->model->select($columns)->find($id);
    }

    /**
     * Find a record by ID or fail.
     */
    /**
     * @param array<string> $columns
     */
    public function findOrFail(int|string $id, array $columns = ['*']): Model
    {
        return $this->model->select($columns)->findOrFail($id);
    }

    /**
     * Find records by criteria.
     */
    /**
     * @param array<string, mixed> $criteria
     * @param array<string> $columns
     */
    public function findWhere(array $criteria, array $columns = ['*']): Collection
    {
        $query = $this->model->select($columns);

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->get();
    }

    /**
     * Find first record by criteria.
     */
    /**
     * @param array<string, mixed> $criteria
     * @param array<string> $columns
     */
    public function findWhereFirst(array $criteria, array $columns = ['*']): ?Model
    {
        $query = $this->model->select($columns);

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->first();
    }

    /**
     * Create a new record.
     */
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update a record.
     */
    /**
     * @param array<string, mixed> $data
     */
    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    /**
     * Update records by criteria.
     */
    /**
     * @param array<string, mixed> $criteria
     * @param array<string, mixed> $data
     */
    public function updateWhere(array $criteria, array $data): int
    {
        $query = $this->model->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->update($data);
    }

    /**
     * Delete a record.
     */
    public function delete(Model $model): bool
    {
        return $model->delete() !== false;
    }

    /**
     * Delete records by ID.
     */
    public function deleteById(int|string $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    /**
     * Delete records by criteria.
     */
    /**
     * @param array<string, mixed> $criteria
     */
    public function deleteWhere(array $criteria): int
    {
        $query = $this->model->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->delete();
    }

    /**
     * Get paginated records.
     */
    /**
     * @param array<string> $columns
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->select($columns)->paginate($perPage);
    }

    /**
     * Get records with pagination and criteria.
     */
    /**
     * @param array<string, mixed> $criteria
     * @param array<string> $columns
     */
    public function paginateWhere(array $criteria, int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        $query = $this->model->select($columns);

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get query builder.
     */
    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Count records.
     */
    public function count(): int
    {
        return $this->model->count();
    }

    /**
     * Count records by criteria.
     */
    /**
     * @param array<string, mixed> $criteria
     */
    public function countWhere(array $criteria): int
    {
        $query = $this->model->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->count();
    }

    /**
     * Check if record exists.
     */
    /**
     * @param array<string, mixed> $criteria
     */
    public function exists(array $criteria): bool
    {
        $query = $this->model->newQuery();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->exists();
    }
}
