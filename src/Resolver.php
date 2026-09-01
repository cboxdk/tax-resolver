<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * Address → taxing authorities, over the dataset the register ships.
 *
 * The ladder, most precise first: a street range, then a ZIP+4, then a whole ZIP, then
 * point-in-polygon for the states the postal files do not cover. It stops at the first
 * layer that answers and reports which one it was and how much to trust it. It never
 * geocodes — the engine does that at the edge and hands in a {@see ParsedAddress}.
 *
 * This is the shared brain: the register runs it at build time against its own written
 * artifacts to prove they resolve the certification decks, and an engine runs it at
 * request time. Same code, so an address cannot resolve two ways.
 */
readonly class Resolver
{
    public function resolve(ParsedAddress $address, BoundaryData $data, ?Geometry $geometry = null): JurisdictionAssignment
    {
        if ($address->houseNumber !== null) {
            $authorities = $data->resolveStreet($address->zip5, $address->streetKey(), $address->houseNumber);

            if ($authorities !== null) {
                return new JurisdictionAssignment($authorities, Method::StreetRange, $this->confidenceFrom($address->accuracy));
            }
        }

        [$authorities, $narrow] = $data->resolveZip($address->zip5, $address->plus4);

        if ($authorities !== null) {
            $method = $address->plus4 !== '' && $narrow ? Method::Zip4 : Method::Zip5;

            return new JurisdictionAssignment(
                $authorities,
                $method,
                $method === Method::Zip4 ? Confidence::Interpolated : Confidence::Coarse,
            );
        }

        if ($geometry instanceof Geometry && $address->point instanceof Point) {
            $authorities = $geometry->authoritiesAt($address->point);

            if ($authorities !== []) {
                return new JurisdictionAssignment($authorities, Method::Polygon, $this->confidenceFrom($address->accuracy));
            }
        }

        return JurisdictionAssignment::none();
    }

    private function confidenceFrom(Accuracy $accuracy): Confidence
    {
        return match ($accuracy) {
            Accuracy::Rooftop => Confidence::Exact,
            Accuracy::Interpolated => Confidence::Interpolated,
            Accuracy::Coarse => Confidence::Coarse,
        };
    }
}
