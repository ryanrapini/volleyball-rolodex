<?php

namespace App\Models;

use App\Enums\CategoryType;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'position', 'default_filter', 'show_on_card', 'show_name_on_card', 'colour'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'position' => 'integer',
            'default_filter' => 'array',
            'show_on_card' => 'boolean',
            'show_name_on_card' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CategoryOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(CategoryOption::class)->orderBy('position')->orderBy('label');
    }

    /**
     * Answers recorded against this category, across every person.
     *
     * @return HasMany<PersonCategoryValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(PersonCategoryValue::class);
    }
}
