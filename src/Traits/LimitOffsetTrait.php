<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query\Traits;

use InvalidArgumentException;

/**
 * Trait for adding the ability to specify a LIMIT/OFFSET on a query.
 */
trait LimitOffsetTrait
{

    protected int|null $limit = null;

    protected int|null $offset = null;

    /**
     * Set the LIMIT of this query
     */
    public function limit(int|null $limit): static
    {
        if ($limit < -1)
            throw new InvalidArgumentException("LIMIT must be greater than or equal to -1");
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set the OFFSET of this query
     */
    public function offset(int|null $offset): static
    {
        if ($offset < 0)
            throw new InvalidArgumentException("OFFSET must be a positive number");
        $this->offset = $offset;
        return $this;
    }

    /**
     * Get a valid LIMIT/OFFSET statement if set, otherwise an empty string
     */
    protected function sql_limit_offset(): string
    {
        if ($this->limit !== null)
            if ($this->offset)
                return "LIMIT {$this->limit} OFFSET {$this->offset}";
            else
                return "LIMIT {$this->limit}";
        elseif ($this->offset)
            return "LIMIT -1 OFFSET {$this->offset}";
        else
            return "";
    }

}
