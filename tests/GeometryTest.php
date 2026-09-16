<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\Authority;
use Cboxdk\TaxResolver\Geometry;
use Cboxdk\TaxResolver\Point;
use Cboxdk\TaxResolver\UnsupportedFormatVersion;

/**
 * @param  list<Authority>|null  $authorities
 * @return list<string>
 */
function named(?array $authorities): array
{
    return array_map(fn (Authority $a): string => $a->code, $authorities ?? []);
}

it('loads a FeatureCollection and resolves a point to every authority over it', function (): void {
    $geo = Geometry::fromFeatureCollection([
        'type' => 'FeatureCollection',
        'formatVersion' => 2,
        'features' => [
            ['type' => 'Feature', 'properties' => ['authority' => 'us:CA:COUNTY-LOS-ANGELES', 'level' => 'county', 'name' => 'Los Angeles'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]]]]],
            ['type' => 'Feature', 'properties' => ['authority' => 'us:CA:CITY-ALAMEDA', 'level' => 'city', 'name' => 'Alameda'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[4, 4], [4, 6], [6, 6], [6, 4], [4, 4]]]]],
        ],
    ]);

    expect(named($geo->authoritiesAt(new Point(5.0, 5.0))))->toBe(['us:CA:COUNTY-LOS-ANGELES', 'us:CA:CITY-ALAMEDA'])
        ->and(named($geo->authoritiesAt(new Point(1.0, 1.0))))->toBe(['us:CA:COUNTY-LOS-ANGELES'])
        ->and($geo->authoritiesAt(new Point(20.0, 20.0)))->toBe([]);
});

it('carries the register jurisdiction code, because the geometry layer publishes one', function (): void {
    // The postal layers give a code in the STATE's space and leave `jurisdiction` null;
    // this layer names the register's own code, so the answer joins to a rate directly.
    $geo = Geometry::fromFeatureCollection([
        'formatVersion' => 2,
        'features' => [
            ['properties' => ['authority' => 'us:CA:CITY-ALAMEDA', 'level' => 'city', 'name' => 'Alameda'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [0, 2], [2, 2], [2, 0], [0, 0]]]]],
        ],
    ]);

    $authorities = $geo->authoritiesAt(new Point(1.0, 1.0));
    $authority = $authorities[0] ?? null;

    expect($authority?->jurisdiction)->toBe('us:CA:CITY-ALAMEDA')
        ->and($authority?->level)->toBe('city');
});

it('reads a MultiPolygon as one authority in two pieces', function (): void {
    $geo = Geometry::fromFeatureCollection([
        'formatVersion' => 2,
        'features' => [
            ['properties' => ['authority' => 'X', 'level' => 'county', 'name' => 'X'],
                'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [
                    [[[0, 0], [0, 2], [2, 2], [2, 0], [0, 0]]],
                    [[[10, 10], [10, 12], [12, 12], [12, 10], [10, 10]]],
                ]]],
        ],
    ]);

    expect(named($geo->authoritiesAt(new Point(1.0, 1.0))))->toBe(['X'])
        ->and(named($geo->authoritiesAt(new Point(11.0, 11.0))))->toBe(['X'])
        ->and($geo->authoritiesAt(new Point(5.0, 5.0)))->toBe([]);
});

it('refuses a geometry version it does not implement', function (): void {
    expect(fn () => Geometry::fromFeatureCollection(['features' => []]))
        ->toThrow(UnsupportedFormatVersion::class);
});
