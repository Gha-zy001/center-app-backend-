<?php

namespace Modules\Classroom\app\Support;

/**
 * Telegram reports "201001234567" while a teacher types "01001234567" or "+20 100 123 4567".
 * Comparing the last digits makes all of them the same number.
 */
class PhoneKey
{
    private const SIGNIFICANT_DIGITS = 10;

    public static function of(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 8 ? substr($digits, -self::SIGNIFICANT_DIGITS) : null;
    }
}
