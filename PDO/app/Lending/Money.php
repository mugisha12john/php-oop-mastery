<?php
require_once dirname(__DIR__, 1) . '/autoload.php';
final class Money
{
    public static function cents(string $value, bool $positive = true): int
    {
        if (!preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,2})?$/D', $value)) {
            throw new DomainException('Enter a valid amount with at most two decimal places.');
        }
        $parts = explode('.', $value);
        $cents = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        if ($positive && $cents <= 0) {
            throw new DomainException('The amount must be greater than zero.');
        }
        return $cents;
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function interest(int $principal, string $rate): int
    {
        $basisPoints = self::cents($rate, false);
        return intdiv($principal, 10000) * $basisPoints + intdiv(($principal % 10000) * $basisPoints + 5000, 10000);
    }
}
