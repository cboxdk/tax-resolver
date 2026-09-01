<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * A stretch of house numbers on one street that falls in one set of taxing authorities.
 *
 * Parity is part of the range, not a footnote: a row may cover only the odd side of a
 * street or only the even, so 101 and 102 on the same block can sit in different
 * jurisdictions. `O` odd, `E` even, `B` both.
 */
readonly class StreetSpan
{
    public function __construct(
        public int $low,
        public int $high,
        public string $parity,
        public int $set,
    ) {}

    public function covers(int $house): bool
    {
        if ($house < $this->low || $house > $this->high) {
            return false;
        }

        return match ($this->parity) {
            'O' => $house % 2 === 1,
            'E' => $house % 2 === 0,
            default => true,
        };
    }
}
