<?php

namespace App\Helpers;

class PhoneHelper
{
    public static function normalize(string $phone): string
    {

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '20') && strlen($phone) === 12) {
            $phone = substr($phone, 2);
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 11) {
            $phone = substr($phone, 1);
        }

        return '20'.$phone;
    }
}
