<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * How much to trust an answer, derived from the method and the geocoder's accuracy.
 * A caller must never present a `Coarse` or `Centroid` answer as if it were `Exact`.
 */
enum Confidence: string
{
    case Exact = 'exact';
    case Interpolated = 'interpolated';
    case Coarse = 'coarse';
    case Centroid = 'centroid';
    case None = 'none';
}
