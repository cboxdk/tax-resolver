# Changelog

All notable changes to this package are documented here.

## v1.1.0

### Added — geometry formatVersion 3: an authority may stand IN PLACE OF others

Texas publishes a "combined area" wherever a city and a special district overlap: its
own code, its own rate, to be used instead of the city's and the district's. All three
polygons cover the point, so reading every authority over it charged the city, the
district and the combination together — 5.5% at an address in Bee Cave that owes 2%.

A v3 feature may carry `properties.replaces`, a list of authority codes, and
`Geometry::authoritiesAt()` drops any of them that another authority over the same point
replaces. A malformed list throws; `replaces` in a v2 file is ignored. v1.0.0 refuses a
v3 file outright (`UnsupportedFormatVersion`), which is the safe failure: no local
answer, never a summed one. v2 files read exactly as before.

## v1.0.0

The API is settled. No code changed from v0.2.0 — what changed is the commitment:
`Authority`, `JurisdictionAssignment`, `BoundaryData`, `Geometry` and `Resolver` are
now stable surface, and a breaking change to any of them needs a v2.

That commitment is worth making now because the shape has been used rather than
merely published. `cboxdk/laravel-tax` resolves US addresses through this package,
and the register's own conformance deck — addresses in, expected authority set out,
cut from the artifacts a release actually ships — passes through it. Two readers of
one format drifting apart is the failure this package exists to prevent, and it is
the failure v0.1.0 shipped; a deck that both sides run is the only thing that proves
they have not.

### What a consumer commits to

- An authority is identified by **level AND code**. A county and a special district
  can file under the same number and levy separately.
- **`null` is not `[]`.** Null means no layer answered and the caller falls back to
  the state rate; an empty list means a row answered "no local authority levies
  here". Use `resolved()`.
- **An unknown `formatVersion` throws.** A version this code cannot read has to stop,
  because the alternative is a confident answer that is wrong in the direction nobody
  audits.

## v0.2.0

### Fixed — formatVersion 3 was read as if it were v2, and answered empty

An authority set is `[{level, code, type?}]`. `BoundaryData::sets()` kept an entry only
when it was a bare string — the v2 shape — so against a v3 artifact every entry was
dropped and every set came out empty.

An empty set is not nothing in this format: it means *a row covers this address and no
local authority levies here*. So the resolver reported no local tax, with no error, for
every address in all twenty-four Streamlined states. Against the real Kansas artifact
that is 1029 sets, every one of them wrong.

The suite was green throughout, because every fixture had been hand-built to the
parser's assumption rather than to what the register publishes.

### Changed — breaking

- `JurisdictionAssignment::$authorities` is `list<Authority>|null`, was `list<string>|null`.
- `BoundaryData::resolveZip()` and `resolveStreet()` return `Authority` objects.
- `Geometry::authoritiesAt()` returns `Authority` objects.
- `BoundaryData::fromArtifacts()` and `Geometry::fromFeatureCollection()` throw
  `UnsupportedFormatVersion` for a version they do not implement, instead of degrading.

### Added

- `Authority` — `level`, `code`, optional `type` (the state's own district-type code:
  79 and 26 in North Carolina, 63 in Minnesota), and `jurisdiction`, filled only by the
  geometry layer, which publishes the register's own code.
- `Authority::key()` and `is()` — identity is level **and** code, never the bare number.
- `UnsupportedFormatVersion`.
- `tests/Fixtures/ks.v3.json` — a genuine slice of `boundaries/KS` from register release
  2026.09.15-202, so the suite is measured against published bytes rather than against
  its own assumptions.

## v0.1.0

Initial release: the pure address to jurisdiction resolver.
