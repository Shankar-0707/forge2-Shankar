<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Agent = 'agent';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Agent => 'Agent',
            self::User => 'User',
        };
    }

    public static function assignable(): array
    {
        return [self::Admin->value, self::Agent->value];
    }
}
