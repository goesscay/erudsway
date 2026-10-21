<?php

namespace App\Modules\Commerce\Enums;

/** How a learner paid when staff record a payment taken outside the website. */
enum OfflinePaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Upi = 'upi';
    case Cheque = 'cheque';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank transfer',
            self::Upi => 'UPI',
            self::Cheque => 'Cheque',
            self::Card => 'Card (in person)',
            self::Other => 'Other',
        };
    }
}
