<?php

namespace App\Services\Payment\Support;

/**
 * Converts between integer minor units (halalas, fils) and the decimal amounts some providers expect.
 */
final class Currency
{
    /**
     * ISO-4217 currencies with three decimal places; everything else here uses two.
     */
    private const THREE_DECIMALS = ['KWD', 'BHD', 'OMR', 'JOD', 'IQD', 'LYD', 'TND'];

    public static function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), self::THREE_DECIMALS, true) ? 3 : 2;
    }

    /**
     * 12345 halalas => "123.45"; 12345 fils => "12.345". Returned as a string to avoid float drift.
     */
    public static function toDecimal(int $minor, string $currency): string
    {
        $decimals = self::decimals($currency);

        return number_format($minor / (10 ** $decimals), $decimals, '.', '');
    }

    /**
     * "123.45" SAR => 12345. Rounds to the nearest minor unit.
     */
    public static function toMinor(float | int | string $amount, string $currency): int
    {
        return (int) round(((float) $amount) * (10 ** self::decimals($currency)));
    }
}
