<?php

declare(strict_types=1);

namespace Habib\LaravelCrud\Helper;

use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileCannotBeAdded;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaUploader
{
    /**
     * Upload a file to media library
     *
     * @param  HasMedia&InteractsWithMedia  $model
     * @param  UploadedFile|Media|string|array<int, UploadedFile>|null  $file
     *
     * @throws FileCannotBeAdded
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */
    public static function upload(
        HasMedia $model,
        UploadedFile|Media|string|array|null $file,
        string $collection = 'default',
        ?string $name = null,
        bool $autoClear = true
    ): Media|string|null {
        if ($file === null) {
            return null;
        }

        if (is_string($file) && str_starts_with($file, url('/'))) {
            return $file;
        }

        if (is_string($file)) {
            $urls = [
                url('/'),
                config('filesystems.disks.s3.url', ''),
            ];
            $file = trim($file);
            foreach ($urls as $url) {
                if (str_starts_with($file, $url)) {
                    return null;
                }
            }
        }

        return match (true) {
            $file instanceof UploadedFile => $model->copyMedia($file)->toMediaCollection($collection),
            $file instanceof Media => $model->addMediaFromUrl($file->getFullUrl())->toMediaCollection($collection),
            is_array($file) && isset($file[0]) && $file[0] instanceof UploadedFile => $model
                ->addMultipleMediaFromRequest([is_null($name) ? 'file' : $name])
                ->each(fn ($fileAdder) => $fileAdder->toMediaCollection($collection)),
            is_string($file) => self::handleStringFile($model, $file, $collection),
            default => $file
        };
    }

    /**
     * Handle string file upload
     *
     * @param  HasMedia&InteractsWithMedia  $model
     */
    private static function handleStringFile(HasMedia $model, string $file, string $collection): Media|string
    {
        if (str_starts_with($file, '/') || preg_match('/^data:*\/(w+);base64,/', $file)) {
            return $model->addMediaFromBase64("data:image/jpeg;base64,$file")
                ->toMediaCollection($collection);
        }

        if (filter_var($file, FILTER_VALIDATE_URL)) {
            return $model->addMediaFromUrl($file)->toMediaCollection($collection);
        }

        return $file;
    }
}
