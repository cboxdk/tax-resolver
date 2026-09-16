# Changelog

All notable changes to this package are documented here.

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
