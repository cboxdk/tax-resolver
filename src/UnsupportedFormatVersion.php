<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

use RuntimeException;

/**
 * A boundary artifact written to a format version this resolver does not implement.
 *
 * REFUSING IS THE POINT. The format says a consumer MUST reject a `formatVersion` it
 * does not implement rather than guess, and the reason is not theoretical: v0.1.0 of
 * this package read a v3 artifact as if it were v2, found no strings where it expected
 * codes, and produced an EMPTY authority set for every address in every Streamlined
 * state. An empty set is not a failure in this format — it means "a row covers this
 * address and no local authority levies here" — so the wrong answer arrived with no
 * error attached to it, and anything pricing from it under-charged silently.
 *
 * A version this code cannot read has to stop, because the alternative is a confident
 * answer that is wrong in the direction nobody audits.
 */
class UnsupportedFormatVersion extends RuntimeException
{
    /**
     * @param  list<int>  $supported
     */
    public static function for(string $artifact, mixed $found, array $supported): self
    {
        $describe = is_scalar($found) ? var_export($found, true) : get_debug_type($found);

        return new self(sprintf(
            'The %s artifact declares formatVersion %s; this resolver implements %s. Refusing to read it: an unreadable set is indistinguishable from "no local tax here".',
            $artifact,
            $describe,
            implode(' and ', array_map(strval(...), $supported)),
        ));
    }
}
