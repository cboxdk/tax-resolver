<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * How precisely the geocoder placed the address. The engine passes this in; the
 * resolver folds it into the confidence it reports.
 *
 * Named for the values a geocoder actually returns (Geocodio's `accuracy_type` is the
 * reference), collapsed to the three that change the answer's trust.
 */
enum Accuracy: string
{
    case Rooftop = 'rooftop';
    case Interpolated = 'range_interpolation';
    case Coarse = 'coarse';

    /**
     * Map a geocoder's own accuracy string onto the three that matter. Anything not a
     * clean rooftop or interpolation is coarse — the safe reading of an unknown.
     */
    public static function fromGeocoder(string $value): self
    {
        return match (strtolower(trim($value))) {
            'rooftop', 'point' => self::Rooftop,
            'range_interpolation', 'interpolation', 'nearest_rooftop_match' => self::Interpolated,
            default => self::Coarse,
        };
    }
}
