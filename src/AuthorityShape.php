<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * One taxing authority and the ground it covers — a multipolygon, because a county or
 * a special district need not be one connected piece.
 */
readonly class AuthorityShape
{
    /**
     * @param  list<Polygon>  $polygons
     */
    public function __construct(
        public string $authority,
        public string $level,
        public string $name,
        public array $polygons,
        /**
         * The authorities this one stands IN PLACE OF where it applies (geometry v3).
         *
         * Texas publishes a "combined area" wherever a city and a special district
         * overlap, with its own code and its own rate, to be used INSTEAD of the city's
         * and the district's. Every one of those polygons still covers the point, so a
         * reader that returned all of them would add the city, the district and the
         * combination together: 5.5% at an address in Bee Cave that owes 2%.
         *
         * @var list<string>
         */
        public array $replaces = [],
    ) {}

    public function contains(Point $point): bool
    {
        return array_any($this->polygons, fn (Polygon $polygon): bool => $polygon->contains($point));
    }
}
