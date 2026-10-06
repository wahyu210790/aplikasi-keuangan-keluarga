<?php

namespace App\Services;

use App\Models\ExchangeRate;

class CurrencyConversionService
{
    /**
     * Convert an amount from one currency to another.
     *
     * @param float $amount
     * @param string $fromCurrency (e.g. USD)
     * @param string $toCurrency (e.g. IDR)
     * @param string|null $date (YYYY-MM-DD)
     * @return array Calculated converted amount, rate used, and formatted values
     */
    public static function convert(float $amount, string $fromCurrency, string $toCurrency, ?string $date = null): array
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === $to) {
            return [
                'from_currency' => $from,
                'to_currency' => $to,
                'original_amount' => $amount,
                'converted_amount' => $amount,
                'rate' => 1.000000,
            ];
        }

        $query = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to);

        if ($date) {
            $query->whereDate('effective_date', '<=', $date);
        }

        $rateRecord = $query->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();

        if ($rateRecord) {
            $rate = (float) $rateRecord->rate;
        } else {
            // Check reverse rate
            $reverseQuery = ExchangeRate::where('from_currency', $to)
                ->where('to_currency', $from);
            if ($date) {
                $reverseQuery->whereDate('effective_date', '<=', $date);
            }
            $reverseRecord = $reverseQuery->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();

            if ($reverseRecord && (float) $reverseRecord->rate > 0) {
                $rate = 1.0 / (float) $reverseRecord->rate;
            } else {
                $rate = 1.0; // Fallback safe rate
            }
        }

        $converted = round($amount * $rate, 2);

        return [
            'from_currency' => $from,
            'to_currency' => $to,
            'original_amount' => $amount,
            'converted_amount' => $converted,
            'rate' => round($rate, 6),
        ];
    }
}
