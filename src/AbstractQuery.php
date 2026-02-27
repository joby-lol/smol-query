<?php

/**
 * smolQuery
 * https://github.com/joby-lol/smol-query
 * (c) 2026 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Query;

use BackedEnum;
use Closure;
use Stringable;

abstract class AbstractQuery
{

    public function __construct(protected readonly DB $db) {}

    /**
     * Build the final SQL query for this object.
     */
    abstract public function sql(): string;

    /**
     * Get all the final scalar values for the parameters of this query.
     * 
     * @return array<int,string|int|float|null>
     */
    abstract public function parameters(): array;

    /**
     * Given a list of parameter values, which may by scalar, Stringable, BackedEnum, or a closure that returns the same, compile them down to scalar values only for final binding to a query. This should be called on the final parameter list before executing a query.
     * 
     * @param array<int,string|Stringable|BackedEnum|int|float|bool|null|(Closure():(string|Stringable|BackedEnum|int|float|bool|null))>  $parameters
     * @return array<int,string|int|float|null>
     */
    protected function finalizeParameterList(array $parameters): array
    {
        return array_map(
            function ($v): string|int|float|null {
                if ($v instanceof Closure)
                    return $v(); // @phpstan-ignore-line this just can't really be type-checked
                if ($v instanceof Stringable)
                    return (string) $v;
                if ($v instanceof BackedEnum)
                    return $v->value;
                if (is_bool($v))
                    return $v ? 1 : 0;
                return $v;
            },
            $parameters,
        );
    }

}
