<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\Authority;
use Cboxdk\TaxResolver\BoundaryData;
use Cboxdk\TaxResolver\UnsupportedFormatVersion;

/**
 * @param  list<Authority>|null  $authorities
 * @return list<string>
 */
function keys(?array $authorities): array
{
    return array_map(fn (Authority $a): string => $a->key(), $authorities ?? []);
}

it('resolves ZIP+4, whole-ZIP fallback and ranges from the wire format', function (): void {
    $data = BoundaryData::fromArtifacts([
        'formatVersion' => 3,
        'sets' => [
            [['level' => 'county', 'code' => '209'], ['level' => 'city', 'code' => '36000']],
            [['level' => 'county', 'code' => '049']],
            [],
        ],
        'zip' => ['66101' => [['1300', '1399', 0], ['0000', '9999', 2]]],
        'ranges' => [['67300', '67399', '0000', '9999', 1]],
    ]);

    [$narrow, $wasNarrow] = $data->resolveZip('66101', '1366');
    [$wide] = $data->resolveZip('66101', '5000');
    [$range] = $data->resolveZip('67349');
    [$miss] = $data->resolveZip('99999');

    expect(keys($narrow))->toBe(['county:209', 'city:36000'])
        ->and($wasNarrow)->toBeTrue()
        ->and($wide)->toBe([])          // an ANSWER: no local authority levies here
        ->and(keys($range))->toBe(['county:049'])
        ->and($miss)->toBeNull();       // no row covers this address at all
});

it('identifies an authority on level AND code, never the bare number', function (): void {
    // A county and a special district filing under the same number levy separately.
    // Matching on '209' alone merges two bodies that each want their own share.
    $data = BoundaryData::fromArtifacts([
        'formatVersion' => 3,
        'sets' => [[
            ['level' => 'county', 'code' => '209'],
            ['level' => 'district', 'code' => '209', 'type' => '79'],
        ]],
        'zip' => ['27601' => [['0000', '9999', 0]]],
        'ranges' => [],
    ]);

    [$authorities] = $data->resolveZip('27601');

    $list = $authorities ?? [];

    expect(keys($list))->toBe(['county:209', 'district:209'])
        ->and($list[1]->type)->toBe('79')
        ->and($list[0]->is('county', '209'))->toBeTrue()
        ->and($list[0]->is('district', '209'))->toBeFalse();
});

it('refuses a formatVersion it does not implement rather than reading it as empty', function (): void {
    // THE REGRESSION. v0.1.0 read this exact document as v2, found no strings where it
    // expected codes, and answered [] — which in this format means "no local authority
    // levies here". A wrong answer with no error attached to it.
    $v2 = ['formatVersion' => 2, 'sets' => [['20', '209']], 'zip' => ['66101' => [['0000', '9999', 0]]], 'ranges' => []];

    expect(fn () => BoundaryData::fromArtifacts($v2))->toThrow(UnsupportedFormatVersion::class)
        ->and(fn () => BoundaryData::fromArtifacts(['sets' => [], 'zip' => [], 'ranges' => []]))
        ->toThrow(UnsupportedFormatVersion::class);
});

it('refuses a street artifact written to a version it does not implement', function (): void {
    expect(fn () => BoundaryData::fromArtifacts(
        ['formatVersion' => 3, 'sets' => [], 'zip' => [], 'ranges' => []],
        ['formatVersion' => 2, 'sets' => [['001']], 'street' => []],
    ))->toThrow(UnsupportedFormatVersion::class);
});

it('resolves a street off its own set table, narrowest-first', function (): void {
    $data = BoundaryData::fromArtifacts(
        ['formatVersion' => 3, 'sets' => [], 'zip' => [], 'ranges' => []],
        [
            'formatVersion' => 3,
            'sets' => [[['level' => 'city', 'code' => '111']], [['level' => 'city', 'code' => '222']]],
            'street' => ['12345' => ['|MAIN|ST|' => [[500, 500, 'B', 1], [1, 999, 'B', 0]]]],
        ],
    );

    expect(keys($data->resolveStreet('12345', '|MAIN|ST|', 500)))->toBe(['city:222'])
        ->and(keys($data->resolveStreet('12345', '|MAIN|ST|', 300)))->toBe(['city:111'])
        ->and($data->resolveStreet('12345', '|MAIN|ST|', 2000))->toBeNull();
});

it('skips an entry that carries no level or no code', function (): void {
    $data = BoundaryData::fromArtifacts([
        'formatVersion' => 3,
        'sets' => [[
            ['level' => 'state', 'code' => '20'],
            ['level' => '', 'code' => '999'],
            ['level' => 'city', 'code' => ''],
            ['code' => '888'],
        ]],
        'zip' => ['66101' => [['0000', '9999', 0]]],
        'ranges' => [],
    ]);

    [$authorities] = $data->resolveZip('66101');

    expect(keys($authorities))->toBe(['state:20']);
});
