<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\AddressKey;

it('normalises directionals, suffix and case into a canonical key', function (): void {
    expect(AddressKey::of('north', 'Main', 'Avenue', null))->toBe('N|MAIN|AVE|')
        ->and(AddressKey::of('', '4800th', 'St', ''))->toBe('|4800TH|ST|')
        ->and(AddressKey::of('SW', 'Martin  Luther King', 'Blvd', 'S'))->toBe('SW|MARTIN LUTHER KING|BLVD|S')
        ->and(AddressKey::of(null, 'Elm', 'Street', null))->toBe('|ELM|ST|');
});

it('passes an already-abbreviated or unknown suffix through unchanged', function (): void {
    expect(AddressKey::of('', 'Main', 'ST', ''))->toBe('|MAIN|ST|')
        ->and(AddressKey::of('', 'Main', 'Xyz', ''))->toBe('|MAIN|XYZ|');
});

it('strips a pipe from a name so a key can never split wrong', function (): void {
    expect(AddressKey::of('', 'A|B', 'St', ''))->toBe('|A B|ST|');
});
