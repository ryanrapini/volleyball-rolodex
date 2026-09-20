<?php

namespace App\Models;

use Database\Factories\PersonCategoryValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's answer to one category: either a chosen option, or a yes/no.
 */
#[Fillable(['category_id', 'category_option_id', 'value'])]
class PersonCategoryValue extends Model
{
    /** @use HasFactory<PersonCategoryValueFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'boolean'];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<CategoryOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(CategoryOption::class, 'category_option_id');
    }

    /**
     * The words this answer puts on a card: "BB", "Setter", or "Yes".
     */
    public function label(): ?string
    {
        if ($this->category_option_id !== null) {
            return $this->option?->label;
        }

        return $this->value === true ? 'Yes' : null;
    }
}
