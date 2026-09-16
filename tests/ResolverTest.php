<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\Authority;
use Cboxdk\TaxResolver\BoundaryData;
use Cboxdk\TaxResolver\Confidence;
use Cboxdk\TaxResolver\Geometry;
use Cboxdk\TaxResolver\Method;
use Cboxdk\TaxResolver\ParsedAddress;
use Cboxdk\TaxResolver\Point;
use Cboxdk\TaxResolver\Resolver;

/**
 * @param  list<Authority>|null  $authorities
 * @return list<string>
 */
function resolved(?array $authorities): array
{
    return array_map(fn (Authority $a): string => $a->key(), $authorities ?? []);
}

it('takes the rooftop street answer when it has a house number', function (): void {
    $data = BoundaryData::fromArtifacts(
        [
            'formatVersion' => 3,
            'sets' => [[['level' => 'state', 'code' => '20']]],
            'zip' => ['66716' => [['0000', '9999', 0]]],
            'ranges' => [],
        ],
        [
            'formatVersion' => 3,
            'sets' => [[['level' => 'state', 'code' => '20'], ['level' => 'city', 'code' => '001']]],
            'street' => ['66716' => ['|4800TH|ST|' => [[1100, 1199, 'B', 0]]]],
        ],
    );

    $assignment = new Resolver()->resolve(
        new ParsedAddress(zip5: '66716', plus4: '3077', houseNumber: 1150, street: '4800th', suffix: 'St'),
        $data,
    );

    expect(resolved($assignment->authorities))->toBe(['state:20', 'city:001'])
        ->and($assignment->method)->toBe(Method::StreetRange)
        ->and($assignment->confidence)->toBe(Confidence::Exact);
});

it('falls down the ladder: ZIP+4, then whole ZIP, then polygon, then nothing', function (): void {
    $data = BoundaryData::fromArtifacts([
        'formatVersion' => 3,
        'sets' => [
            [['level' => 'city', 'code' => '36000']],
            [['level' => 'state', 'code' => '20']],
        ],
        'zip' => ['10001' => [['0500', '0599', 0], ['0000', '9999', 1]]],
        'ranges' => [],
    ]);
    $empty = BoundaryData::fromArtifacts(['formatVersion' => 3, 'sets' => [], 'zip' => [], 'ranges' => []]);
    $resolver = new Resolver;

    $zip4 = $resolver->resolve(new ParsedAddress(zip5: '10001', plus4: '0550'), $data);
    expect($zip4->method)->toBe(Method::Zip4)->and(resolved($zip4->authorities))->toBe(['city:36000']);

    $zip5 = $resolver->resolve(new ParsedAddress(zip5: '10001', plus4: '9000'), $data);
    expect($zip5->method)->toBe(Method::Zip5)->and(resolved($zip5->authorities))->toBe(['state:20']);

    $geo = Geometry::fromFeatureCollection(['formatVersion' => 2, 'features' => [
        ['properties' => ['authority' => 'us:LA:PARISH-ORLEANS', 'level' => 'county', 'name' => 'Orleans'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]]]]],
    ]]);
    $polygon = $resolver->resolve(new ParsedAddress(zip5: '99999', point: new Point(5.0, 5.0)), $empty, $geo);
    expect($polygon->method)->toBe(Method::Polygon)
        ->and(resolved($polygon->authorities))->toBe(['county:us:LA:PARISH-ORLEANS']);

    $none = $resolver->resolve(new ParsedAddress(zip5: '99999'), $empty);
    expect($none->resolved())->toBeFalse()->and($none->method)->toBe(Method::None);
});

it('tells an empty answer from no answer', function (): void {
    // One keystroke apart, and they cost money in opposite directions. An empty set is
    // a row saying no local authority levies here; null is silence, and the caller
    // falls back to the state rate.
    $data = BoundaryData::fromArtifacts([
        'formatVersion' => 3,
        'sets' => [[]],
        'zip' => ['66101' => [['0000', '9999', 0]]],
        'ranges' => [],
    ]);
    $resolver = new Resolver;

    $answered = $resolver->resolve(new ParsedAddress(zip5: '66101'), $data);
    $silent = $resolver->resolve(new ParsedAddress(zip5: '99999'), $data);

    expect($answered->authorities)->toBe([])->and($answered->resolved())->toBeTrue()
        ->and($silent->authorities)->toBeNull()->and($silent->resolved())->toBeFalse();
});
