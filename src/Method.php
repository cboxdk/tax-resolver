<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * How an address was resolved, most precise to least. The resolver reports it on every
 * answer so a caller can see whether it got a rooftop or a ZIP-centroid guess.
 */
enum Method: string
{
    case StreetRange = 'street_range';
    case Zip4 = 'zip4';
    case Zip5 = 'zip5';
    case Polygon = 'polygon';
    case ZipCentroid = 'zip_centroid';
    case None = 'none';
}
