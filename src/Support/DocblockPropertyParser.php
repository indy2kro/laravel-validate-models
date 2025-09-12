<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Support;

/**
 * Parse @property, @property-read, @property-write tags from a class docblock.
 *
 * Produces:
 *  - name: string (without $)
 *  - types: list<string> (normalized without leading '?', without 'null')
 *  - nullable: bool (true if '?' or 'null' present)
 *  - raw: original type string
 *
 * @example "@property ?int $age"
 */
final class DocblockPropertyParser
{
    /**
     * @return array<string, array{name:string, types: string[], nullable: bool, raw: string}>
     */
    public function parse(?string $docblock): array
    {
        if (!$docblock) {
            return [];
        }

        $lines = preg_split('/\R/u', $docblock) ?: [];
        $out   = [];

        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*");

            if (!preg_match('/^@property(?:-read|-write)?\s+([^\s]+)\s+\$([A-Za-z_]\w*)/iu', $line, $m)) {
                continue;
            }

            $typeString = $m[1];
            $name       = $m[2];
            $nullable   = false;
            $types      = [];

            foreach (explode('|', $typeString) as $t) {
                $t = ltrim($t, '\\');
                if ($t === '') {
                    continue;
                }

                if ($t[0] === '?') {
                    $nullable = true;
                    $t        = substr($t, 1);
                }
                if (strcasecmp($t, 'null') === 0) {
                    $nullable = true;
                    continue;
                }
                $types[] = $t;
            }

            $out[$name] = [
                'name'     => $name,
                'types'    => $types,
                'nullable' => $nullable,
                'raw'      => $typeString,
            ];
        }

        return $out;
    }
}
