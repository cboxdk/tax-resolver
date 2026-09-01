<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\BoundaryData;
use Cboxdk\TaxResolver\Confidence;
use Cboxdk\TaxResolver\Geometry;
use Cboxdk\TaxResolver\Method;
use Cboxdk\TaxResolver\ParsedAddress;
use Cboxdk\TaxResolver\Point;
use Cboxdk\TaxResolver\Resolver;

it('takes the rooftop street answer when it has a house number', function (): void {
    $data = BoundaryData::fromArtifacts(
        ['sets' => [['ZIP']], 'zip' => ['66716' => [['0000', '9999', 0]]], 'ranges' => []],
        ['sets' => [['001']], 'street' => ['66716' => ['|4800TH|ST|' => [[1100, 1199, 'B', 0]]]]],
    );

    $assignment = new Resolver()->resolve(
        new ParsedAddress(zip5: '66716', plus4: '3077', houseNumber: 1150, street: '4800th', suffix: 'St'),
        $data,
    );

    expect($assignment->authorities)->toBe(['001'])
        ->and($assignment->method)->toBe(Method::StreetRange)
        ->and($assignment->confidence)->toBe(Confidence::Exact);
});

it('falls down the ladder: ZIP+4, then whole ZIP, then polygon, then nothing', function (): void {
    $data = BoundaryData::fromArtifacts([
        'sets' => [['CITY'], ['STATE']],
        'zip' => ['10001' => [['0500', '0599', 0], ['0000', '9999', 1]]],
        'ranges' => [],
    ]);
    $empty = BoundaryData::fromArtifacts(['sets' => [], 'zip' => [], 'ranges' => []]);
    $resolver = new Resolver;

    $zip4 = $resolver->resolve(new ParsedAddress(zip5: '10001', plus4: '0550'), $data);
    expect($zip4->method)->toBe(Method::Zip4)->and($zip4->authorities)->toBe(['CITY']);

    $zip5 = $resolver->resolve(new ParsedAddress(zip5: '10001', plus4: '9000'), $data);
    expect($zip5->method)->toBe(Method::Zip5)->and($zip5->authorities)->toBe(['STATE']);

    $geo = Geometry::fromFeatureCollection(['features' => [
        ['properties' => ['authority' => 'PARISH', 'level' => 'county', 'name' => 'X'],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[0, 0], [0, 10], [10, 10], [10, 0], [0, 0]]]]],
    ]]);
    $polygon = $resolver->resolve(new ParsedAddress(zip5: '99999', point: new Point(5.0, 5.0)), $empty, $geo);
    expect($polygon->method)->toBe(Method::Polygon)->and($polygon->authorities)->toBe(['PARISH']);

    $none = $resolver->resolve(new ParsedAddress(zip5: '99999'), $empty);
    expect($none->resolved())->toBeFalse()->and($none->method)->toBe(Method::None);
});
