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
    /** The wire formats this reader implements. Anything else is refused, not guessed. */
    public const array SUPPORTED = [3];

    /**
     * @param  list<list<Authority>>  $sets  authority sets for the ZIP/range layers
     * @param  array<array-key, list<array{0: string, 1: string, 2: int}>>  $zip
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: int}>  $ranges
     * @param  list<list<Authority>>  $streetSets  authority sets for the street layer
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
     *
     * @throws UnsupportedFormatVersion When either artifact is written to a version this
     *                                  reader does not implement. See that class for why
     *                                  this refuses rather than degrading.
     */
    public static function fromArtifacts(array $main, ?array $streets = null): self
    {
        self::assertVersion('boundary', $main);

        if ($streets !== null) {
            self::assertVersion('street', $streets);
        }

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
     * @return list<Authority>|null
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
     * @return array{0: list<Authority>|null, 1: bool}
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
     * The authority-set table, read as the objects the format actually carries.
     *
     * An entry is `{level, code, type?}`. This used to keep an entry only when it was a
     * STRING, which is the v2 shape — against a v3 artifact every entry was dropped,
     * every set came out empty, and an empty set means something specific and wrong:
     * "no local authority levies here". The version gate in {@see fromArtifacts()} now
     * stops a v2 document before it reaches this, and a malformed entry inside a v3 one
     * is skipped rather than silently becoming an empty set.
     *
     * @return list<list<Authority>>
     */
    private static function sets(mixed $value): array
    {
        $sets = [];

        foreach (is_array($value) ? $value : [] as $set) {
            $authorities = [];

            foreach (is_array($set) ? $set : [] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $level = $entry['level'] ?? null;
                $code = $entry['code'] ?? null;

                // The state is IN the set, and only where the file says it levies
                // there — element 24 repeats the state FIPS where the state's own rate
                // applies and reads `00` where it does not (Nevada writes `00` on every
                // row). So a consumer must not add the state rate itself; the set
                // already answers whether it is due. An entry missing either half is
                // not an authority and cannot be joined to a rate.
                if (! is_string($level) || $level === '' || ! is_scalar($code) || (string) $code === '') {
                    continue;
                }

                $type = $entry['type'] ?? null;

                $authorities[] = new Authority(
                    $level,
                    (string) $code,
                    is_scalar($type) && (string) $type !== '' ? (string) $type : null,
                );
            }

            $sets[] = $authorities;
        }

        return $sets;
    }

    /**
     * @param  array<string, mixed>  $artifact
     *
     * @throws UnsupportedFormatVersion
     */
    private static function assertVersion(string $name, array $artifact): void
    {
        $version = $artifact['formatVersion'] ?? null;

        if (! is_int($version) || ! in_array($version, self::SUPPORTED, true)) {
            throw UnsupportedFormatVersion::for($name, $version, self::SUPPORTED);
        }
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
