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
 *
 * The two are one keystroke apart and cost real money in opposite directions, which is
 * why {@see resolved()} exists and why nothing here collapses them. Reading a v3
 * artifact with a v2 parser turned every answer into the second kind, and the tests of
 * the day could not see it because they asserted on bare strings that v3 does not
 * carry.
 */
readonly class JurisdictionAssignment
{
    /**
     * @param  list<Authority>|null  $authorities
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
