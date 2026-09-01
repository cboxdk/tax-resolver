<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\StreetSpan;

it('matches a house by range and by odd/even/both parity', function (): void {
    $odd = new StreetSpan(100, 200, 'O', 0);
    $even = new StreetSpan(100, 200, 'E', 0);
    $both = new StreetSpan(100, 200, 'B', 0);

    expect($odd->covers(101))->toBeTrue()
        ->and($odd->covers(102))->toBeFalse()
        ->and($odd->covers(99))->toBeFalse()
        ->and($even->covers(102))->toBeTrue()
        ->and($even->covers(101))->toBeFalse()
        ->and($both->covers(150))->toBeTrue()
        ->and($both->covers(201))->toBeFalse();
});
