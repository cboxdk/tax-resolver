<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * A state's authority polygons — the point-in-polygon layer for the places the postal
 * files cannot express: non-SST states, and special districts that do not follow ZIPs.
 *
 * Loaded from the `<state>.geo.json` artifact, a GeoJSON FeatureCollection where each
 * feature is one authority. The register produces it; this reads it; a point resolves
 * to every authority whose ground contains it.
 */
readonly class Geometry
{
    /**
     * The geometry wire format this reader implements. The postal layers version
     * independently of this one, and they are currently at 3.
     */
    public const array SUPPORTED = [2];

    /**
     * @param  list<AuthorityShape>  $shapes
     */
    public function __construct(
        public array $shapes,
    ) {}

    /**
     * Every authority whose ground contains the point.
     *
     * This layer names the register's OWN jurisdiction code in `properties.authority`
     * (`us:CA:CITY-ALAMEDA`), not a code in the state's space, so the answer joins to a
     * rate directly — all 558 of California's features match a jurisdiction that carries
     * one. That is why {@see Authority::named()} fills `jurisdiction` here and the
     * postal layers leave it null.
     *
     * @return list<Authority>
     */
    public function authoritiesAt(Point $point): array
    {
        $authorities = [];

        foreach ($this->shapes as $shape) {
            if ($shape->contains($point)) {
                $authorities[] = Authority::named($shape->authority, $shape->level);
            }
        }

        return $authorities;
    }

    /**
     * @param  array<string, mixed>  $json  a decoded GeoJSON FeatureCollection
     *
     * @throws UnsupportedFormatVersion When the collection is written to a version this
     *                                  reader does not implement.
     */
    public static function fromFeatureCollection(array $json): self
    {
        $version = $json['formatVersion'] ?? null;

        if (! is_int($version) || ! in_array($version, self::SUPPORTED, true)) {
            throw UnsupportedFormatVersion::for('geometry', $version, self::SUPPORTED);
        }

        $features = $json['features'] ?? null;
        $shapes = [];

        foreach (is_array($features) ? $features : [] as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $properties = $feature['properties'] ?? null;
            $properties = is_array($properties) ? $properties : [];
            $geometry = $feature['geometry'] ?? null;
            $polygons = is_array($geometry) ? self::polygonsOf($geometry) : [];

            if ($polygons === []) {
                continue;
            }

            $shapes[] = new AuthorityShape(
                self::text($properties['authority'] ?? ''),
                self::text($properties['level'] ?? ''),
                self::text($properties['name'] ?? ''),
                $polygons,
            );
        }

        return new self($shapes);
    }

    /**
     * @param  array<mixed, mixed>  $geometry
     * @return list<Polygon>
     */
    private static function polygonsOf(array $geometry): array
    {
        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return [];
        }

        if (($geometry['type'] ?? null) === 'Polygon') {
            return [self::polygon($coordinates)];
        }

        if (($geometry['type'] ?? null) === 'MultiPolygon') {
            $polygons = [];

            foreach ($coordinates as $rings) {
                if (is_array($rings)) {
                    $polygons[] = self::polygon($rings);
                }
            }

            return $polygons;
        }

        return [];
    }

    /**
     * @param  array<int|string, mixed>  $rings
     */
    private static function polygon(array $rings): Polygon
    {
        $parsed = [];

        foreach ($rings as $ring) {
            if (is_array($ring)) {
                $parsed[] = self::ring($ring);
            }
        }

        return new Polygon($parsed[0] ?? [], array_slice($parsed, 1));
    }

    /**
     * @param  array<int|string, mixed>  $ring
     * @return list<array{0: float, 1: float}>
     */
    private static function ring(array $ring): array
    {
        $points = [];

        foreach ($ring as $pair) {
            if (is_array($pair) && isset($pair[0], $pair[1]) && is_numeric($pair[0]) && is_numeric($pair[1])) {
                $points[] = [(float) $pair[0], (float) $pair[1]];
            }
        }

        return $points;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
