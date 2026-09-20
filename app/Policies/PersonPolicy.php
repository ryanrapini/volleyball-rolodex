<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    /**
     * A user may only ever see their own rolodex.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Person $person): bool
    {
        return $this->owns($user, $person);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Person $person): bool
    {
        return $this->owns($user, $person);
    }

    public function delete(User $user, Person $person): bool
    {
        return $this->owns($user, $person);
    }

    /**
     * Compare keys as strings so the check holds whether the foreign key comes
     * back from the driver as an int or a UUID string.
     */
    private function owns(User $user, Person $person): bool
    {
        return (string) $person->user_id === (string) $user->getKey();
    }
}
