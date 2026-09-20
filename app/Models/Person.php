<?php

namespace App\Models;

use App\Support\PersonPhotos;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'phone', 'email', 'notes'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Keep the searchable digits in step with whatever format the user typed,
     * and take the photo file with the record when it goes.
     */
    protected static function booted(): void
    {
        static::saving(function (Person $person): void {
            $person->phone_digits = self::phoneDigits($person->phone);
        });

        static::deleted(function (Person $person): void {
            PersonPhotos::forget($person->photo_path);
        });
    }

    /**
     * Reduce a phone number to searchable digits, or null when there are none.
     */
    public static function phoneDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return $digits === '' ? null : $digits;
    }
}
