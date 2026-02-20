<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

class TestStringable implements \Stringable
{

    public function __construct(private string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }

}
