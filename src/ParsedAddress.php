<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * A geocoded address, in the shape the resolver needs — the output of a geocoder, not
 * a raw string. The engine geocodes at the edge and fills this in; the resolver never
 * sees the original address.
 *
 * The name parts feed the canonical street key; the ZIP and add-on feed the postal
 * layers; the point feeds point-in-polygon. A caller supplies as much as it has, and
 * the resolver uses the most precise layer that answers.
 */
readonly class ParsedAddress
{
    public function __construct(
        public string $zip5,
        public string $plus4 = '',
        public ?int $houseNumber = null,
        public string $preDir = '',
        public string $street = '',
        public string $suffix = '',
        public string $postDir = '',
        public ?Point $point = null,
        public Accuracy $accuracy = Accuracy::Rooftop,
    ) {}

    public function streetKey(): string
    {
        return AddressKey::of($this->preDir, $this->street, $this->suffix, $this->postDir);
    }
}
