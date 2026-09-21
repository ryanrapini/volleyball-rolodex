<?php

namespace App\Enums;

enum TeamResponse: string
{
    /** Picked for the team, not asked yet. */
    case Waiting = 'waiting';

    case Yes = 'yes';

    case No = 'no';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Not asked yet',
            self::Yes => 'Said yes',
            self::No => 'Said no',
        };
    }

    /**
     * Short enough for a chip.
     */
    public function short(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Yes => 'Yes',
            self::No => 'No',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
