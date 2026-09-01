<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\BoundaryData;

it('resolves ZIP+4, whole-ZIP fallback and ranges from the wire format', function (): void {
    $data = BoundaryData::fromArtifacts([
        'sets' => [['209', '36000'], ['049'], []],
        'zip' => ['66101' => [['1300', '1399', 0], ['0000', '9999', 2]]],
        'ranges' => [['67300', '67399', '0000', '9999', 1]],
    ]);

    expect($data->resolveZip('66101', '1366'))->toBe([['209', '36000'], true])   // targeted span
        ->and($data->resolveZip('66101', '5000'))->toBe([[], false])             // whole-ZIP fallback, empty set
        ->and($data->resolveZip('67349'))->toBe([['049'], false])               // a range
        ->and($data->resolveZip('99999'))->toBe([null, false]);                 // no match at all
});

it('resolves a street off its own set table, narrowest-first', function (): void {
    $data = BoundaryData::fromArtifacts(
        ['sets' => [], 'zip' => [], 'ranges' => []],
        ['sets' => [['111'], ['222']], 'street' => ['12345' => ['|MAIN|ST|' => [[500, 500, 'B', 1], [1, 999, 'B', 0]]]]],
    );

    expect($data->resolveStreet('12345', '|MAIN|ST|', 500))->toBe(['222'])
        ->and($data->resolveStreet('12345', '|MAIN|ST|', 300))->toBe(['111'])
        ->and($data->resolveStreet('12345', '|MAIN|ST|', 2000))->toBeNull();
});
