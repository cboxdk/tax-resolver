<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * One state's postal boundary data, read from the wire format the register ships:
 * `<state>.json` (the ZIP and ZIP+4 layers) and, where it exists, `<state>.streets.json`
 * (the rooftop street layer). The two carry their own authority-set tables — the street
 * file is self-contained — so they are held apart here.
 *
 * The resolution semantics are the format, not this class's invention: first match
 * wins, spans are held narrowest-first, an empty set differs from no match. They match
 * the register's own build-time reader exactly, which is the whole point of a shared
 * package — the numbers cannot disagree.
 */
readonly class BoundaryData
{
    /**
     * @param  list<list<string>>  $sets  authority sets for the ZIP/range layers
     * @param  array<array-key, list<array{0: string, 1: string, 2: int}>>  $zip
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: int}>  $ranges
     * @param  list<list<string>>  $streetSets  authority sets for the street layer
     * @param  array<array-key, array<string, list<array{0: int, 1: int, 2: string, 3: int}>>>  $street
     */
    public function __construct(
        public array $sets = [],
        public array $zip = [],
        public array $ranges = [],
        public array $streetSets = [],
        public array $street = [],
    ) {}

    /**
     * @param  array<string, mixed>  $main  the decoded `<state>.json`
     * @param  array<string, mixed>|null  $streets  the decoded `<state>.streets.json`, when present
     */
    public static function fromArtifacts(array $main, ?array $streets = null): self
    {
        return new self(
            self::sets($main['sets'] ?? null),
            self::zip($main['zip'] ?? null),
            self::ranges($main['ranges'] ?? null),
            self::sets($streets['sets'] ?? null),
            self::street($streets['street'] ?? null),
        );
    }

    /**
     * The authorities at a house on a street, or null when the street layer is silent.
     * First match, narrowest-first — the tightest range covering the house wins.
     *
     * @return list<string>|null
     */
    public function resolveStreet(string $zip5, string $streetKey, int $house): ?array
    {
        foreach ($this->street[$zip5][$streetKey] ?? [] as [$low, $high, $parity, $set]) {
            if ($house < $low || $house > $high) {
                continue;
            }

            $matches = match ($parity) {
                'O' => $house % 2 === 1,
                'E' => $house % 2 === 0,
                default => true,
            };

            if ($matches) {
                return $this->streetSets[$set] ?? [];
            }
        }

        return null;
    }

    /**
     * The authorities at a ZIP+4 (or a whole ZIP when the add-on is empty), and whether
     * the match was a targeted span rather than the whole-ZIP fallback — which is what
     * tells a ZIP+4 answer from a ZIP-5 one.
     *
     * @return array{0: list<string>|null, 1: bool}
     */
    public function resolveZip(string $zip5, string $plus4 = ''): array
    {
        $addOn = str_pad($plus4, 4, '0', STR_PAD_LEFT);

        foreach ($this->zip[$zip5] ?? [] as [$from, $to, $set]) {
            if ($addOn >= $from && $addOn <= $to) {
                return [$this->sets[$set] ?? [], $from !== '0000' || $to !== '9999'];
            }
        }

        // Ranges are whole-ZIP stretches, consulted only after the per-ZIP spans, and
        // read as ZIP-5 precision.
        foreach ($this->ranges as [$zipFrom, $zipTo, $from, $to, $set]) {
            if ($zip5 >= $zipFrom && $zip5 <= $zipTo && $addOn >= $from && $addOn <= $to) {
                return [$this->sets[$set] ?? [], false];
            }
        }

        return [null, false];
    }

    /**
     * @return list<list<string>>
     */
    private static function sets(mixed $value): array
    {
        $sets = [];

        foreach (is_array($value) ? $value : [] as $set) {
            $codes = [];

            foreach (is_array($set) ? $set : [] as $code) {
                if (is_string($code)) {
                    $codes[] = $code;
                }
            }

            $sets[] = $codes;
        }

        return $sets;
    }

    /**
     * @return array<array-key, list<array{0: string, 1: string, 2: int}>>
     */
    private static function zip(mixed $value): array
    {
        $out = [];

        foreach (is_array($value) ? $value : [] as $zip => $spans) {
            $list = [];

            foreach (is_array($spans) ? $spans : [] as $span) {
                if (is_array($span) && isset($span[0], $span[1], $span[2])) {
                    $list[] = [self::str($span[0]), self::str($span[1]), self::int($span[2])];
                }
            }

            $out[$zip] = $list;
        }

        return $out;
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: int}>
     */
    private static function ranges(mixed $value): array
    {
        $out = [];

        foreach (is_array($value) ? $value : [] as $range) {
            if (is_array($range) && isset($range[0], $range[1], $range[2], $range[3], $range[4])) {
                $out[] = [self::str($range[0]), self::str($range[1]), self::str($range[2]), self::str($range[3]), self::int($range[4])];
            }
        }

        return $out;
    }

    /**
     * @return array<array-key, array<string, list<array{0: int, 1: int, 2: string, 3: int}>>>
     */
    private static function street(mixed $value): array
    {
        $out = [];

        foreach (is_array($value) ? $value : [] as $zip => $streets) {
            $byStreet = [];

            foreach (is_array($streets) ? $streets : [] as $key => $spans) {
                $list = [];

                foreach (is_array($spans) ? $spans : [] as $span) {
                    if (is_array($span) && isset($span[0], $span[1], $span[2], $span[3])) {
                        $list[] = [self::int($span[0]), self::int($span[1]), self::str($span[2]), self::int($span[3])];
                    }
                }

                $byStreet[(string) $key] = $list;
            }

            $out[$zip] = $byStreet;
        }

        return $out;
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
