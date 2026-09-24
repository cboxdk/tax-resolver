# tax-resolver

[![CI](https://github.com/cboxdk/tax-resolver/actions/workflows/ci.yml/badge.svg)](https://github.com/cboxdk/tax-resolver/actions/workflows/ci.yml)

Pure **address → taxing-jurisdiction** resolution over the Cbox Tax boundary dataset.
No framework, no I/O, no geocoder — just the logic that turns a geocoded address into
the set of taxing authorities that apply at it.

It is deliberately small and deliberately shared. The Cbox Tax **register** runs it at
build time to prove its shipped data resolves correctly; a **tax engine** runs it at
request time against the same downloaded data. Same code on both ends, so an address
can never resolve two different ways.

> The register produces the data. This package (and the engine embedding it) makes the
> determination. Geocoding happens in the engine, at the edge — never here, and never in
> the register.

## Install

```bash
composer require cboxdk/tax-resolver
```

Requires PHP 8.4+.

## The resolution ladder

An address is resolved by the most precise layer that answers, and the result says
which layer it was and how much to trust it:

1. **`street_range`** — a house number on a named street (the rooftop layer).
2. **`zip4`** — a ZIP+4 add-on.
3. **`zip5`** — a whole ZIP.
4. **`polygon`** — point-in-polygon, for states and special districts the postal files
   do not cover.

First match wins, spans are held narrowest-first, and an **empty** authority set (no
local tax applies here) is distinct from **null** (the data is silent — fall back to the
state rate).

## Usage

Your engine geocodes the address and loads the relevant state's dataset (the register's
`<state>.json`, `<state>.streets.json`, and optionally `<state>.geo.json` artifacts),
then asks the resolver:

```php
use Cboxdk\TaxResolver\{Resolver, BoundaryData, ParsedAddress, Point, Accuracy};

$data = BoundaryData::fromArtifacts(
    json_decode(file_get_contents('ks.json'), true),
    json_decode(file_get_contents('ks.streets.json'), true), // optional
);

$address = new ParsedAddress(
    zip5: '66716',
    houseNumber: 1150,
    street: '4800th',
    suffix: 'St',
    point: new Point(lng: -95.07, lat: 37.89), // for the polygon fallback
    accuracy: Accuracy::Rooftop,
);

$assignment = new Resolver()->resolve($address, $data /*, $geometry */);

$assignment->authorities; // list<Authority>, or null — see below
$assignment->method;      // Method::StreetRange
$assignment->confidence;  // Confidence::Exact

foreach ($assignment->authorities ?? [] as $authority) {
    $authority->level;  // 'state' | 'county' | 'city' | 'district'
    $authority->code;   // the code the source files it under, verbatim
    $authority->type;   // the state's own district-type code, where it states one
    $authority->key();  // 'county:209' — identity is level AND code
}
```

An authority joins to the rate data by `{stateFips, level, code}`. Match on **both**
halves: a county and a special district can file under the same number and levy
separately, so matching on the bare code merges two bodies that each want their own
share. Computing the combined rate (and applying category, sourcing and holiday rules)
is the engine's job, not this package's.

### null is not the same as an empty list

`authorities` of `null` means no layer answered — the dataset is silent here, and the
caller falls back to the state rate. An **empty list** means a layer answered "no local
authority levies here", which is an answer. They are one keystroke apart and cost money
in opposite directions; use `$assignment->resolved()` rather than checking emptiness.

### Format versions

The postal artifacts are `formatVersion` 3 and the geometry artifacts 2 or 3; they
version independently. Geometry v3 adds `properties.replaces`: an authority listed there
is dropped wherever the replacing one also covers the point (Texas's combined areas). An artifact written to a version this package does not implement throws
`UnsupportedFormatVersion` rather than reading what it can. That is deliberate: a
partially-read set is indistinguishable from "no local tax here", which is a wrong
answer with no error attached to it.

## What it does not do

- **Geocoding.** Bring your own geocoder (Geocodio is the reference); hand in a
  `ParsedAddress`.
- **Rate arithmetic.** It returns authorities, not a percentage.
- **I/O.** It reads no files and makes no network calls; load the dataset yourself.

## License

MIT — see [LICENSE](LICENSE).
