<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * One polygon: an outer ring, and any holes cut out of it.
 *
 * Containment is a ray-cast (even-odd rule) behind a bounding-box prefilter, because a
 * state carries thousands of these and most of them are nowhere near the point. A hole
 * is a ring the point must NOT be inside — a lake inside a county is not in the county.
 *
 * Coordinates are `[lng, lat]` pairs, WGS84, the ring closed (first pair equals last),
 * exactly as GeoJSON stores them.
 */
readonly class Polygon
{
    public float $minLng;

    public float $minLat;

    public float $maxLng;

    public float $maxLat;

    /**
     * @param  list<array{0: float, 1: float}>  $outer
     * @param  list<list<array{0: float, 1: float}>>  $holes
     */
    public function __construct(
        public array $outer,
        public array $holes = [],
    ) {
        $minLng = $minLat = INF;
        $maxLng = $maxLat = -INF;

        foreach ($outer as [$lng, $lat]) {
            $minLng = min($minLng, $lng);
            $maxLng = max($maxLng, $lng);
            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
        }

        $this->minLng = $minLng;
        $this->minLat = $minLat;
        $this->maxLng = $maxLng;
        $this->maxLat = $maxLat;
    }

    public function contains(Point $point): bool
    {
        if ($point->lng < $this->minLng || $point->lng > $this->maxLng
            || $point->lat < $this->minLat || $point->lat > $this->maxLat) {
            return false;
        }

        if (! $this->inRing($point, $this->outer)) {
            return false;
        }

        return array_all($this->holes, fn (array $hole): bool => ! $this->inRing($point, $hole));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     */
    private function inRing(Point $point, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            // The edge straddles the point's latitude, and the point is left of where
            // the edge crosses it. Each such crossing flips inside/outside. The first
            // clause guarantees $yj !== $yi, so the division is safe.
            $straddles = ($yi > $point->lat) !== ($yj > $point->lat);

            if ($straddles && $point->lng < ($xj - $xi) * ($point->lat - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
