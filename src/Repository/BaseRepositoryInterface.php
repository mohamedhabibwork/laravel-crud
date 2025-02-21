<?php

declare(strict_types=1);

namespace Habib\LaravelCrud\Repository;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Interface BaseRepositoryInterface
 *
 * @template TModel of Model
 */
interface BaseRepositoryInterface
{
    /**
     * Get the view prefix for the repository
     */
    public function getViewPrefix(string $view): string;

    /**
     * Get the base view prefix for the repository
     */
    public function getBaseViewPrefix(string $view): string;

    /**
     * Get the route prefix for the repository
     */
    public function getRoutePrefix(string $route, ?string $key = null): string;

    /**
     * Get the translation key
     */
    public function getTranslate(string $key): string;

    /**
     * Find a model by its primary key
     *
     * @param  TModel|int|string  $id
     * @param  array<string>  $columns
     * @param  array<string>  $with
     * @return TModel|null
     */
    /**
     * Find a model by its primary key
     *
     * @param  TModel|int|string  $id
     * @param  array<string>  $columns
     * @param  array<string>  $with
     * @return TModel|null
     */
    public function find(Model|int|string $id, array $columns = ['*'], array $with = [], bool $lock = false): ?Model;

    /**
     * Get the authorization key for the model
     */
    public function getAuthorize(string $key): string;

    /**
     * Get the repository key
     */
    public function getKey(): string;

    /**
     * Get the searchable keys for the model
     *
     * @return array<string>
     */
    public function getSearchKeys(): array;

    /**
     * Modify the search query
     *
     * @param  QueryBuilder<TModel>|Builder<TModel>  $query
     * @return QueryBuilder<TModel>|Builder<TModel>
     */
    public function setSearchQuery(QueryBuilder|Builder $query): QueryBuilder|Builder;

    /**
     * Get the model instance
     *
     * @return TModel
     */
    public function getModel(): Model;

    /**
     * Get the form fields for the model
     *
     * @param  TModel  $model
     * @return array<string, mixed>
     */
    public function fields(Model $model): array;

    /**
     * Apply filters to the query
     *
     * @return Builder<TModel>
     */
    public function filter(): Builder;

    /**
     * Get all records with optional filtering
     *
     * @param  array<string>  $columns
     * @param  array<string>  $with
     * @return Collection<int, TModel>
     */
    public function all(array $columns = ['*'], array $with = []): Collection;

    /**
     * Paginate the filtered results
     *
     * @param  array<string>  $columns
     * @param  array<string>  $with
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], array $with = []): LengthAwarePaginator;

    /**
     * Create a new model instance
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): Model;

    /**
     * Update an existing model instance
     *
     * @param  TModel|int|string  $id
     * @param  array<string, mixed>  $data
     * @return TModel|null
     */
    public function update($id, array $data = []): ?Model;

    /**
     * Delete a model instance
     *
     * @param  TModel|int|string  $id
     */
    public function delete($id): bool;

    /**
     * Get the request instance for store operations
     */
    public function getStoreRequest(): ?Request;

    /**
     * Get the request instance for update operations
     */
    public function getUpdateRequest(): ?Request;
}
