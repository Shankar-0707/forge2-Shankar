<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Pending',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
