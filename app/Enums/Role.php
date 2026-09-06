<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';

    /**
     * Human-readable label in Portuguese.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Manager => 'Gestor',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
