<?php

namespace App\Support;

class CardDeliveryAccounts
{
    /**
     * Regional accounts that can only access the insurance-card delivery page.
     *
     * @return list<array{name: string, email: string, password: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'المنطقة الشرقية',
                'email' => 'east.delivery@smartcare.com.ly',
                'password' => 'East-Delivery!2026',
            ],
            [
                'name' => 'المنطقة الغربية',
                'email' => 'west.delivery@smartcare.com.ly',
                'password' => 'West-Delivery!2026',
            ],
        ];
    }
}
