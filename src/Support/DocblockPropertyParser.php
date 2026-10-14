<?php

declare(strict_types=1);

namespace Indy2kro\ValidateModels\Support;

/**
 * Parse @property, @property-read, @property-write tags from a class docblock.
 *
 * Produces:
 *  - name: string (without $)
 *  - types: list<string> (union members without leading '?' and without 'null'; generics are kept, e.g. "array<string, int>")
 *  - nullable: bool (true if '?' or 'null' present)
 *  - raw: original type string
 *
 * @example "@property ?int $age"
 */
final class DocblockPropertyParser
{
    /**
     * @return array<string, array{name: string, types: list<string>, nullable: bool, raw: string}>
     */
    public function parse(?string $docblock): array
    {
        if ($docblock === null || $docblock === '') {
            return [];
        }

        $lines = preg_split('/\R/u', $docblock) ?: [];
        $out   = [];

        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*");

            if (!preg_match('/^@property(?:-read|-write)?\s+(\S.*)$/iu', $line, $m)) {
                continue;
            }

            $typeString = $this->readType($m[1]);
            $rest       = substr($m[1], \strlen($typeString));

            if ($typeString === '' || !preg_match('/^\s+\$([A-Za-z_]\w*)/', $rest, $n)) {
                continue;
            }

            $name     = $n[1];
            $nullable = false;
            $types    = [];

            foreach ($this->splitUnion($typeString) as $t) {
                $t = ltrim(trim($t), '\\');
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

    /** The leading type expression: everything up to the first whitespace outside brackets. */
    private function readType(string $text): string
    {
        $depth = 0;
        $len   = \strlen($text);

        for ($i = 0; $i < $len; $i++) {
            $c = $text[$i];

            if ($c === '<' || $c === '(' || $c === '{' || $c === '[') {
                $depth++;
            } elseif ($c === '>' || $c === ')' || $c === '}' || $c === ']') {
                $depth = max(0, $depth - 1);
            } elseif ($depth === 0 && ($c === ' ' || $c === "\t")) {
                return substr($text, 0, $i);
            }
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private function splitUnion(string $type): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $len   = \strlen($type);

        for ($i = 0; $i < $len; $i++) {
            $c = $type[$i];

            if ($c === '<' || $c === '(' || $c === '{' || $c === '[') {
                $depth++;
            } elseif ($c === '>' || $c === ')' || $c === '}' || $c === ']') {
                $depth = max(0, $depth - 1);
            } elseif ($c === '|' && $depth === 0) {
                $parts[] = substr($type, $start, $i - $start);
                $start   = $i + 1;
            }
        }
        $parts[] = substr($type, $start);

        return $parts;
    }
}
