<?php

namespace App\Support;

use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Photos live on the public disk at storage/app/public/people. Everything that
 * writes or removes one goes through here so no orphaned files are left behind.
 */
class PersonPhotos
{
    public const DISK = 'public';

    private const DIRECTORY = 'people';

    /**
     * Replace the person's photo, deleting whatever was there before.
     */
    public static function store(Person $person, UploadedFile $photo): void
    {
        $previous = $person->photo_path;

        $person->photo_path = $photo->store(self::DIRECTORY, self::DISK);
        $person->save();

        if ($previous !== $person->photo_path) {
            self::forget($previous);
        }
    }

    public static function forget(?string $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function url(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }
}
