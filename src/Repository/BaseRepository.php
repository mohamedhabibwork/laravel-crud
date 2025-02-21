<?php

declare(strict_types=1);

namespace Habib\LaravelCrud\Repository;

use Habib\LaravelCrud\Helper\Helper;
use Habib\LaravelCrud\Helper\MediaUploader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

/**
 * Abstract Base Repository Class
 *
 * @template TModel of Model
 *
 * @template-implements BaseRepositoryInterface<TModel>
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(
        protected readonly array $filters = [],
        protected readonly array $files = [],
        protected readonly string $key = 'crud',
        protected readonly ?Request $storeRequest = null,
        protected readonly ?Request $updateRequest = null
    ) {}

    /**
     * @return TModel
     */
    abstract public function getModel(): Model;

    /**
     * @param  TModel  $model
     * @return array<string, mixed>
     */
    public function fields(Model $model): array
    {
        if (method_exists($model, 'getForm')) {
            /** @var callable():array<string,mixed> $getForm */
            $getForm = [$model, 'getForm'];

            return $getForm();
        }

        if (method_exists($this, 'getForm')) {
            /** @var callable(Model):array<string,mixed> $getForm */
            $getForm = [$this, 'getForm'];

            return $getForm($model);
        }

        return $model->getFillable();
    }

    public function getKey(): string
    {
        return $this->key;
    }

    protected function getRequest(): Request
    {
        return request();
    }

    /**
     * @return Builder<TModel>
     */
    public function filter(): Builder
    {
        $filters = method_exists($this, 'getFilters') ? $this->getFilters() : $this->filters;

        /** @var Builder<TModel> $query */
        $query = $this->getModel()->query();

        foreach ($filters as $filter) {
            $parts = explode('#', $filter);
            if (empty($parts[0])) {
                continue;
            }

            $name = $parts[0];
            $op = $parts[1] ?? '=';
            $alias = $parts[2] ?? $name;
            $value = $parts[3] ?? $this->getRequest()->get($alias);

            if (is_null($value) && ! in_array($op, ['null', 'notnull'], true)) {
                continue;
            }

            $this->executeQuery($query, $op, $name, $value);
        }

        return $query;
    }

    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function executeQuery(Builder $query, string $op, string $name, mixed $value): Builder
    {
        return match ($op) {
            'like', 'ilike', 'rlike' => $query->where($query->qualifyColumn($name), 'like', "%$value%"),
            'notlike', 'notilike', 'notrlike' => $query->where($query->qualifyColumn($name), 'not like', "%$value%"),
            'null' => $query->whereNull($query->qualifyColumn($name)),
            'notnull' => $query->whereNotNull($query->qualifyColumn($name)),
            'in' => $query->whereIn($query->qualifyColumn($name), explode(',', $value)),
            'notin' => $query->whereNotIn($query->qualifyColumn($name), explode(',', $value)),
            'between' => $query->whereBetween($query->qualifyColumn($name), explode(',', $value)),
            'notbetween' => $query->whereNotBetween($query->qualifyColumn($name), explode(',', $value)),
            default => $query->where($query->qualifyColumn($name), $op, $value),
        };
    }

    /**
     * @param  TModel&HasMedia  $model
     *
     * @throws FileCannotBeAdded
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    protected function uploadFiles(Model&HasMedia $model, array $data, bool $autoClear = true): void
    {
        $files = method_exists($this, 'getFiles') ? $this->getFiles() : $this->files;
        foreach ($files as $fileKey) {
            if (! isset($data[$fileKey])) {
                continue;
            }
            MediaUploader::upload($model, $data[$fileKey], $fileKey, $fileKey, autoClear: $autoClear);
        }
    }

    /**
     * @param  array<string>  $columns
     * @param  array<string>  $with
     * @return TModel|null
     */
    public function find(Model|int|string $id, array $columns = ['*'], array $with = [], bool $lock = false): ?Model
    {
        return $this->getModel()
            ->newModelQuery()
            ->with($with)
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->find($id, $columns);
    }

    public function getViewPrefix(string $view): string
    {
        return ! view()->exists("dashboard::{$this->key}.{$view}") ? "dashboard::crud.{$view}" : "dashboard::{$this->key}.{$view}";
    }

    public function getBaseViewPrefix(string $view): string
    {
        return trim("dashboard::{$this->key}.{$view}", '.');
    }

    public function getRoutePrefix(string $route, ?string $key = null): string
    {
        $key ??= str($this->key)->plural()->snake()->toString();

        return trim('dashboard.'.($key ? "$key." : '').$route, '.');
    }

    public function getTranslate(string $key): string
    {
        return "dashboard.{$this->key}.{$key}";
    }

    public function getAuthorize(string $key): string
    {
        return Helper::getAuthorizeModel($this->getModel()::class, $key);
    }

    /**
     * @return array<string>
     */
    public function getSearchKeys(): array
    {
        return [
            'id',
            'created_at',
        ];
    }

    /**
     * Modify the search query
     *
     * @param  QueryBuilder<TModel>|Builder<TModel>  $query
     * @return QueryBuilder<TModel>|Builder<TModel>
     */
    public function setSearchQuery(QueryBuilder|Builder $query): QueryBuilder|Builder
    {
        return $query;
    }

    /**
     * @param  array<string>  $columns
     * @param  array<string>  $with
     * @return Collection<int, TModel>
     */
    public function all(array $columns = ['*'], array $with = []): Collection
    {
        return $this->filter()->with($with)->get($columns);
    }

    /**
     * @param  array<string>  $columns
     * @param  array<string>  $with
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], array $with = []): LengthAwarePaginator
    {
        return $this->filter()->with($with)->paginate($perPage, $columns);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function create(array $data): Model
    {
        /** @var TModel $model */
        $model = $this->getModel()->newInstance($data);
        $model->save();

        if ($model instanceof HasMedia && ! empty($this->files)) {
            $this->uploadFiles($model, $data);
        }

        return $model;
    }

    /**
     * @param  TModel|int|string  $id
     * @param  array<string, mixed>  $data
     * @return TModel|null
     */
    public function update($id, array $data = []): ?Model
    {
        $model = $id instanceof Model ? $id : $this->find($id);
        if (! $model) {
            return null;
        }

        $model->fill($data);
        $model->save();

        if ($model instanceof HasMedia && ! empty($this->files)) {
            $this->uploadFiles($model, $data);
        }

        return $model;
    }

    /**
     * @param  TModel|int|string  $id
     */
    public function delete($id): bool
    {
        $model = $id instanceof Model ? $id : $this->find($id);
        if (! $model) {
            return false;
        }

        return $model->delete();
    }

    public function getStoreRequest(): ?Request
    {
        return $this->storeRequest;
    }

    public function getUpdateRequest(): ?Request
    {
        return $this->updateRequest;
    }
}
