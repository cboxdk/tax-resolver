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

it('drops what a combined area stands in place of, and nothing else (v3)', function (): void {
    // Texas: a combined area where a city and a district overlap has its own code, used
    // INSTEAD of theirs. Bee Cave city 2%, Travis ESD 10 1.5%; the combination 1.5%
    // plus the library district its row keeps, 0.5%. Summed without this: 5.5%.
    $square = static fn (int $a, int $b): array => ['type' => 'Polygon', 'coordinates' => [[[$a, $a], [$a, $b], [$b, $b], [$b, $a], [$a, $a]]]];
    $geo = Geometry::fromFeatureCollection([
        'formatVersion' => 3,
        'features' => [
            ['properties' => ['authority' => 'us:TX:CITY-2227150', 'level' => 'city', 'name' => 'Bee Cave'], 'geometry' => $square(0, 10)],
            ['properties' => ['authority' => 'us:TX:DISTRICT-5227702', 'level' => 'district', 'name' => 'Travis Co ESD 10'], 'geometry' => $square(4, 20)],
            ['properties' => ['authority' => 'us:TX:DISTRICT-5227506', 'level' => 'district', 'name' => 'Westbank Library Dist'], 'geometry' => $square(0, 20)],
            ['properties' => ['authority' => 'us:TX:DISTRICT-6227025', 'level' => 'district', 'name' => 'Bee Cave/Travis ESD No 10',
                'replaces' => ['us:TX:CITY-2227150', 'us:TX:DISTRICT-5227702']], 'geometry' => $square(4, 10)],
        ],
    ]);

    // Inside the combination: the combination and the library, not the city or the ESD.
    expect(named($geo->authoritiesAt(new Point(5.0, 5.0))))->toBe(['us:TX:DISTRICT-5227506', 'us:TX:DISTRICT-6227025'])
        // In the city outside the ESD: the city and the library, untouched.
        ->and(named($geo->authoritiesAt(new Point(2.0, 2.0))))->toBe(['us:TX:CITY-2227150', 'us:TX:DISTRICT-5227506'])
        // In the ESD outside the city: the ESD and the library.
        ->and(named($geo->authoritiesAt(new Point(15.0, 15.0))))->toBe(['us:TX:DISTRICT-5227702', 'us:TX:DISTRICT-5227506']);
});

it('ignores replaces in a v2 file and refuses a malformed one in v3', function (): void {
    $feature = static fn (mixed $replaces): array => ['properties' => ['authority' => 'A', 'level' => 'city', 'name' => 'A', 'replaces' => $replaces],
        'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [0, 2], [2, 2], [2, 0], [0, 0]]]]];

    expect(Geometry::fromFeatureCollection(['formatVersion' => 2, 'features' => [$feature(['A'])]])->shapes[0]->replaces)->toBe([])
        ->and(fn () => Geometry::fromFeatureCollection(['formatVersion' => 3, 'features' => [$feature('A')]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Geometry::fromFeatureCollection(['formatVersion' => 3, 'features' => [$feature([1])]]))->toThrow(InvalidArgumentException::class);
});
