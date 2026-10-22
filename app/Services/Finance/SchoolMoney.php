<?php

namespace App\Services\Finance;

/** Convertir les montants FCFA en centimes pour éviter les calculs flottants. */
class SchoolMoney
{
    public function toCents(string|int|float $amount): int
    {
        $normalized = trim((string) $amount);

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            $normalized = number_format((float) $amount, 2, '.', '');
            preg_match('/^(\d+)\.(\d{2})$/', $normalized, $matches);
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return ((int) $matches[1] * 100) + (int) $fraction;
    }

    public function format(int $amountInCents): string
    {
        return sprintf('%d.%02d', intdiv($amountInCents, 100), $amountInCents % 100);
    }
}
