<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * The canonical key a street resolves under — the one thing the register (writing the
 * index) and an engine (querying it) must compute identically.
 *
 * A house lands on the right side of a boundary only if "N Commercial Ave" hashes to
 * the same key whether it came out of a Board boundary file or out of a geocoder. So
 * both sides run this, and both follow USPS Publication 28: directionals to their
 * one-letter form, street suffixes to their standard abbreviation, everything upper
 * and space-collapsed. The map is deliberately shared rather than clever — an engine
 * that abbreviates "AVENUE" one way and the register another is a silent mismatch, and
 * the only defence is running the same table on both ends.
 */
class AddressKey
{
    /** USPS Pub 28 directionals, long form to the abbreviation the files carry. */
    private const array DIRECTIONALS = [
        'NORTH' => 'N', 'SOUTH' => 'S', 'EAST' => 'E', 'WEST' => 'W',
        'NORTHEAST' => 'NE', 'NORTHWEST' => 'NW', 'SOUTHEAST' => 'SE', 'SOUTHWEST' => 'SW',
    ];

    /**
     * USPS Pub 28 Appendix C1 street suffixes, common forms to the standard primary
     * abbreviation. A suffix already in its abbreviation, or one with no standard form,
     * passes through upper-cased — the same on both ends, which is all that matters.
     */
    private const array SUFFIXES = [
        'ALLEY' => 'ALY', 'ANEX' => 'ANX', 'ARCADE' => 'ARC', 'AVENUE' => 'AVE', 'AV' => 'AVE',
        'BAYOU' => 'BYU', 'BEACH' => 'BCH', 'BEND' => 'BND', 'BLUFF' => 'BLF', 'BOTTOM' => 'BTM',
        'BOULEVARD' => 'BLVD', 'BOUL' => 'BLVD', 'BRANCH' => 'BR', 'BRIDGE' => 'BRG', 'BROOK' => 'BRK',
        'BYPASS' => 'BYP', 'CAMP' => 'CP', 'CANYON' => 'CYN', 'CAPE' => 'CPE', 'CAUSEWAY' => 'CSWY',
        'CENTER' => 'CTR', 'CIRCLE' => 'CIR', 'CLIFF' => 'CLF', 'CLIFFS' => 'CLFS', 'CLUB' => 'CLB',
        'COMMON' => 'CMN', 'CORNER' => 'COR', 'COURSE' => 'CRSE', 'COURT' => 'CT', 'COURTS' => 'CTS',
        'COVE' => 'CV', 'CREEK' => 'CRK', 'CRESCENT' => 'CRES', 'CROSSING' => 'XING', 'CROSSROAD' => 'XRD',
        'CURVE' => 'CURV', 'DALE' => 'DL', 'DAM' => 'DM', 'DIVIDE' => 'DV', 'DRIVE' => 'DR', 'DRIV' => 'DR',
        'ESTATE' => 'EST', 'ESTATES' => 'ESTS', 'EXPRESSWAY' => 'EXPY', 'EXTENSION' => 'EXT',
        'FALL' => 'FALL', 'FALLS' => 'FLS', 'FERRY' => 'FRY', 'FIELD' => 'FLD', 'FIELDS' => 'FLDS',
        'FLAT' => 'FLT', 'FORD' => 'FRD', 'FOREST' => 'FRST', 'FORGE' => 'FRG', 'FORK' => 'FRK',
        'FORT' => 'FT', 'FREEWAY' => 'FWY', 'GARDEN' => 'GDN', 'GARDENS' => 'GDNS', 'GATEWAY' => 'GTWY',
        'GLEN' => 'GLN', 'GREEN' => 'GRN', 'GROVE' => 'GRV', 'HARBOR' => 'HBR', 'HAVEN' => 'HVN',
        'HEIGHTS' => 'HTS', 'HIGHWAY' => 'HWY', 'HILL' => 'HL', 'HILLS' => 'HLS', 'HOLLOW' => 'HOLW',
        'INLET' => 'INLT', 'ISLAND' => 'IS', 'ISLANDS' => 'ISS', 'JUNCTION' => 'JCT', 'KEY' => 'KY',
        'KNOLL' => 'KNL', 'KNOLLS' => 'KNLS', 'LAKE' => 'LK', 'LAKES' => 'LKS', 'LANDING' => 'LNDG',
        'LANE' => 'LN', 'LIGHT' => 'LGT', 'LOAF' => 'LF', 'LOCK' => 'LCK', 'LODGE' => 'LDG',
        'LOOP' => 'LOOP', 'MALL' => 'MALL', 'MANOR' => 'MNR', 'MEADOW' => 'MDW', 'MEADOWS' => 'MDWS',
        'MEWS' => 'MEWS', 'MILL' => 'ML', 'MILLS' => 'MLS', 'MISSION' => 'MSN', 'MOTORWAY' => 'MTWY',
        'MOUNT' => 'MT', 'MOUNTAIN' => 'MTN', 'MOUNTAINS' => 'MTNS', 'NECK' => 'NCK', 'ORCHARD' => 'ORCH',
        'OVAL' => 'OVAL', 'OVERPASS' => 'OPAS', 'PARK' => 'PARK', 'PARKWAY' => 'PKWY', 'PASS' => 'PASS',
        'PASSAGE' => 'PSGE', 'PATH' => 'PATH', 'PIKE' => 'PIKE', 'PINE' => 'PNE', 'PINES' => 'PNES',
        'PLACE' => 'PL', 'PLAIN' => 'PLN', 'PLAINS' => 'PLNS', 'PLAZA' => 'PLZ', 'POINT' => 'PT',
        'POINTS' => 'PTS', 'PORT' => 'PRT', 'PRAIRIE' => 'PR', 'RADIAL' => 'RADL', 'RANCH' => 'RNCH',
        'RAPID' => 'RPD', 'RAPIDS' => 'RPDS', 'REST' => 'RST', 'RIDGE' => 'RDG', 'RIDGES' => 'RDGS',
        'RIVER' => 'RIV', 'ROAD' => 'RD', 'ROADS' => 'RDS', 'ROUTE' => 'RTE', 'ROW' => 'ROW',
        'RUN' => 'RUN', 'SHOAL' => 'SHL', 'SHOALS' => 'SHLS', 'SHORE' => 'SHR', 'SHORES' => 'SHRS',
        'SPRING' => 'SPG', 'SPRINGS' => 'SPGS', 'SPUR' => 'SPUR', 'SQUARE' => 'SQ', 'STATION' => 'STA',
        'STRAVENUE' => 'STRA', 'STREAM' => 'STRM', 'STREET' => 'ST', 'STR' => 'ST', 'SUMMIT' => 'SMT',
        'TERRACE' => 'TER', 'THROUGHWAY' => 'TRWY', 'TRACE' => 'TRCE', 'TRACK' => 'TRAK', 'TRAIL' => 'TRL',
        'TRAILER' => 'TRLR', 'TUNNEL' => 'TUNL', 'TURNPIKE' => 'TPKE', 'UNDERPASS' => 'UPAS', 'UNION' => 'UN',
        'VALLEY' => 'VLY', 'VIADUCT' => 'VIA', 'VIEW' => 'VW', 'VILLAGE' => 'VLG', 'VILLE' => 'VL',
        'VISTA' => 'VIS', 'WALK' => 'WALK', 'WALL' => 'WALL', 'WAY' => 'WAY', 'WELL' => 'WL',
        'WELLS' => 'WLS',
    ];

    /**
     * The canonical key: pre-directional, name, suffix, post-directional, joined with
     * a pipe. Empty parts stay empty, so "MAIN ST" with no directionals is `|MAIN|ST|`.
     */
    public static function of(?string $preDir, string $name, ?string $suffix, ?string $postDir): string
    {
        return implode('|', [
            self::directional($preDir),
            self::name($name),
            self::suffix($suffix),
            self::directional($postDir),
        ]);
    }

    private static function upper(?string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strtoupper((string) $value)));
    }

    private static function directional(?string $value): string
    {
        $normal = self::upper($value);

        return self::DIRECTIONALS[$normal] ?? $normal;
    }

    private static function suffix(?string $value): string
    {
        $normal = self::upper($value);

        return self::SUFFIXES[$normal] ?? $normal;
    }

    private static function name(string $value): string
    {
        // The pipe is the key's own separator, so a name can never contain one.
        return str_replace('|', ' ', self::upper($value));
    }
}
