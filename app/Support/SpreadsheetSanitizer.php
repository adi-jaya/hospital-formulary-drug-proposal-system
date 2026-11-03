<?php

declare(strict_types=1);

namespace App\Support;

class SpreadsheetSanitizer
{
    /**
     * @var list<string>
     */
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Escapes formulas to prevent CSV/Spreadsheet Formula Injection.
     */
    public static function sanitizeCell(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $firstChar = mb_substr($value, 0, 1);

        if (in_array($firstChar, self::DANGEROUS_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
