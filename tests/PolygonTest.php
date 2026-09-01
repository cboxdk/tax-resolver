<?php

declare(strict_types=1);

use Cboxdk\TaxResolver\Point;
use Cboxdk\TaxResolver\Polygon;

it('contains a point inside its ring and rejects one outside', function (): void {
    $square = new Polygon([[0.0, 0.0], [0.0, 10.0], [10.0, 10.0], [10.0, 0.0], [0.0, 0.0]]);

    expect($square->contains(new Point(5.0, 5.0)))->toBeTrue()
        ->and($square->contains(new Point(15.0, 5.0)))->toBeFalse()
        ->and($square->contains(new Point(-1.0, 5.0)))->toBeFalse();
});

it('excludes a point that falls in a hole', function (): void {
    $donut = new Polygon(
        [[0.0, 0.0], [0.0, 10.0], [10.0, 10.0], [10.0, 0.0], [0.0, 0.0]],
        [[[3.0, 3.0], [3.0, 7.0], [7.0, 7.0], [7.0, 3.0], [3.0, 3.0]]],
    );

    expect($donut->contains(new Point(5.0, 5.0)))->toBeFalse()
        ->and($donut->contains(new Point(1.0, 1.0)))->toBeTrue();
});
