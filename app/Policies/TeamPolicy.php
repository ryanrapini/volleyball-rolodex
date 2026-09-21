<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Team $team): bool
    {
        return $this->owns($user, $team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Team $team): bool
    {
        return $this->owns($user, $team);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->owns($user, $team);
    }

    /**
     * Compare keys as strings, so the check holds whatever the driver hands back.
     */
    private function owns(User $user, Team $team): bool
    {
        return (string) $team->user_id === (string) $user->getKey();
    }
}
