<?php

namespace App\Enums;

enum CategoryType: string
{
    /** Pick exactly one of the choices. */
    case Single = 'single';

    /** Pick any number of the choices. */
    case Multiple = 'multiple';

    /** A plain yes / no. */
    case Boolean = 'boolean';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Pick one',
            self::Multiple => 'Pick any',
            self::Boolean => 'Yes / no',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Single => 'e.g. Skill level — A, BB, B',
            self::Multiple => 'e.g. Position — setter, middle, libero',
            self::Boolean => 'e.g. Under 6 ft, can set',
        };
    }

    /**
     * Boolean categories hold no choices; the others need at least one.
     */
    public function hasOptions(): bool
    {
        return $this !== self::Boolean;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
