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

$assignment->authorities; // ['001'] — the taxing authority codes that apply
$assignment->method;      // Method::StreetRange
$assignment->confidence;  // Confidence::Exact
```

The authority codes join to the rate data by `{stateFips, level, jurisdictionCode}`;
computing the combined rate (and applying category, sourcing and holiday rules) is the
engine's job, not this package's.

## What it does not do

- **Geocoding.** Bring your own geocoder (Geocodio is the reference); hand in a
  `ParsedAddress`.
- **Rate arithmetic.** It returns authorities, not a percentage.
- **I/O.** It reads no files and makes no network calls; load the dataset yourself.

## License

MIT — see [LICENSE](LICENSE).
