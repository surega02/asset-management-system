<?php

namespace App\Support;

use DateTimeImmutable;
use InvalidArgumentException;

enum AssetAttributeType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';

    public function hasBounds(): bool
    {
        return in_array($this, [self::Text, self::Integer, self::Decimal], true);
    }

    public function normalize(mixed $value, array $bounds = [], array $options = []): string|int|bool
    {
        if ($this === self::Boolean) {
            return match ($value) {
                true, 1, '1', 'true' => true,
                false, 0, '0', 'false' => false,
                default => throw new InvalidArgumentException,
            };
        }

        if ($this === self::Integer && is_int($value)) {
            $value = (string) $value;
        }
        if ($this === self::Decimal && is_int($value)) {
            $value = (string) $value;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException;
        }

        $value = trim($value);

        return match ($this) {
            self::Text => $this->checkedText($value, $bounds),
            self::Integer, self::Decimal => $this->checkedNumber($value, $bounds),
            self::Date => $this->checkedDate($value),
            self::Select => in_array($value, $options, true) ? $value : throw new InvalidArgumentException,
            self::Boolean => throw new InvalidArgumentException,
        };
    }

    public function bound(string $value): int
    {
        if ($this === self::Text) {
            if (! preg_match('/\A[0-9]{1,4}\z/', $value) || (int) $value > 2000) {
                throw new InvalidArgumentException;
            }

            return (int) $value;
        }

        return $this->number($value);
    }

    private function checkedText(string $value, array $bounds): string
    {
        if (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            throw new InvalidArgumentException;
        }
        $this->checkRange(mb_strlen($value), $bounds + ['max' => '2000']);

        return $value;
    }

    private function checkedNumber(string $value, array $bounds): string|int
    {
        $number = $this->number($value);
        $this->checkRange($number, $bounds);
        if ($this === self::Integer) {
            return $number;
        }
        // Canonical decimal strings round-trip JSON without floating-point arithmetic.
        $absolute = abs($number);
        $fraction = rtrim(str_pad((string) ($absolute % 1000000), 6, '0', STR_PAD_LEFT), '0');

        return ($number < 0 ? '-' : '').intdiv($absolute, 1000000).($fraction === '' ? '' : '.'.$fraction);
    }

    private function number(string $value): int
    {
        $pattern = $this === self::Integer ? '/\A(-?)([0-9]{1,12})\z/' : '/\A(-?)([0-9]{1,12})(?:\.([0-9]{1,6}))?\z/';
        if (! preg_match($pattern, $value, $parts)) {
            throw new InvalidArgumentException;
        }
        $sign = $parts[1] === '-' ? -1 : 1;
        $whole = (int) $parts[2];
        if ($this === self::Integer) {
            return $sign * $whole;
        }

        return $sign * ($whole * 1000000 + (int) str_pad($parts[3] ?? '', 6, '0'));
    }

    private function checkRange(int $number, array $bounds): void
    {
        if ((isset($bounds['min']) && $number < $this->bound($bounds['min']))
            || (isset($bounds['max']) && $number > $this->bound($bounds['max']))) {
            throw new InvalidArgumentException;
        }
    }

    private function checkedDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value || str_starts_with($value, '0000-')) {
            throw new InvalidArgumentException;
        }

        return $value;
    }
}
