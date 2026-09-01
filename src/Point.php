<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * A geocoded point, in the order GeoJSON uses: longitude then latitude.
 *
 * The order is a trap worth naming — most of the world says "lat, lng" out loud, and
 * GeoJSON stores "lng, lat". This holds them named, so nothing downstream has to
 * remember which slot is which.
 */
readonly class Point
{
    public function __construct(
        public float $lng,
        public float $lat,
    ) {}
}
