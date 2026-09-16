<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\Authority;
use Cboxdk\TaxResolver\BoundaryData;
use Cboxdk\TaxResolver\Method;
use Cboxdk\TaxResolver\ParsedAddress;
use Cboxdk\TaxResolver\Resolver;
use Cboxdk\TaxResolver\UnsupportedFormatVersion;

/*
 * THE REGRESSION, against bytes the register actually published.
 *
 * `tests/Fixtures/ks.v3.json` is a genuine slice of `boundaries/KS` from release
 * 2026.09.15-202 — four real ZIPs and the fourteen authority sets they reference, with
 * the set pointers re-indexed to match and nothing else touched.
 *
 * It exists because every test in this suite used to be written against hand-built v2
 * fixtures, which agreed with the parser rather than with the publisher. v0.1.0 read a
 * real v3 artifact, dropped every authority, and answered "no local authority levies
 * here" for every address in all twenty-four Streamlined states — and the suite was
 * green throughout. A fixture that comes out of the wire cannot do that.
 */

/**
 * @return array<string, mixed>
 */
function kansas(): array
{
    $raw = file_get_contents(__DIR__.'/Fixtures/ks.v3.json');
    $decoded = $raw === false ? null : json_decode($raw, true);
    $out = [];

    foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
        $out[(string) $key] = $value;
    }

    return $out;
}

it('reads a real published artifact into authorities that are actually there', function (): void {
    $data = BoundaryData::fromArtifacts(kansas());

    [$authorities] = $data->resolveZip('66101', '1366');
    $list = $authorities ?? [];

    expect($authorities)->not->toBeNull()
        ->and($list)->not->toBe([])   // the exact shape of the v0.1.0 failure
        ->and($list)->toHaveCount(3)
        ->and($list[0])->toBeInstanceOf(Authority::class)
        ->and(array_map(fn (Authority $a): string => $a->key(), $list))
        ->toBe(['state:20', 'county:209', 'city:36000']);
});

it('takes the narrow span over the whole-ZIP fallback that also covers the address', function (): void {
    // Kansas 66002 is the case the format document names: add-on 5033 sits inside a
    // narrow span assigning one county AND inside the whole-ZIP row assigning another.
    // Both match. Only narrowest-first decides, and it decides which county is paid.
    $resolver = new Resolver;
    $data = BoundaryData::fromArtifacts(kansas());

    $narrow = $resolver->resolve(new ParsedAddress(zip5: '66002', plus4: '5033'), $data);
    $whole = $resolver->resolve(new ParsedAddress(zip5: '66002'), $data);

    expect(array_map(fn (Authority $a): string => $a->key(), $narrow->authorities ?? []))
        ->toBe(['state:20', 'county:087'])
        ->and($narrow->method)->toBe(Method::Zip4)
        ->and(array_map(fn (Authority $a): string => $a->key(), $whole->authorities ?? []))
        ->toBe(['state:20', 'county:005'])
        ->and($whole->method)->toBe(Method::Zip5);
});

it('keeps the district type the state filed, and never merges on a bare code', function (): void {
    $sets = BoundaryData::fromArtifacts(kansas())->sets;

    $districts = [];

    foreach ($sets as $set) {
        foreach ($set as $authority) {
            if ($authority->level === 'district') {
                $districts[] = $authority;
            }
        }
    }

    $first = $districts[0] ?? null;

    expect($districts)->not->toBe([])
        ->and($first?->type)->not->toBeNull()
        ->and($first?->key())->toStartWith('district:');
});

it('refuses the same artifact if its version is changed to one this code cannot read', function (): void {
    $artifact = kansas();
    $artifact['formatVersion'] = 99;

    expect(fn (): BoundaryData => BoundaryData::fromArtifacts($artifact))
        ->toThrow(UnsupportedFormatVersion::class);
});
