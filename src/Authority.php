<?php

declare(strict_types=1);

namespace Cboxdk\TaxResolver;

/**
 * One taxing authority in a resolved set: what kind of body it is, and the code the
 * source files it under.
 *
 * IT SAYS WHAT IT IS, and that is the whole reason this is an object. Through
 * formatVersion 2 an authority was a bare code and its level was its POSITION in the
 * list — a shape that could not survive the source, because the SSTGB boundary format
 * carries up to twenty district triplets per row and position can only hold one. An
 * empty county code was skipped rather than held, so a city with no county above it
 * read as a county.
 *
 * IDENTITY IS LEVEL AND CODE TOGETHER, never the bare code: a county and a special
 * district may file under the same number and levy separately, so matching on `209`
 * alone merges two authorities that each want their own share.
 *
 * `jurisdiction` is filled only where the artifact names the register's own code for
 * this body — the geometry layer does, the postal layers do not. Where it is null the
 * consumer joins to a rate by `{stateFips, level, code}`, which is what the format
 * says to do.
 */
readonly class Authority
{
    public function __construct(
        public string $level,
        public string $code,
        /**
         * The source's OWN code for the kind of district, present only where the file
         * states one — North Carolina files `79` and `26`, Minnesota `63`, Vermont
         * `02`. Every one of them is read as `district` for want of a finer word, and
         * anybody reconciling against a state's own return needs the number the state
         * used.
         */
        public ?string $type = null,
        public ?string $jurisdiction = null,
    ) {}

    /** The identity a consumer matches on: level and code, never one of them. */
    public function key(): string
    {
        return $this->level.':'.$this->code;
    }

    public function is(string $level, string $code): bool
    {
        return $this->level === $level && $this->code === $code;
    }

    /**
     * An authority named by the geometry layer, which publishes the register's own
     * jurisdiction code directly rather than a code in the state's space.
     */
    public static function named(string $jurisdiction, string $level): self
    {
        return new self($level, $jurisdiction, null, $jurisdiction);
    }
}
