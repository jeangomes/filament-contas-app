<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum ExpenseScope: string
{
    case Comum = 'comum';
    case Individual = 'individual';

    public function ehComum(): bool
    {
        return $this === self::Comum;
    }
}
