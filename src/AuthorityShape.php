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
    ) {}

    public function contains(Point $point): bool
    {
        return array_any($this->polygons, fn (Polygon $polygon): bool => $polygon->contains($point));
    }
}
