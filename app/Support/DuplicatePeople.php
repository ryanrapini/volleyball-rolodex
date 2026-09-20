<?php

namespace App\Support;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/*
 * "Is this the same person twice?" The manual form and the assistant both have
 * to ask it, so the rules live here and cannot drift apart.
 *
 * The name comparison is deliberately forgiving: the same name punctuated
 * differently, the same words in another order, one name contained in the other
 * ("Marcus" against "Marcus Hale"), and a typo — which is what catches a
 * transposition like "Marcsu".
 */
class DuplicatePeople
{
    public function __construct(private readonly User $user)
    {
    }

    /**
     * People who look like the same person, matched on name or phone number.
     *
     * @return Collection<int, Person>
     */
    public function matches(string $name, ?string $phone = null, ?Person $except = null): Collection
    {
        return $this->byName($name, $except)
            ->merge($this->byPhone($phone, $except))
            ->unique('id')
            ->take(5)
            ->values();
    }

    /**
     * @return Collection<int, Person>
     */
    public function byName(string $name, ?Person $except = null): Collection
    {
        $needle = $this->normaliseName($name);

        if ($needle === '') {
            return collect();
        }

        return $this->others($except)
            ->get()
            ->filter(function (Person $person) use ($needle): bool {
                $candidate = $this->normaliseName($person->name);

                if ($candidate === '') {
                    return false;
                }

                // Same name, however it was punctuated or capitalised.
                if ($candidate === $needle) {
                    return true;
                }

                // The same words in a different order.
                if ($this->wordSet($candidate) === $this->wordSet($needle)) {
                    return true;
                }

                $shortest = min(mb_strlen($candidate), mb_strlen($needle));

                // One name contains the other: "Marcus" against "Marcus Hale".
                if ($shortest >= 3 && (str_contains($candidate, $needle) || str_contains($needle, $candidate))) {
                    return true;
                }

                // A typo: short names get one character of slack, longer ones two,
                // which is what catches a transposition ("Marcsu" for "Marcus").
                return $shortest >= 5 && levenshtein($candidate, $needle) <= ($shortest <= 8 ? 1 : 2);
            })
            ->values();
    }

    /**
     * @return Collection<int, Person>
     */
    public function byPhone(?string $phone, ?Person $except = null): Collection
    {
        $digits = $this->digits($phone);

        // Shorter than this is not a phone number worth matching on.
        if (mb_strlen($digits) < 7) {
            return collect();
        }

        return $this->others($except)
            ->where('phone_digits', $digits)
            ->get();
    }

    /**
     * The user's people, minus the record being edited.
     */
    private function others(?Person $except): Builder
    {
        // The relation already carries the user_id constraint; take its builder
        // so the return type does not depend on which branch runs.
        $query = $this->user->people()->getQuery();

        if ($except) {
            $query->whereKeyNot($except->getKey());
        }

        return $query;
    }

    public function normaliseName(?string $name): string
    {
        $letters = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', mb_strtolower((string) $name)) ?? '';
        $collapsed = preg_replace('/\s+/', ' ', $letters) ?? '';

        return trim($collapsed);
    }

    public function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function wordSet(string $normalised): array
    {
        $words = array_values(array_unique(explode(' ', $normalised)));
        sort($words);

        return $words;
    }
}
