<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * The answer: which taxing authorities apply, how it was reached, and how much to
 * trust it.
 *
 * `authorities` of null is not the same as an empty list. Null means no layer answered
 * — the dataset is silent here, and the caller falls back to the state rate. An empty
 * list means a layer answered "no local authority applies", which is an answer.
 */
readonly class JurisdictionAssignment
{
    /**
     * @param  list<string>|null  $authorities
     */
    public function __construct(
        public ?array $authorities,
        public Method $method,
        public Confidence $confidence,
    ) {}

    public function resolved(): bool
    {
        return $this->authorities !== null;
    }

    public static function none(): self
    {
        return new self(null, Method::None, Confidence::None);
    }
}
