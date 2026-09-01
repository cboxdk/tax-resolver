<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\Geometry;
use Cboxdk\TaxResolver\Point;

it('loads a FeatureCollection and resolves a point to every authority over it', function (): void {
    $geo = Geometry::fromFeatureCollection([
        'type' => 'FeatureCollection',
        'features' => [
            ['type' => 'Feature', 'properties' => ['authority' => '06-037', 'level' => 'county', 'name' => 'LA'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]]]]],
            ['type' => 'Feature', 'properties' => ['authority' => 'CITY', 'level' => 'city', 'name' => 'Town'],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[4, 4], [4, 6], [6, 6], [6, 4], [4, 4]]]]],
        ],
    ]);

    expect($geo->authoritiesAt(new Point(5.0, 5.0)))->toBe(['06-037', 'CITY'])
        ->and($geo->authoritiesAt(new Point(1.0, 1.0)))->toBe(['06-037'])
        ->and($geo->authoritiesAt(new Point(20.0, 20.0)))->toBe([]);
});

it('reads a MultiPolygon as one authority in two pieces', function (): void {
    $geo = Geometry::fromFeatureCollection([
        'features' => [
            ['properties' => ['authority' => 'X', 'level' => 'county', 'name' => 'X'],
                'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [
                    [[[0, 0], [0, 2], [2, 2], [2, 0], [0, 0]]],
                    [[[10, 10], [10, 12], [12, 12], [12, 10], [10, 10]]],
                ]]],
        ],
    ]);

    expect($geo->authoritiesAt(new Point(1.0, 1.0)))->toBe(['X'])
        ->and($geo->authoritiesAt(new Point(11.0, 11.0)))->toBe(['X'])
        ->and($geo->authoritiesAt(new Point(5.0, 5.0)))->toBe([]);
});
