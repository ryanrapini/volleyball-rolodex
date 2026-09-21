<?php

namespace App\Models;

use App\Enums\TeamResponse;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'tournament_date'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tournament_date' => 'date'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TeamMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class)->orderBy('position');
    }

    /**
     * The people themselves, for pages that only need names and faces.
     *
     * @return BelongsToMany<Person, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'team_members')
            ->withPivot(['id', 'response', 'position'])
            ->withTimestamps();
    }

    /**
     * How many said each thing.
     *
     * @return array<string, int>
     */
    public function tally(): array
    {
        $counts = $this->members()
            ->selectRaw('response, count(*) as total')
            ->groupBy('response')
            ->pluck('total', 'response')
            ->all();

        return [
            'waiting' => (int) ($counts[TeamResponse::Waiting->value] ?? 0),
            'yes' => (int) ($counts[TeamResponse::Yes->value] ?? 0),
            'no' => (int) ($counts[TeamResponse::No->value] ?? 0),
            'total' => array_sum($counts),
        ];
    }
}
