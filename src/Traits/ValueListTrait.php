<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query\Traits;

use BackedEnum;
use Closure;
use Stringable;

/**
 * Trait for adding the ability to collect a set of column names and arbitrary values that will be converted to parameters.
 */
trait ValueListTrait
{

    /**
     * Array of column name keys and arbitrary values. Values may be scalar, Stringable, BackedEnum, or a Closure that returns the same.
     * 
     * @var array<string,string|Stringable|BackedEnum|int|float|bool|null|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool|null))> $value_list
     */
    protected array $value_list = [];

    /**
     * Set all column names and values for this query, overwriting any existing data. Note that this will overwrite any existing values in this query.
     * 
     * @param array<string,string|Stringable|BackedEnum|int|float|bool|null|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool|null))> $values
     */
    public function values(array $values): static
    {
        $this->value_list = $values;
        return $this;
    }

    /**
     * Set a single column name and value pair for this query. Adding it if not already selected, and overwriting it if it is.
     * 
     * @param string|Stringable|BackedEnum|int|float|bool|null|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool|null)) $value
     */
    public function value(string $column_name, string|Stringable|BackedEnum|int|float|bool|null|Closure $value): static
    {
        $this->value_list[$column_name] = $value;
        return $this;
    }

    /**
     * @return array<int,string|Stringable|BackedEnum|int|float|bool|null|(Closure(mixed...):(string|Stringable|BackedEnum|int|float|bool|null))>
     */
    protected function valueParameters(): array
    {
        return array_values($this->value_list);
    }

}
