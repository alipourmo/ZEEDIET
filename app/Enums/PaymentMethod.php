<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a payment was made (spec section 6.5).
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Pos = 'pos';
    case Other = 'other';

    public function label(): string
    {
        return __('payments.methods.'.$this->value);
    }
}
