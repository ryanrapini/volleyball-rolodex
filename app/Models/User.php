<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The admin flag is never mass assignable: it is set on the model directly,
     * so a profile update can never grant it.
     *
     * @var list<string>
     */
    protected $appends = ['can_use_ai'];

    /**
     * Approval gates the AI assistant only. Admins always have it.
     */
    public function canUseAi(): bool
    {
        return $this->is_admin === true || $this->ai_approved_at !== null;
    }

    /**
     * Exposed to the browser so the assistant button can be hidden, rather than
     * shown and then refused.
     */
    public function getCanUseAiAttribute(): bool
    {
        return $this->canUseAi();
    }

    /**
     * The people in this user's private rolodex.
     *
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /**
     * The categories this user classifies people with.
     *
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ai_approved_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
